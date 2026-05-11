<?php

namespace App\Console\Commands;

use App\Services\ServiceRequest\ServiceRequestWorkflowService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('service-requests:cancel-expired {--hours=24 : Number of hours an open request may wait for a worker response}')]
#[Description('Cancel open service requests that received no worker response before the expiry window.')]
class CancelExpiredServiceRequests extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ServiceRequestWorkflowService $serviceRequests): int
    {
        $hoursOld = (int) $this->option('hours');

        // The business expiry window defaults to 24 hours, but the option helps focused admin re-runs.
        if ($hoursOld < 1) {
            $this->error('The expiry window must be at least 1 hour.');

            return self::FAILURE;
        }

        $expiredRequestsCount = $serviceRequests->cancelExpiredOpenRequests($hoursOld);

        $this->info(sprintf('Cancelled %d expired service request(s).', $expiredRequestsCount));

        return self::SUCCESS;
    }
}
