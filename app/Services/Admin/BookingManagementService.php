<?php

namespace App\Services\Admin;

use App\Models\Booking;
use App\Models\User;
use App\Notifications\BookingWorkflowNotification;
use App\Services\Booking\BookingWorkflowService;
use App\Support\Filters\BookingFilter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class BookingManagementService
{
    public function __construct(
        private readonly BookingFilter $filter,
        private readonly BookingWorkflowService $workflow,
    ) {}

    public function paginate(Request $request): LengthAwarePaginator
    {
        $query = Booking::query()
            ->with(['customer.role', 'worker.role', 'selectedWorker.role', 'service', 'cancelledBy.role', 'activities.actor.role', 'review.customer.role'])
            ->latest();

        return $this->filter
            ->apply($query, $request)
            ->paginate($request->integer('per_page', 15));
    }

    public function cancel(Booking $booking, User $admin, string $reason): Booking
    {
        $booking = $this->workflow
            ->transition($booking, Booking::STATUS_CANCELLED, $admin, $reason, ['event' => 'admin_cancelled_booking'])
            ->load(['customer.role', 'worker.role', 'selectedWorker.role', 'service', 'cancelledBy.role', 'activities.actor.role', 'review.customer.role']);

        $booking->customer?->notify(new BookingWorkflowNotification(
            booking: $booking,
            event: 'booking_cancelled',
            title: 'Booking cancelled',
            message: 'Your booking was cancelled by admin.',
        ));

        $booking->worker?->notify(new BookingWorkflowNotification(
            booking: $booking,
            event: 'booking_cancelled',
            title: 'Booking cancelled',
            message: 'A booking assigned to you was cancelled by admin.',
        ));

        return $booking;
    }
}
