<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkerService;

class WorkerServicePolicy
{
    public function before(User $user): ?bool
    {
        return $user->hasRole('admin') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasRole('worker');
    }

    public function view(User $user, WorkerService $workerService): bool
    {
        return $workerService->worker_id === $user->id;
    }

    public function create(User $user): bool
    {
        // Workers must be verified and active before offering services in the marketplace.
        return $user->hasRole('worker') && $user->hasVerifiedEmail() && ! $user->is_blocked && $user->is_verified;
    }

    public function update(User $user, WorkerService $workerService): bool
    {
        return $this->view($user, $workerService);
    }

    public function delete(User $user, WorkerService $workerService): bool
    {
        return $this->view($user, $workerService);
    }
}
