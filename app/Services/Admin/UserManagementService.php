<?php

namespace App\Services\Admin;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestWorker;
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
    private const PARTIAL_BLOCK_REASON = 'Account partially blocked by admin';

    private const FULL_BLOCK_REASON = 'Worker blocked by admin';

    public function __construct(
        private readonly UserFilter $filter,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Show admin users with worker booking impact counts for moderation decisions.
     */
    public function paginate(Request $request): LengthAwarePaginator
    {
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

    /**
     * Apply a partial block while keeping login and profile access available.
     */
    public function partialBlock(User $user): User
    {
        $this->ensureNotAdmin($user, 'Admin accounts cannot be partially blocked.');

        $admin = $this->currentAdmin();

        DB::transaction(function () use ($user, $admin): void {
            $user->forceFill([
                'account_status' => User::STATUS_PARTIALLY_BLOCKED,
                'is_blocked' => false,
            ])->save();

            $partialBlockSummary = $this->handlePartialBlockBookings($user, $admin);

            $this->audit->record('admin.user_partially_blocked', $admin, $user, [
                'pending_service_requests_cancelled' => $partialBlockSummary['cancelled_service_requests_count'],
                'pending_worker_requests_cancelled' => $partialBlockSummary['cancelled_worker_requests_count'],
                'pending_bookings_cancelled' => $partialBlockSummary['cancelled_pending_bookings_count'],
            ]);
        });

        return $this->loadUserForAdminResponse($user);
    }

    /**
     * Apply a full block and reset trust checks that require reverification later.
     */
    public function fullBlock(User $user): User
    {
        $this->ensureNotAdmin($user, 'Admin accounts cannot be fully blocked.');

        $admin = $this->currentAdmin();

        DB::transaction(function () use ($user, $admin): void {
            $user->forceFill([
                'account_status' => User::STATUS_FULLY_BLOCKED,
                'is_blocked' => true,
                'is_verified' => false,
                'email_verified_at' => null,
            ])->save();

            $bookingCancellationSummary = [
                'cancelled_count' => 0,
                'refund_review_count' => 0,
            ];

            // Full blocks remove worker verification because the trust review must restart.
            if ($user->loadMissing('role')->hasRole('worker')) {
                $user->workerProfile()->updateOrCreate(['user_id' => $user->id], [
                    'is_verified' => false,
                ]);

                $bookingCancellationSummary = $this->cancelFutureWorkerBookings($user, $admin);
            }

            $this->audit->record('admin.user_fully_blocked', $admin, $user, [
                'email_verification_reset' => true,
                'admin_approval_reset' => true,
                'future_bookings_cancelled' => $bookingCancellationSummary['cancelled_count'],
                'refund_reviews_required' => $bookingCancellationSummary['refund_review_count'],
            ]);
        });

        return $this->loadUserForAdminResponse($user);
    }

    /**
     * Manually restore a blocked user outside the unblock request workflow.
     */
    public function unblock(User $user): User
    {
        $this->ensureNotAdmin($user, 'Admin accounts cannot be unblocked from user management.');

        $user->update([
            'account_status' => User::STATUS_ACTIVE,
            'is_blocked' => false,
        ]);

        $this->audit->record('admin.user_unblocked', request()->user(), $user);

        return $user->refresh()->load(['role', 'customerProfile']);
    }

    /**
     * Load the consistent user payload needed by the admin users screen.
     */
    private function loadUserForAdminResponse(User $user): User
    {
        return $user->refresh()->load(['role', 'customerProfile', 'workerProfile'])
            ->loadCount([
                'workerBookings as active_worker_bookings_count' => function (Builder $query): void {
                    $query->whereIn('status', Booking::ActiveStatuses);
                },
            ]);
    }

    /**
     * Cancel only the early-stage work that should not continue during a partial block.
     *
     * @return array{cancelled_service_requests_count: int, cancelled_worker_requests_count: int, cancelled_pending_bookings_count: int}
     */
    private function handlePartialBlockBookings(User $user, ?User $admin): array
    {
        $cancelledServiceRequestsCount = 0;
        $cancelledWorkerRequestsCount = 0;
        $cancelledPendingBookingsCount = 0;

        if ($user->loadMissing('role')->hasRole('customer')) {
            $cancelledServiceRequestsCount = $this->cancelOpenCustomerServiceRequests($user, $admin);
            $cancelledPendingBookingsCount += $this->cancelPendingCustomerBookings($user, $admin);
        }

        if ($user->hasRole('worker')) {
            $cancelledWorkerRequestsCount = $this->cancelPendingWorkerRequests($user);
            $cancelledPendingBookingsCount += $this->cancelPendingWorkerBookings($user, $admin);
        }

        return [
            'cancelled_service_requests_count' => $cancelledServiceRequestsCount,
            'cancelled_worker_requests_count' => $cancelledWorkerRequestsCount,
            'cancelled_pending_bookings_count' => $cancelledPendingBookingsCount,
        ];
    }

    /**
     * Cancel open customer-side service requests so partially blocked customers cannot keep booking.
     */
    private function cancelOpenCustomerServiceRequests(User $customer, ?User $admin): int
    {
        $cancelledCount = 0;

        $openServiceRequests = ServiceRequest::query()
            ->where('customer_id', $customer->id)
            ->where('status', ServiceRequest::STATUS_OPEN)
            ->get();

        foreach ($openServiceRequests as $serviceRequest) {
            $serviceRequest->update(['status' => ServiceRequest::STATUS_CANCELLED]);

            $serviceRequest->workers()
                ->whereIn('status', [
                    ServiceRequestWorker::STATUS_PENDING,
                    ServiceRequestWorker::STATUS_ACCEPTED,
                    ServiceRequestWorker::STATUS_AWAITING_RESCHEDULE,
                ])
                ->update([
                    'status' => ServiceRequestWorker::STATUS_CANCELLED,
                    'response_reason' => self::PARTIAL_BLOCK_REASON,
                    'responded_at' => now(),
                ]);

            $this->audit->record('admin.partially_blocked_service_request_cancelled', $admin, $serviceRequest, [
                'customer_id' => $customer->id,
            ]);

            $cancelledCount++;
        }

        return $cancelledCount;
    }

    /**
     * Cancel worker invitations that are still waiting for a reply.
     */
    private function cancelPendingWorkerRequests(User $worker): int
    {
        return ServiceRequestWorker::query()
            ->where('worker_id', $worker->id)
            ->where('status', ServiceRequestWorker::STATUS_PENDING)
            ->update([
                'status' => ServiceRequestWorker::STATUS_CANCELLED,
                'response_reason' => self::PARTIAL_BLOCK_REASON,
                'responded_at' => now(),
            ]);
    }

    /**
     * Cancel customer bookings that are still in the early pending request stage.
     */
    private function cancelPendingCustomerBookings(User $customer, ?User $admin): int
    {
        $pendingBookings = Booking::query()
            ->with(['customer', 'service', 'payments'])
            ->where('customer_id', $customer->id)
            ->whereIn('status', [
                Booking::STATUS_PENDING,
                Booking::STATUS_REQUESTED,
            ])
            ->get();

        return $this->cancelPendingBookings($pendingBookings, $admin);
    }

    /**
     * Cancel worker bookings that have not advanced beyond the pending request stage.
     */
    private function cancelPendingWorkerBookings(User $worker, ?User $admin): int
    {
        $pendingBookings = $worker->workerBookings()
            ->with(['customer', 'service', 'payments'])
            ->whereIn('status', [
                Booking::STATUS_PENDING,
                Booking::STATUS_REQUESTED,
            ])
            ->get();

        return $this->cancelPendingBookings($pendingBookings, $admin);
    }

    /**
     * Cancel the provided pending bookings with a clear partial block reason.
     *
     * @param  iterable<int, Booking>  $pendingBookings
     */
    private function cancelPendingBookings(iterable $pendingBookings, ?User $admin): int
    {
        $cancelledCount = 0;

        foreach ($pendingBookings as $booking) {
            $this->cancelBlockedWorkerBooking($booking, $admin, self::PARTIAL_BLOCK_REASON);
            $cancelledCount++;
        }

        return $cancelledCount;
    }

    /**
     * Cancel future worker bookings after a full block so customers can be protected.
     *
     * @return array{cancelled_count: int, refund_review_count: int}
     */
    private function cancelFutureWorkerBookings(User $worker, ?User $admin): array
    {
        $cancelledCount = 0;
        $refundReviewCount = 0;

        $futureBookings = $worker->workerBookings()
            ->with(['customer', 'service', 'payments'])
            ->whereIn('status', [
                Booking::STATUS_PENDING,
                Booking::STATUS_REQUESTED,
                Booking::STATUS_ACCEPTED,
                Booking::STATUS_CONFIRMED,
            ])
            ->oldest('booking_date')
            ->get();

        foreach ($futureBookings as $booking) {
            $refundReviewCount += $this->cancelBlockedWorkerBooking($booking, $admin, self::FULL_BLOCK_REASON);
            $cancelledCount++;
        }

        return [
            'cancelled_count' => $cancelledCount,
            'refund_review_count' => $refundReviewCount,
        ];
    }

    /**
     * Cancel one booking and return one when a refund review is required.
     */
    private function cancelBlockedWorkerBooking(Booking $booking, ?User $admin, string $cancelledReason): int
    {
        $oldStatus = $booking->status;
        $needsRefundReview = $this->bookingNeedsRefundReview($booking);

        $bookingUpdates = [
            'status' => Booking::STATUS_CANCELLED,
            'cancelled_by' => $admin?->id,
            'cancelled_reason' => $cancelledReason,
        ];

        // Paid bookings stay out of refunded status until finance has checked the money movement.
        if ($needsRefundReview) {
            $bookingUpdates['payment_status'] = Booking::PAYMENT_REFUND_REVIEW;
        }

        $booking->update($bookingUpdates);

        $this->recordBlockedWorkerBookingCancellation($booking, $oldStatus, $admin, $needsRefundReview, $cancelledReason);
        $this->notifyCustomerAboutBlockedWorkerCancellation($booking, $cancelledReason);

        return $needsRefundReview ? 1 : 0;
    }

    /**
     * Check whether a cancelled booking has money captured and needs finance review.
     */
    private function bookingNeedsRefundReview(Booking $booking): bool
    {
        if ($booking->payment_status === Booking::PAYMENT_PAID) {
            return true;
        }

        return $booking->payments->contains(function (Payment $payment): bool {
            return $payment->status === Payment::STATUS_PAID;
        });
    }

    /**
     * Store both the booking timeline row and the admin audit row for compliance review.
     */
    private function recordBlockedWorkerBookingCancellation(Booking $booking, string $oldStatus, ?User $admin, bool $needsRefundReview, string $cancelledReason): void
    {
        $booking->activities()->create([
            'actor_id' => $admin?->id,
            'from_status' => $oldStatus,
            'to_status' => Booking::STATUS_CANCELLED,
            'event' => 'worker_blocked_booking_cancelled',
            'note' => $cancelledReason,
        ]);

        $this->audit->record('admin.worker_blocked_booking_cancelled', $admin, $booking, [
            'worker_id' => $booking->worker_id,
            'customer_id' => $booking->customer_id,
            'refund_review_required' => $needsRefundReview,
        ]);
    }

    /**
     * Notify the customer that the platform cancelled the booking after a worker restriction.
     */
    private function notifyCustomerAboutBlockedWorkerCancellation(Booking $booking, string $cancelledReason): void
    {
        if ($booking->customer === null) {
            return;
        }

        $message = $cancelledReason === self::FULL_BLOCK_REASON
            ? 'Your booking was cancelled because the assigned worker is no longer available on the platform.'
            : 'Your booking was cancelled because the assigned worker is temporarily restricted from taking new work.';

        $booking->customer->notify(new BookingWorkflowNotification(
            booking: $booking->refresh()->loadMissing('service'),
            event: 'worker_blocked_booking_cancelled',
            title: 'Booking cancelled',
            message: $message,
        ));
    }

    /**
     * Return the authenticated admin when present.
     */
    private function currentAdmin(): ?User
    {
        $admin = request()->user();

        if (! $admin instanceof User) {
            return null;
        }

        return $admin;
    }

    /**
     * Mark a user as platform verified after the required checks are complete.
     */
    public function verify(User $user): User
    {
        $this->ensureNotAdmin($user, 'Admin accounts cannot be verified from user management.');
        $this->ensureEmailVerified($user);
        $this->ensureWorkerVerificationApproved($user);

        $user->update(['is_verified' => true]);

        if ($user->hasRole('worker')) {
            $user->workerProfile()->updateOrCreate(['user_id' => $user->id], [
                'is_verified' => true,
            ]);
        }

        $this->audit->record('admin.user_verified', request()->user(), $user);

        return $user->refresh()->load(['role', 'customerProfile', 'workerProfile', 'workerVerification']);
    }

    /**
     * Delete a non-admin user from the platform.
     *
     * @throws ValidationException
     */
    public function delete(User $user, User $admin): void
    {
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
     * Stop admin accounts from entering customer and worker moderation flows.
     *
     * @throws ValidationException
     */
    private function ensureNotAdmin(User $user, string $message): void
    {
        if ($user->loadMissing('role')->hasRole('admin')) {
            throw ValidationException::withMessages([
                'user' => [$message],
            ]);
        }
    }

    /**
     * Ensure the user has an active email verification before admin approval.
     */
    private function ensureEmailVerified(User $user): void
    {
        if ($user->hasVerifiedEmail()) {
            return;
        }

        throw ValidationException::withMessages([
            'email' => ['User must verify their email before admin approval.'],
        ]);
    }

    /**
     * Ensure a worker has an approved verification record before admin approval.
     */
    private function ensureWorkerVerificationApproved(User $user): void
    {
        if (! $user->loadMissing('role')->hasRole('worker')) {
            return;
        }

        $isApproved = $user->workerVerification()
            ->where('status', WorkerVerification::STATUS_APPROVED)
            ->exists();

        if ($isApproved) {
            return;
        }

        throw ValidationException::withMessages([
            'worker_verification' => ['Approve this worker ID proof before approving the user account.'],
        ]);
    }
}
