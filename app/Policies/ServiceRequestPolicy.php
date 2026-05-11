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
        // Service request visibility belongs to the customer, selected worker, or invited workers.
        return $serviceRequest->customer_id === $user->id
            || $serviceRequest->selected_worker_id === $user->id
            || $serviceRequest->workers()->where('worker_id', $user->id)->exists();
    }

    public function create(User $user): bool
    {
        // Customers must be verified and active before sending work requests to providers.
        return $user->hasRole('customer') && $user->hasVerifiedEmail() && ! $user->is_blocked && $user->is_verified;
    }

    public function selectWorker(User $user, ServiceRequest $serviceRequest): bool
    {
        // The customer can choose a worker only while the request is still open.
        return $serviceRequest->customer_id === $user->id && $serviceRequest->status === ServiceRequest::STATUS_OPEN;
    }

    public function cancel(User $user, ServiceRequest $serviceRequest): bool
    {
        // Customers can cancel service requests only before worker selection.
        return $serviceRequest->customer_id === $user->id && $serviceRequest->status === ServiceRequest::STATUS_OPEN;
    }
}
