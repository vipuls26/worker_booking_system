<?php

namespace App\Services\Account;

use App\Models\UnblockRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
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
        return DB::transaction(function () use ($user, $data): UnblockRequest {
            // Lock the user so duplicate unblock submissions for the same account cannot race.
            $lockedUser = User::query()
                ->whereKey($user->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Only blocked users should enter the unblock review workflow.
            if (! $lockedUser->is_blocked) {
                throw ValidationException::withMessages([
                    'account' => ['Your account is not blocked.'],
                ]);
            }

            // Users should not create duplicate pending requests while admins are reviewing one.
            $hasPendingRequest = $lockedUser->unblockRequests()
                ->where('status', UnblockRequest::STATUS_PENDING)
                ->exists();

            // A pending request already represents the user's current appeal.
            if ($hasPendingRequest) {
                throw ValidationException::withMessages([
                    'request' => ['You already have a pending unblock request.'],
                ]);
            }

            return $lockedUser->unblockRequests()
                ->create([
                    'reason' => $data['reason'],
                    'status' => UnblockRequest::STATUS_PENDING,
                ])
                ->load(['user.role', 'reviewer.role']);
        });
    }
}
