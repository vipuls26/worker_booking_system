<?php

namespace App\Services\ServiceRequest;

use App\Models\ServiceRequest;
use App\Models\ServiceRequestWorker;
use App\Notifications\ServiceRequestWorkflowNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ServiceRequestWorkflowService
{
    /**
     * Cancel open service requests that waited too long for worker action.
     */
    public function cancelExpiredOpenRequests(int $hoursOld = 24): int
    {
        $expiredRequestsCount = 0;

        // Old open requests should leave the customer queue instead of waiting forever.
        $expiredServiceRequests = ServiceRequest::query()
            ->with(['customer', 'service', 'workers'])
            ->where('status', ServiceRequest::STATUS_OPEN)
            ->where('created_at', '<=', now()->subHours($hoursOld))
            ->whereDoesntHave('workers', function ($query): void {
                $query->where('status', '!=', ServiceRequestWorker::STATUS_PENDING);
            })
            ->get();

        foreach ($expiredServiceRequests as $serviceRequest) {
            if ($this->cancelExpiredRequest($serviceRequest)) {
                $expiredRequestsCount++;
            }
        }

        return $expiredRequestsCount;
    }

    /**
     * Cancel one expired request, expire worker invitations, and notify the customer.
     */
    private function cancelExpiredRequest(ServiceRequest $serviceRequest): bool
    {
        return DB::transaction(function () use ($serviceRequest): bool {
            // Reload the request inside the transaction so concurrent worker responses do not race the expiry job.
            $lockedServiceRequest = ServiceRequest::query()
                ->with(['customer', 'service'])
                ->lockForUpdate()
                ->findOrFail($serviceRequest->id);

            // Only still-open requests can be expired by the scheduled job.
            if ($lockedServiceRequest->status !== ServiceRequest::STATUS_OPEN) {
                return false;
            }

            // Any worker response means this request needs normal customer or worker workflow, not expiry.
            if ($lockedServiceRequest->workers()->where('status', '!=', ServiceRequestWorker::STATUS_PENDING)->exists()) {
                return false;
            }

            $lockedServiceRequest->update([
                'status' => ServiceRequest::STATUS_CANCELLED,
            ]);

            // Pending invitations should no longer appear in worker queues after the request expires.
            $this->expirePendingWorkers($lockedServiceRequest->workers()->get());

            $this->notifyCustomerAboutExpiredRequest($lockedServiceRequest);

            return true;
        });
    }

    /**
     * Mark worker invitations as expired so workers know the request timed out.
     *
     * @param  Collection<int, ServiceRequestWorker>  $serviceRequestWorkers
     */
    private function expirePendingWorkers(Collection $serviceRequestWorkers): void
    {
        foreach ($serviceRequestWorkers as $serviceRequestWorker) {
            // Only pending invitations are timed out; completed responses keep their historical status.
            if ($serviceRequestWorker->status !== ServiceRequestWorker::STATUS_PENDING) {
                continue;
            }

            $serviceRequestWorker->update([
                'status' => ServiceRequestWorker::STATUS_EXPIRED,
                'responded_at' => now(),
            ]);
        }
    }

    /**
     * Tell the customer that no worker responded before the 24-hour expiry window closed.
     */
    private function notifyCustomerAboutExpiredRequest(ServiceRequest $serviceRequest): void
    {
        // Deleted customer accounts cannot receive database notifications.
        if ($serviceRequest->customer === null) {
            return;
        }

        $serviceRequest->customer->notify(new ServiceRequestWorkflowNotification(
            serviceRequest: $serviceRequest->refresh()->loadMissing('service'),
            event: 'service_request_expired',
            title: 'Service request expired',
            message: 'Your service request was cancelled because no worker responded within 24 hours.',
        ));
    }
}
