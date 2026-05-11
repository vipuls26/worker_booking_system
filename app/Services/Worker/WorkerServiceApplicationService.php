<?php

namespace App\Services\Worker;

use App\Models\User;
use App\Models\WorkerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkerServiceApplicationService
{
    /**
     * Submit a worker service application and reuse a rejected application when the worker reapplies.
     *
     * The business needs one row per worker and service so admin review history remains easy to inspect
     * while rejected workers can correct their offering and submit it again.
     *
     * @param  array<string, mixed>  $applicationData
     */
    public function submit(User $worker, array $applicationData): WorkerService
    {
        return DB::transaction(function () use ($worker, $applicationData): WorkerService {
            // Retrieve the current application so rejected rows can be safely reused instead of duplicated.
            $existingApplication = WorkerService::query()
                ->where('worker_id', $worker->id)
                ->where('service_id', $applicationData['service_id'])
                ->lockForUpdate()
                ->first();

            // A rejected application is reopened because the worker is correcting a previous denial.
            if ($existingApplication instanceof WorkerService) {
                // Active review records cannot be overwritten by a duplicate application.
                if ($existingApplication->approval_status !== WorkerService::StatusRejected) {
                    throw ValidationException::withMessages([
                        'service_id' => 'This service has already been submitted and is not eligible for reapplication.',
                    ]);
                }

                return $this->resubmitRejectedApplication($existingApplication, $applicationData);
            }

            return $this->createApplication($worker, $applicationData);
        });
    }

    /**
     * Create the first application for a worker and service.
     *
     * New applications are hidden from customer matching until an admin approves them.
     *
     * @param  array<string, mixed>  $applicationData
     */
    private function createApplication(User $worker, array $applicationData): WorkerService
    {
        $applicationData = $this->prepareForReview($applicationData);

        // Store the new application under the worker who is requesting marketplace approval.
        return $worker->workerServices()
            ->create($applicationData)
            ->load(['service:id,name,slug,icon,is_active', 'reviewer:id,name']);
    }

    /**
     * Update a rejected application so the worker can reapply without violating the unique constraint.
     *
     * Validation prevents non-rejected records from reaching this branch, so this method keeps the write path
     * focused on the allowed resubmission case.
     *
     * @param  array<string, mixed>  $applicationData
     */
    private function resubmitRejectedApplication(WorkerService $workerService, array $applicationData): WorkerService
    {
        $applicationData = $this->prepareForReview($applicationData);

        // Reuse the rejected row so the worker and service pair stays unique while returning to admin review.
        $workerService->update($applicationData);

        return $workerService->refresh()->load(['service:id,name,slug,icon,is_active', 'reviewer:id,name']);
    }

    /**
     * Normalize application fields for admin review.
     *
     * Reapplied and first-time applications share these review defaults so rejected metadata is cleared.
     *
     * @param  array<string, mixed>  $applicationData
     * @return array<string, mixed>
     */
    private function prepareForReview(array $applicationData): array
    {
        $applicationData['approval_status'] = WorkerService::StatusPending;
        $applicationData['is_active'] = false;
        $applicationData['rejection_reason'] = null;
        $applicationData['reviewed_by'] = null;
        $applicationData['reviewed_at'] = null;

        return $applicationData;
    }
}
