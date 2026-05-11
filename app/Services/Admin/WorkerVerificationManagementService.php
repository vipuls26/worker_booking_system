<?php

namespace App\Services\Admin;

use App\Models\User;
use App\Models\WorkerVerification;
use App\Services\Audit\AuditLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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
            ->with(['user.role', 'verifier.role'])
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
            $verification->loadMissing('user.role');
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
            ]);

            return $verification->refresh()->load(['user.role', 'verifier.role']);
        });
    }
}
