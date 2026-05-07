<?php

namespace App\Services\Admin;

use App\Models\User;
use App\Models\WorkerVerification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class WorkerVerificationManagementService
{
    public function paginate(Request $request): LengthAwarePaginator
    {
        return WorkerVerification::query()
            ->with(['user.role', 'verifier.role'])
            ->whereHas('user.role', fn ($query) => $query->where('slug', 'worker'))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->latest()
            ->paginate($request->integer('per_page', 15));
    }

    public function approve(WorkerVerification $verification, User $admin): WorkerVerification
    {
        $verification->update([
            'status' => WorkerVerification::STATUS_APPROVED,
            'rejection_reason' => null,
            'verified_by' => $admin->id,
            'verified_at' => now(),
        ]);

        return $verification->refresh()->load(['user.role', 'verifier.role']);
    }

    public function reject(WorkerVerification $verification, User $admin, string $reason): WorkerVerification
    {
        $verification->update([
            'status' => WorkerVerification::STATUS_REJECTED,
            'rejection_reason' => $reason,
            'verified_by' => $admin->id,
            'verified_at' => now(),
        ]);

        return $verification->refresh()->load(['user.role', 'verifier.role']);
    }
}
