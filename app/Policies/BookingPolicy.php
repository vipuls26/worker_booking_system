<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;

class BookingPolicy
{
    public function before(User $user): ?bool
    {
        return $user->hasRole('admin') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasRole('customer') || $user->hasRole('worker');
    }

    public function view(User $user, Booking $booking): bool
    {
        return $booking->customer_id === $user->id || $booking->worker_id === $user->id;
    }

    public function pay(User $user, Booking $booking): bool
    {
        // Only the customer can pay, and only while the booking is still unpaid.
        return $booking->customer_id === $user->id && $booking->payment_status !== Booking::PAYMENT_PAID;
    }

    public function cancel(User $user, Booking $booking): bool
    {
        // Customers may cancel only before the booking reaches a terminal workflow status.
        return $booking->customer_id === $user->id && in_array($booking->status, [Booking::STATUS_CONFIRMED, Booking::STATUS_PENDING], true);
    }

    public function bookAgain(User $user, Booking $booking): bool
    {
        // Rebooking is available only to the customer after the original work is complete.
        return $booking->customer_id === $user->id && $booking->status === Booking::STATUS_COMPLETED;
    }

    public function updateStatus(User $user, Booking $booking): bool
    {
        return $booking->worker_id === $user->id;
    }

    public function dispute(User $user, Booking $booking): bool
    {
        // Disputes are reserved for involved parties on bookings that were not rejected or cancelled.
        return $this->view($user, $booking) && ! in_array($booking->status, [Booking::STATUS_CANCELLED, Booking::STATUS_REJECTED], true);
    }
}
