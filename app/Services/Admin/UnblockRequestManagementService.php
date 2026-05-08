<?php

namespace App\Services\Admin;

use App\Models\UnblockRequest;
use App\Models\User;
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
        if ($unblockRequest->status !== UnblockRequest::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'request' => ['This unblock request has already been reviewed.'],
            ]);
        }

        return DB::transaction(function () use ($unblockRequest, $admin, $status, $note): UnblockRequest {
            $unblockRequest->update([
                'status' => $status,
                'admin_note' => $note,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ]);

            if ($status === UnblockRequest::STATUS_APPROVED) {
                $unblockRequest->user?->update(['is_blocked' => false]);
            }

            $this->audit->record('admin.unblock_request_'.$status, $admin, $unblockRequest, [
                'user_id' => $unblockRequest->user_id,
                'note' => $note,
            ]);

            return $unblockRequest->refresh()->load(['user.role', 'reviewer.role']);
        });
    }
}
