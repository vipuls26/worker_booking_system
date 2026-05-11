<?php

namespace App\Services\Worker;

use App\Models\Service;
use App\Models\User;
use App\Models\WorkerService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class WorkerServiceManagementService
{
    public function paginate(User $worker, Request $request): LengthAwarePaginator
    {
        // Workers manage only their own service offerings and review outcomes.
        return $worker->workerServices()
            ->with(['service:id,name,slug,icon,is_active', 'reviewer:id,name'])
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->toString();

                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('description', 'like', "%{$search}%")
                        ->orWhereHas('service', function ($query) use ($search): void {
                            $query
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('slug', 'like', "%{$search}%");
                        });
                });
            })
            ->when($request->filled('pricing_type'), fn ($query) => $query->where('pricing_type', $request->string('pricing_type')->toString()))
            ->when($request->filled('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->when($request->filled('approval_status'), fn ($query) => $query->where('approval_status', $request->string('approval_status')->toString()))
            ->latest()
            ->paginate($request->integer('per_page', 10));
    }

    public function activeServiceOptions(): Collection
    {
        // Workers can create offerings only for active platform service categories.
        return Service::query()
            ->select(['id', 'name', 'slug', 'icon'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $worker, array $data): WorkerService
    {
        // New worker services require admin approval before customers can book them.
        $data['approval_status'] = WorkerService::StatusPending;
        $data['is_active'] = false;
        $data['rejection_reason'] = null;
        $data['reviewed_by'] = null;
        $data['reviewed_at'] = null;

        return $worker->workerServices()
            ->create($data)
            ->load(['service:id,name,slug,icon,is_active', 'reviewer:id,name']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(WorkerService $workerService, array $data): WorkerService
    {
        // Edited worker services return to pending review because pricing or scope may have changed.
        $data['approval_status'] = WorkerService::StatusPending;
        $data['is_active'] = false;
        $data['rejection_reason'] = null;
        $data['reviewed_by'] = null;
        $data['reviewed_at'] = null;

        $workerService->update($data);

        return $workerService->refresh()->load(['service:id,name,slug,icon,is_active', 'reviewer:id,name']);
    }

    public function delete(WorkerService $workerService): void
    {
        $workerService->delete();
    }
}
