<?php

namespace App\Services\Admin;

use App\Models\User;
use App\Models\WorkerVerification;
use App\Services\Audit\AuditLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkerVerificationManagementService
{
    public function __construct(private readonly AuditLogger $audit) {}

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
        return DB::transaction(function () use ($verification, $admin): WorkerVerification {
            $verification->loadMissing('user.role');
            $worker = $verification->user;

            $verification->update([
                'status' => WorkerVerification::STATUS_APPROVED,
                'rejection_reason' => null,
                'verified_by' => $admin->id,
                'verified_at' => now(),
            ]);

            $worker
                ?->workerProfile()
                ->updateOrCreate(['user_id' => $verification->user_id], [
                    'experience_years' => $verification->experience_years,
                    'is_verified' => true,
                ]);

            $this->audit->record('admin.worker_verification_approved', $admin, $verification, [
                'worker_id' => $verification->user_id,
            ]);

            return $verification->refresh()->load(['user.role', 'verifier.role']);
        });
    }

    public function reject(WorkerVerification $verification, User $admin, string $reason): WorkerVerification
    {
        return $this->markNeedsChanges($verification, $admin, $reason, WorkerVerification::STATUS_REJECTED);
    }

    public function requestResubmission(WorkerVerification $verification, User $admin, string $reason): WorkerVerification
    {
        return $this->markNeedsChanges($verification, $admin, $reason, WorkerVerification::STATUS_RESUBMISSION_REQUESTED);
    }

    private function markNeedsChanges(WorkerVerification $verification, User $admin, string $reason, string $status): WorkerVerification
    {
        return DB::transaction(function () use ($verification, $admin, $reason, $status): WorkerVerification {
            $verification->loadMissing('user.role');
            $worker = $verification->user;

            $verification->update([
                'status' => $status,
                'rejection_reason' => $reason,
                'verified_by' => $admin->id,
                'verified_at' => now(),
            ]);

            $worker?->forceFill(['is_verified' => false])->save();

            $worker
                ?->workerProfile()
                ->updateOrCreate(['user_id' => $verification->user_id], [
                    'experience_years' => $verification->experience_years,
                    'is_verified' => false,
                ]);

            $this->audit->record('admin.worker_verification_'.$status, $admin, $verification, [
                'worker_id' => $verification->user_id,
                'reason' => $reason,
            ]);

            return $verification->refresh()->load(['user.role', 'verifier.role']);
        });
    }
}
