<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\Review;
use App\Models\User;

class ReviewPolicy
{
    public function before(User $user): ?bool
    {
        return $user->hasRole('admin') ? true : null;
    }

    public function view(User $user, Review $review): bool
    {
        return $review->customer_id === $user->id || $review->worker_id === $user->id;
    }

    public function createForWorker(User $user, Booking $booking): bool
    {
        return $user->hasRole('customer')
            && $booking->customer_id === $user->id
            && $booking->status === Booking::STATUS_COMPLETED;
    }

    public function createForCustomer(User $user, Booking $booking): bool
    {
        return $user->hasRole('worker')
            && $booking->worker_id === $user->id
            && $booking->status === Booking::STATUS_COMPLETED;
    }
}
