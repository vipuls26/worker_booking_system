<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestWorker;
use App\Models\User;
use App\Models\WorkerService;
use App\Notifications\ServiceRequestWorkflowNotification;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ExpiredServiceRequestCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_cancels_open_service_requests_after_twenty_four_hours_without_worker_response(): void
    {
        Notification::fake();

        [$customer, $worker, $workerService] = $this->requestActors();
        $expiredRequest = ServiceRequest::factory()->create([
            'customer_id' => $customer->id,
            'service_id' => $workerService->service_id,
            'status' => ServiceRequest::STATUS_OPEN,
            'created_at' => now()->subHours(25),
            'updated_at' => now()->subHours(25),
        ]);
        $pendingInvitation = ServiceRequestWorker::factory()->create([
            'service_request_id' => $expiredRequest->id,
            'worker_id' => $worker->id,
            'worker_service_id' => $workerService->id,
            'status' => ServiceRequestWorker::STATUS_PENDING,
            'responded_at' => null,
        ]);

        $this->artisan('service-requests:cancel-expired')
            ->expectsOutput('Cancelled 1 expired service request(s).')
            ->assertSuccessful();

        $this->assertDatabaseHas('service_requests', [
            'id' => $expiredRequest->id,
            'status' => ServiceRequest::STATUS_CANCELLED,
        ]);

        $this->assertDatabaseHas('service_request_workers', [
            'id' => $pendingInvitation->id,
            'status' => ServiceRequestWorker::STATUS_EXPIRED,
        ]);

        Notification::assertSentTo(
            $customer,
            ServiceRequestWorkflowNotification::class,
            function (ServiceRequestWorkflowNotification $notification) use ($customer): bool {
                return $notification->toArray($customer)['event'] === 'service_request_expired';
            }
        );
    }

    public function test_command_keeps_requests_open_when_a_worker_already_responded(): void
    {
        Notification::fake();

        [$customer, $worker, $workerService] = $this->requestActors();
        $serviceRequest = ServiceRequest::factory()->create([
            'customer_id' => $customer->id,
            'service_id' => $workerService->service_id,
            'status' => ServiceRequest::STATUS_OPEN,
            'created_at' => now()->subHours(25),
            'updated_at' => now()->subHours(25),
        ]);
        $acceptedInvitation = ServiceRequestWorker::factory()->create([
            'service_request_id' => $serviceRequest->id,
            'worker_id' => $worker->id,
            'worker_service_id' => $workerService->id,
            'status' => ServiceRequestWorker::STATUS_ACCEPTED,
            'responded_at' => now()->subHours(2),
        ]);

        $this->artisan('service-requests:cancel-expired')
            ->expectsOutput('Cancelled 0 expired service request(s).')
            ->assertSuccessful();

        $this->assertDatabaseHas('service_requests', [
            'id' => $serviceRequest->id,
            'status' => ServiceRequest::STATUS_OPEN,
        ]);

        $this->assertDatabaseHas('service_request_workers', [
            'id' => $acceptedInvitation->id,
            'status' => ServiceRequestWorker::STATUS_ACCEPTED,
        ]);

        Notification::assertNothingSent();
    }

    public function test_command_keeps_recent_requests_open_until_twenty_four_hours_pass(): void
    {
        [$customer, $worker, $workerService] = $this->requestActors();
        $recentRequest = ServiceRequest::factory()->create([
            'customer_id' => $customer->id,
            'service_id' => $workerService->service_id,
            'status' => ServiceRequest::STATUS_OPEN,
            'created_at' => now()->subHours(23),
            'updated_at' => now()->subHours(23),
        ]);
        $pendingInvitation = ServiceRequestWorker::factory()->create([
            'service_request_id' => $recentRequest->id,
            'worker_id' => $worker->id,
            'worker_service_id' => $workerService->id,
            'status' => ServiceRequestWorker::STATUS_PENDING,
            'responded_at' => null,
        ]);

        $this->artisan('service-requests:cancel-expired')
            ->expectsOutput('Cancelled 0 expired service request(s).')
            ->assertSuccessful();

        $this->assertDatabaseHas('service_requests', [
            'id' => $recentRequest->id,
            'status' => ServiceRequest::STATUS_OPEN,
        ]);

        $this->assertDatabaseHas('service_request_workers', [
            'id' => $pendingInvitation->id,
            'status' => ServiceRequestWorker::STATUS_PENDING,
        ]);
    }

    /**
     * Create a customer, worker, and approved service offering for expiry tests.
     *
     * @return array{User, User, WorkerService}
     */
    private function requestActors(): array
    {
        $this->seed(RoleSeeder::class);

        $customer = User::factory()
            ->for(Role::where('slug', 'customer')->firstOrFail())
            ->create();
        $worker = User::factory()
            ->for(Role::where('slug', 'worker')->firstOrFail())
            ->create();
        $service = Service::factory()->create(['is_active' => true]);
        $workerService = WorkerService::factory()->create([
            'worker_id' => $worker->id,
            'service_id' => $service->id,
            'approval_status' => WorkerService::StatusApproved,
        ]);

        return [$customer, $worker, $workerService];
    }
}
