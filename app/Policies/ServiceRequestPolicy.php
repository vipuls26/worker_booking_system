<?php

namespace App\Policies;

use App\Models\ServiceRequest;
use App\Models\User;

class ServiceRequestPolicy
{
    public function before(User $user): ?bool
    {
        return $user->hasRole('admin') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasRole('customer') || $user->hasRole('worker');
    }

    public function view(User $user, ServiceRequest $serviceRequest): bool
    {
        return $serviceRequest->customer_id === $user->id
            || $serviceRequest->selected_worker_id === $user->id
            || $serviceRequest->workers()->where('worker_id', $user->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->hasRole('customer') && $user->hasVerifiedEmail() && ! $user->is_blocked && $user->is_verified;
    }

    public function selectWorker(User $user, ServiceRequest $serviceRequest): bool
    {
        return $serviceRequest->customer_id === $user->id && $serviceRequest->status === ServiceRequest::STATUS_OPEN;
    }

    public function cancel(User $user, ServiceRequest $serviceRequest): bool
    {
        return $serviceRequest->customer_id === $user->id && $serviceRequest->status === ServiceRequest::STATUS_OPEN;
    }
}
