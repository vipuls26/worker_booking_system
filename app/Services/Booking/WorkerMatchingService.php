<?php

namespace App\Services\Booking;

use App\Models\WorkerService;
use Illuminate\Support\Collection;

class WorkerMatchingService
{
    public function __construct(private readonly AvailabilityService $availability) {}

    /**
     * @param  array<string, mixed>  $criteria
     * @return Collection<int, WorkerService>
     */
    public function matchingWorkerServices(array $criteria, int $limit = 10): Collection
    {
        $durationMinutes = (int) ($criteria['duration_minutes'] ?? 60);

        return WorkerService::query()
            ->with(['service', 'worker.role', 'worker.workerProfile'])
            ->where('service_id', $criteria['service_id'])
            ->when(
                ! empty($criteria['worker_id']),
                fn ($query) => $query->where('worker_id', (int) $criteria['worker_id']),
            )
            ->where('is_active', true)
            ->where('approval_status', WorkerService::StatusApproved)
            ->whereHas('service', fn ($query) => $query->where('is_active', true))
            ->whereHas('worker', function ($query): void {
                $query
                    ->where('is_blocked', false)
                    ->where('is_verified', true)
                    ->whereNotNull('email_verified_at')
                    ->whereHas('role', fn ($roleQuery) => $roleQuery->where('slug', 'worker'))
                    ->whereHas('workerProfile', fn ($profileQuery) => $profileQuery->where('is_verified', true));
            })
            ->orderBy('price')
            ->limit($limit * 5)
            ->get()
            ->filter(fn (WorkerService $workerService): bool => $workerService->worker !== null
                && $this->availability->isWorkerAvailable(
                    worker: $workerService->worker,
                    date: (string) $criteria['booking_date'],
                    startTime: (string) $criteria['start_time'],
                    durationMinutes: $durationMinutes,
                ))
            ->take($limit)
            ->values();
    }
}
