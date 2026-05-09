<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\Dispute;
use App\Models\User;

class DisputePolicy
{
    public function before(User $user): ?bool
    {
        return $user->hasRole('admin') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasRole('customer') || $user->hasRole('worker');
    }

    public function view(User $user, Dispute $dispute): bool
    {
        return $dispute->opened_by === $user->id
            || $dispute->against_user_id === $user->id
            || $dispute->booking?->customer_id === $user->id
            || $dispute->booking?->worker_id === $user->id;
    }

    public function create(User $user, Booking $booking): bool
    {
        return ($booking->customer_id === $user->id || $booking->worker_id === $user->id)
            && $booking->customer_id !== null
            && $booking->worker_id !== null
            && ! in_array($booking->status, [Booking::STATUS_PENDING, Booking::STATUS_REQUESTED, Booking::STATUS_REJECTED, Booking::STATUS_CANCELLED], true);
    }

    public function resolve(User $user, Dispute $dispute): bool
    {
        return $user->hasRole('admin') && ! in_array($dispute->status, [Dispute::STATUS_RESOLVED, Dispute::STATUS_REJECTED], true);
    }
}
