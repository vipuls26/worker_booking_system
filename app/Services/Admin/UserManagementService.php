<?php

namespace App\Services\Admin;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use App\Models\WorkerVerification;
use App\Notifications\BookingWorkflowNotification;
use App\Services\Audit\AuditLogger;
use App\Support\Filters\UserFilter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserManagementService
{
    public function __construct(
        private readonly UserFilter $filter,
        private readonly AuditLogger $audit,
    ) {}

    public function paginate(Request $request): LengthAwarePaginator
    {
        // User management excludes admin accounts so staff accounts are handled through safer admin-only flows.
        $query = User::query()
            ->with(['role', 'customerProfile'])
            ->withCount([
                'workerBookings as active_worker_bookings_count' => function (Builder $query): void {
                    // Admins need this count before blocking so they understand customer impact.
                    $query->whereIn('status', Booking::ActiveStatuses);
                },
            ])
            ->whereDoesntHave('role', fn ($query) => $query->where('slug', 'admin'))
            ->latest();

        return $this->filter
            ->apply($query, $request)
            ->paginate($request->integer('per_page', 15));
    }

    public function block(User $user): User
    {
        $this->ensureNotAdmin($user, 'Admin accounts cannot be blocked.');

        $admin = request()->user();

        // Audit records must still be written if the service is called outside an authenticated admin request.
        if (! $admin instanceof User) {
            $admin = null;
        }

        DB::transaction(function () use ($user, $admin): void {
            $user->forceFill([
                'is_blocked' => true,
                'is_verified' => false,
                'email_verified_at' => null,
            ])->save();

            $bookingCancellationSummary = [
                'cancelled_count' => 0,
                'refund_review_count' => 0,
            ];

            // Blocking a worker also removes marketplace verification until an admin re-approves them.
            if ($user->loadMissing('role')->hasRole('worker')) {
                $user->workerProfile()->updateOrCreate(['user_id' => $user->id], [
                    'is_verified' => false,
                ]);

                $bookingCancellationSummary = $this->cancelActiveWorkerBookings($user, $admin);
            }

            $this->audit->record('admin.user_blocked', $admin, $user, [
                'email_verification_reset' => true,
                'admin_approval_reset' => true,
                'active_bookings_cancelled' => $bookingCancellationSummary['cancelled_count'] ?? 0,
                'refund_reviews_required' => $bookingCancellationSummary['refund_review_count'] ?? 0,
            ]);
        });

        return $user->refresh()->load(['role', 'customerProfile', 'workerProfile'])
            ->loadCount([
                'workerBookings as active_worker_bookings_count' => function (Builder $query): void {
                    // The UI should show zero once the worker block cancellation is complete.
                    $query->whereIn('status', Booking::ActiveStatuses);
                },
            ]);
    }

    public function unblock(User $user): User
    {
        $this->ensureNotAdmin($user, 'Admin accounts cannot be unblocked from user management.');

        $user->update(['is_blocked' => false]);

        $this->audit->record('admin.user_unblocked', request()->user(), $user);

        return $user->refresh()->load(['role', 'customerProfile']);
    }

    /**
     * Cancel every active booking owned by a blocked worker so customers are not left with unusable assignments.
     *
     * @return array{cancelled_count: int, refund_review_count: int}
     */
    private function cancelActiveWorkerBookings(User $worker, ?User $admin): array
    {
        $cancelledCount = 0;
        $refundReviewCount = 0;

        // Active bookings are the customer commitments that must be closed immediately when a worker is blocked.
        $activeBookings = $worker->workerBookings()
            ->with(['customer', 'service', 'payments'])
            ->whereIn('status', Booking::ActiveStatuses)
            ->oldest('booking_date')
            ->get();

        foreach ($activeBookings as $booking) {
            $refundReviewCount += $this->cancelBlockedWorkerBooking($booking, $admin);
            $cancelledCount++;
        }

        return [
            'cancelled_count' => $cancelledCount,
            'refund_review_count' => $refundReviewCount,
        ];
    }

    /**
     * Cancel one worker booking and return one when finance must review a paid booking refund.
     */
    private function cancelBlockedWorkerBooking(Booking $booking, ?User $admin): int
    {
        $oldStatus = $booking->status;
        $needsRefundReview = $this->bookingNeedsRefundReview($booking);

        $bookingUpdates = [
            'status' => Booking::STATUS_CANCELLED,
            'cancelled_by' => $admin?->id,
            'cancelled_reason' => 'Worker blocked by admin',
        ];

        // Paid bookings stay out of refunded status until finance has checked the money movement.
        if ($needsRefundReview) {
            $bookingUpdates['payment_status'] = Booking::PAYMENT_REFUND_REVIEW;
        }

        $booking->update($bookingUpdates);

        $this->recordBlockedWorkerBookingCancellation($booking, $oldStatus, $admin, $needsRefundReview);
        $this->notifyCustomerAboutBlockedWorkerCancellation($booking);

        // The caller uses this count to tell admins how many paid bookings require finance review.
        if ($needsRefundReview) {
            return 1;
        }

        return 0;
    }

    /**
     * Check whether a cancelled booking has money captured and needs finance review.
     */
    private function bookingNeedsRefundReview(Booking $booking): bool
    {
        // The booking status can already show paid before individual payment records are inspected.
        if ($booking->payment_status === Booking::PAYMENT_PAID) {
            return true;
        }

        // Paid payment rows prove money was collected even if the booking summary was not updated.
        return $booking->payments->contains(function (Payment $payment): bool {
            return $payment->status === Payment::STATUS_PAID;
        });
    }

    /**
     * Store both the booking timeline row and the admin audit row for compliance review.
     */
    private function recordBlockedWorkerBookingCancellation(Booking $booking, string $oldStatus, ?User $admin, bool $needsRefundReview): void
    {
        // Customers and admins need the booking timeline to explain why the booking ended.
        $booking->activities()->create([
            'actor_id' => $admin?->id,
            'from_status' => $oldStatus,
            'to_status' => Booking::STATUS_CANCELLED,
            'event' => 'worker_blocked_booking_cancelled',
            'note' => 'Worker blocked by admin',
        ]);

        // Audit logs let admins review every booking affected by a worker block.
        $this->audit->record('admin.worker_blocked_booking_cancelled', $admin, $booking, [
            'worker_id' => $booking->worker_id,
            'customer_id' => $booking->customer_id,
            'refund_review_required' => $needsRefundReview,
        ]);
    }

    /**
     * Notify the customer that the platform cancelled the booking after the worker was blocked.
     */
    private function notifyCustomerAboutBlockedWorkerCancellation(Booking $booking): void
    {
        // Only existing customer accounts can receive database notifications.
        if ($booking->customer === null) {
            return;
        }

        $booking->customer->notify(new BookingWorkflowNotification(
            booking: $booking->refresh()->loadMissing('service'),
            event: 'worker_blocked_booking_cancelled',
            title: 'Booking cancelled',
            message: 'Your booking was cancelled because the assigned worker is no longer available on the platform.',
        ));
    }

    public function verify(User $user): User
    {
        $this->ensureNotAdmin($user, 'Admin accounts cannot be verified from user management.');
        $this->ensureEmailVerified($user);
        $this->ensureWorkerVerificationApproved($user);

        $user->update(['is_verified' => true]);

        // Worker approval must keep the profile flag in sync for booking eligibility checks.
        if ($user->hasRole('worker')) {
            $user->workerProfile()->updateOrCreate(['user_id' => $user->id], [
                'is_verified' => true,
            ]);
        }

        $this->audit->record('admin.user_verified', request()->user(), $user);

        return $user->refresh()->load(['role', 'customerProfile', 'workerProfile', 'workerVerification']);
    }

    /**
     * @throws ValidationException
     */
    public function delete(User $user, User $admin): void
    {
        // Admins cannot delete themselves because the platform needs at least one active operator path.
        if ($user->is($admin)) {
            throw ValidationException::withMessages([
                'user' => ['You cannot delete your own admin account.'],
            ]);
        }

        $this->ensureNotAdmin($user, 'Admin accounts cannot be deleted from user management.');

        $this->audit->record('admin.user_deleted', $admin, $user, [
            'deleted_user_email' => $user->email,
        ]);

        $user->delete();
    }

    /**
     * @throws ValidationException
     */
    private function ensureNotAdmin(User $user, string $message): void
    {
        // Admin accounts are protected from customer/worker management actions.
        if ($user->loadMissing('role')->hasRole('admin')) {
            throw ValidationException::withMessages([
                'user' => [$message],
            ]);
        }
    }

    private function ensureEmailVerified(User $user): void
    {
        // Email verification proves the user can receive account and booking notifications.
        if ($user->hasVerifiedEmail()) {
            return;
        }

        throw ValidationException::withMessages([
            'email' => ['User must verify their email before admin approval.'],
        ]);
    }

    private function ensureWorkerVerificationApproved(User $user): void
    {
        // Customers do not need worker document approval before account verification.
        if (! $user->loadMissing('role')->hasRole('worker')) {
            return;
        }

        // Workers must have approved ID proof before admins mark the account platform-verified.
        $isApproved = $user->workerVerification()
            ->where('status', WorkerVerification::STATUS_APPROVED)
            ->exists();

        // Approved verification is enough to continue account approval.
        if ($isApproved) {
            return;
        }

        throw ValidationException::withMessages([
            'worker_verification' => ['Approve this worker ID proof before approving the user account.'],
        ]);
    }
}
