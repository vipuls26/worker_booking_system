<?php

namespace App\Services\Admin;

use App\Models\User;
use App\Models\WorkerService;
use App\Services\Audit\AuditLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkerServiceApprovalService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function paginate(Request $request): LengthAwarePaginator
    {
        return WorkerService::query()
            ->with(['worker:id,name,email,phone', 'service:id,name,slug,icon,is_active', 'reviewer:id,name'])
            ->when($request->filled('status'), fn ($query) => $query->where('approval_status', $request->string('status')->toString()))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('description', 'like', "%{$search}%")
                        ->orWhereHas('worker', fn ($query) => $query
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%"))
                        ->orWhereHas('service', fn ($query) => $query
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('slug', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->paginate($request->integer('per_page', 10));
    }

    public function approve(WorkerService $workerService, User $admin): WorkerService
    {
        return DB::transaction(function () use ($workerService, $admin): WorkerService {
            $workerService->update([
                'approval_status' => WorkerService::StatusApproved,
                'is_active' => true,
                'rejection_reason' => null,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ]);

            $this->audit->record('admin.worker_service_approved', $admin, $workerService, [
                'worker_id' => $workerService->worker_id,
                'service_id' => $workerService->service_id,
            ]);

            return $workerService->refresh()->load(['worker:id,name,email,phone', 'service:id,name,slug,icon,is_active', 'reviewer:id,name']);
        });
    }

    public function reject(WorkerService $workerService, User $admin, string $reason): WorkerService
    {
        return DB::transaction(function () use ($workerService, $admin, $reason): WorkerService {
            $workerService->update([
                'approval_status' => WorkerService::StatusRejected,
                'is_active' => false,
                'rejection_reason' => $reason,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
            ]);

            $this->audit->record('admin.worker_service_rejected', $admin, $workerService, [
                'worker_id' => $workerService->worker_id,
                'service_id' => $workerService->service_id,
                'reason' => $reason,
            ]);

            return $workerService->refresh()->load(['worker:id,name,email,phone', 'service:id,name,slug,icon,is_active', 'reviewer:id,name']);
        });
    }
}
