<?php

namespace App\Services\Account;

use App\Models\UnblockRequest;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class UnblockRequestService
{
    public function latestFor(User $user): ?UnblockRequest
    {
        // Account pages show the latest unblock request and reviewer context for status messaging.
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
        // Only blocked users should enter the unblock review workflow.
        if (! $user->is_blocked) {
            throw ValidationException::withMessages([
                'account' => ['Your account is not blocked.'],
            ]);
        }

        // Users should not create duplicate pending requests while admins are reviewing one.
        $pendingRequest = $user->unblockRequests()
            ->where('status', UnblockRequest::STATUS_PENDING)
            ->exists();

        // A pending request already represents the user's current appeal.
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
