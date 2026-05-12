<?php

namespace App\Services\Admin;

use App\Models\UnblockRequest;
use App\Models\User;
use App\Notifications\UnblockRequestReviewedNotification;
use App\Services\Audit\AuditLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UnblockRequestManagementService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function paginate(Request $request): LengthAwarePaginator
    {
        // Admins need unblock requests with user and reviewer context for account safety decisions.
        return UnblockRequest::query()
            ->with(['user.role', 'reviewer.role'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->latest()
            ->paginate($request->integer('per_page', 15));
    }

    public function approve(UnblockRequest $unblockRequest, User $admin, ?string $note = null): UnblockRequest
    {
        return $this->review($unblockRequest, $admin, UnblockRequest::STATUS_APPROVED, $note);
    }

    public function reject(UnblockRequest $unblockRequest, User $admin, ?string $note = null): UnblockRequest
    {
        return $this->review($unblockRequest, $admin, UnblockRequest::STATUS_REJECTED, $note);
    }

    private function review(UnblockRequest $unblockRequest, User $admin, string $status, ?string $note): UnblockRequest
    {
        return DB::transaction(function () use ($unblockRequest, $admin, $status, $note): UnblockRequest {
            // Lock the request so two admins cannot review the same unblock appeal at once.
            $lockedUnblockRequest = UnblockRequest::query()
                ->whereKey($unblockRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Each unblock request should receive exactly one admin decision.
            if ($lockedUnblockRequest->status !== UnblockRequest::STATUS_PENDING) {
                throw ValidationException::withMessages([
                    'request' => ['This unblock request has already been reviewed.'],
                ]);
            }

            // Lock the blocked user so approval and account access changes commit together.
            $blockedUser = User::query()
                ->whereKey($lockedUnblockRequest->user_id)
                ->lockForUpdate()
                ->firstOrFail();

            // Store the decision details so the user and audit log can explain the account outcome.
            $lockedUnblockRequest->update([
                'status' => $status,
                'admin_note' => $note,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ]);

            // Partial blocks return straight to active, while full blocks move into a reverification-only restricted state.
            if ($status === UnblockRequest::STATUS_APPROVED) {
                if ($blockedUser->isPartiallyBlocked()) {
                    $blockedUser->update([
                        'account_status' => User::STATUS_ACTIVE,
                        'is_blocked' => false,
                    ]);
                } elseif ($blockedUser->isFullyBlocked()) {
                    $blockedUser->update([
                        'account_status' => User::STATUS_PARTIALLY_BLOCKED,
                        'is_blocked' => false,
                    ]);
                }
            }

            $this->audit->record('admin.unblock_request_'.$status, $admin, $lockedUnblockRequest, [
                'user_id' => $lockedUnblockRequest->user_id,
                'note' => $note,
                'account_status' => $blockedUser->account_status,
            ]);

            $reviewedRequest = $lockedUnblockRequest->refresh()->load(['user.role', 'reviewer.role']);

            // Users need a durable notification for both approved and rejected unblock decisions.
            $blockedUser->refresh()->notify(new UnblockRequestReviewedNotification($reviewedRequest));

            return $reviewedRequest;
        });
    }
}
