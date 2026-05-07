<?php

namespace App\Services\Account;

use App\Models\UnblockRequest;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class UnblockRequestService
{
    public function latestFor(User $user): ?UnblockRequest
    {
        return $user->unblockRequests()
            ->with(['user.role', 'reviewer.role'])
            ->latest()
            ->first();
    }

    /**
     * @param  array{reason: string}  $data
     */
    public function submit(User $user, array $data): UnblockRequest
    {
        if (! $user->is_blocked) {
            throw ValidationException::withMessages([
                'account' => ['Your account is not blocked.'],
            ]);
        }

        $pendingRequest = $user->unblockRequests()
            ->where('status', UnblockRequest::STATUS_PENDING)
            ->exists();

        if ($pendingRequest) {
            throw ValidationException::withMessages([
                'request' => ['You already have a pending unblock request.'],
            ]);
        }

        return $user->unblockRequests()
            ->create([
                'reason' => $data['reason'],
                'status' => UnblockRequest::STATUS_PENDING,
            ])
            ->load(['user.role', 'reviewer.role']);
    }
}
