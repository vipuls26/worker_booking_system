<?php

namespace App\Services\Admin;

use App\Models\Booking;
use App\Models\User;
use App\Models\WorkerVerification;
use App\Notifications\BookingWorkflowNotification;
use App\Services\Audit\AuditLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkerVerificationManagementService
{
    private const DefaultPerPage = 15;

    private const PendingSortPriority = 0;

    private const ProcessedSortPriority = 1;

    /**
     * Receive the audit logger so every admin verification decision remains traceable.
     */
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * List worker verification requests for admins, with unresolved pending requests first so review work is prioritized.
     */
    public function paginate(Request $request): LengthAwarePaginator
    {
        $perPage = $request->integer('per_page', self::DefaultPerPage);
        $requestedStatus = $request->string('status')->toString();

        // Get worker verification records only, because admins should not review customer or admin accounts here.
        $verificationQuery = WorkerVerification::query()
            ->with([
                'user' => function ($query): void {
                    $query
                        ->with('role')
                        ->withCount([
                            'workerBookings as active_worker_bookings_count' => function (Builder $query): void {
                                // Admins need this count before removing verification because active customers are affected.
                                $query->whereIn('status', Booking::ActiveStatuses);
                            },
                        ]);
                },
                'verifier.role',
            ])
            ->whereHas('user.role', fn ($query) => $query->where('slug', 'worker'));

        // Apply the status filter only when admins intentionally narrow the review list.
        if ($request->filled('status')) {
            $verificationQuery->where('status', $requestedStatus);
        }

        return $verificationQuery
            ->orderByRaw($this->pendingFirstSortExpression(), [WorkerVerification::STATUS_PENDING])
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Approve a worker verification request and mark the worker profile usable for bookings.
     */
    public function approve(WorkerVerification $verification, User $admin): WorkerVerification
    {
        return DB::transaction(function () use ($verification, $admin): WorkerVerification {
            // Load the worker account so approval can update both verification and profile records together.
            $verification->loadMissing('user.role');
            $worker = $verification->user;

            $verification->update([
                'status' => WorkerVerification::STATUS_APPROVED,
                'rejection_reason' => null,
                'verified_by' => $admin->id,
                'verified_at' => now(),
            ]);

            // Mark the worker profile verified because approved workers may now receive platform bookings.
            $worker
                ?->workerProfile()
                ->updateOrCreate(['user_id' => $verification->user_id], [
                    'experience_years' => $verification->experience_years,
                    'is_verified' => true,
                ]);

            $this->audit->record('admin.worker_verification_approved', $admin, $verification, [
                'worker_id' => $verification->user_id,
            ]);

            return $verification->refresh()->load(['user.role', 'verifier.role']);
        });
    }

    /**
     * Reject a worker verification request when submitted proof is not acceptable for platform approval.
     */
    public function reject(WorkerVerification $verification, User $admin, string $reason): WorkerVerification
    {
        return $this->markNeedsChanges($verification, $admin, $reason, WorkerVerification::STATUS_REJECTED);
    }

    /**
     * Ask a worker to resubmit verification proof when admin review needs corrected documents.
     */
    public function requestResubmission(WorkerVerification $verification, User $admin, string $reason): WorkerVerification
    {
        return $this->markNeedsChanges($verification, $admin, $reason, WorkerVerification::STATUS_RESUBMISSION_REQUESTED);
    }

    /**
     * Build the database sort expression that keeps pending records above processed records.
     */
    private function pendingFirstSortExpression(): string
    {
        $pendingPriority = self::PendingSortPriority;
        $processedPriority = self::ProcessedSortPriority;

        return "CASE WHEN status = ? THEN {$pendingPriority} ELSE {$processedPriority} END";
    }

    /**
     * Mark a worker verification as needing changes and disable booking eligibility until approval.
     */
    private function markNeedsChanges(WorkerVerification $verification, User $admin, string $reason, string $status): WorkerVerification
    {
        return DB::transaction(function () use ($verification, $admin, $reason, $status): WorkerVerification {
            // Load the worker account so rejection or resubmission updates the linked platform status.
            $verification->loadMissing(['user.role']);
            $worker = $verification->user;

            $verification->update([
                'status' => $status,
                'rejection_reason' => $reason,
                'verified_by' => $admin->id,
                'verified_at' => now(),
            ]);

            // Remove platform verification because workers with unresolved verification cannot receive bookings.
            $worker?->forceFill(['is_verified' => false])->save();

            // Keep the worker profile unverified so worker-facing booking features remain blocked.
            $worker
                ?->workerProfile()
                ->updateOrCreate(['user_id' => $verification->user_id], [
                    'experience_years' => $verification->experience_years,
                    'is_verified' => false,
                ]);

            $this->audit->record('admin.worker_verification_'.$status, $admin, $verification, [
                'worker_id' => $verification->user_id,
                'reason' => $reason,
                'active_bookings_notified' => $worker ? $this->recordVerificationRemovedForActiveBookings($worker, $admin, $reason) : 0,
            ]);

            return $verification->refresh()->load([
                'user' => function ($query): void {
                    $query
                        ->with('role')
                        ->withCount([
                            'workerBookings as active_worker_bookings_count' => function (Builder $query): void {
                                // The admin response should keep showing active bookings because they are not cancelled.
                                $query->whereIn('status', Booking::ActiveStatuses);
                            },
                        ]);
                },
                'verifier.role',
            ]);
        });
    }

    /**
     * Add timeline notes and customer notifications when verification is removed but bookings stay active.
     */
    private function recordVerificationRemovedForActiveBookings(User $worker, User $admin, string $reason): int
    {
        $notifiedBookingsCount = 0;

        // Existing active bookings remain assigned, but each timeline must show the worker verification change.
        $activeBookings = $worker->workerBookings()
            ->with(['customer', 'service'])
            ->whereIn('status', Booking::ActiveStatuses)
            ->oldest('booking_date')
            ->get();

        foreach ($activeBookings as $booking) {
            $this->recordVerificationRemovedForBooking($booking, $admin, $reason);
            $notifiedBookingsCount++;
        }

        return $notifiedBookingsCount;
    }

    /**
     * Record and notify one active booking affected by worker verification removal.
     */
    private function recordVerificationRemovedForBooking(Booking $booking, User $admin, string $reason): void
    {
        // Booking status is intentionally unchanged because existing customer commitments continue.
        $booking->activities()->create([
            'actor_id' => $admin->id,
            'from_status' => $booking->status,
            'to_status' => $booking->status,
            'event' => 'worker_verification_removed',
            'note' => 'Worker verification removed by admin. '.$reason,
        ]);

        // Customers should know the assigned worker verification changed even though the booking remains active.
        $booking->customer?->notify(new BookingWorkflowNotification(
            booking: $booking,
            event: 'worker_verification_removed',
            title: 'Worker verification changed',
            message: 'The verification status for your assigned worker changed. Your booking is still active.',
        ));
    }
}
