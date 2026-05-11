<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use App\Models\WorkerService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkerServicesTest extends TestCase
{
    use RefreshDatabase;

    public function test_worker_can_create_fixed_price_service(): void
    {
        Sanctum::actingAs($worker = $this->workerUser());

        $service = Service::factory()->create([
            'name' => 'AC Repair',
            'slug' => 'ac-repair',
            'is_active' => true,
        ]);

        $this->postJson('/api/worker/services', [
            'service_id' => $service->id,
            'pricing_type' => WorkerService::PricingFixed,
            'price' => 500,
            'minimum_hours' => 2,
            'description' => 'Fixed AC service visit.',
            'is_active' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.worker_service.worker_id', $worker->id)
            ->assertJsonPath('data.worker_service.pricing_type', WorkerService::PricingFixed)
            ->assertJsonPath('data.worker_service.minimum_hours', null);

        $this->assertDatabaseHas('worker_services', [
            'worker_id' => $worker->id,
            'service_id' => $service->id,
            'pricing_type' => WorkerService::PricingFixed,
            'price' => 500,
            'minimum_hours' => null,
        ]);
    }

    public function test_worker_can_reapply_after_service_rejection(): void
    {
        Sanctum::actingAs($worker = $this->workerUser());

        $service = Service::factory()->create([
            'name' => 'Deep Cleaning',
            'slug' => 'deep-cleaning',
            'is_active' => true,
        ]);

        $workerService = WorkerService::factory()->rejected()->create([
            'worker_id' => $worker->id,
            'service_id' => $service->id,
            'pricing_type' => WorkerService::PricingFixed,
            'price' => 700,
            'minimum_hours' => null,
            'description' => 'Initial rejected offer.',
            'is_active' => false,
            'rejection_reason' => 'Pricing needs more detail.',
            'reviewed_at' => now(),
        ]);

        $this->postJson('/api/worker/services', [
            'service_id' => $service->id,
            'pricing_type' => WorkerService::PricingHourly,
            'price' => 450,
            'minimum_hours' => 2,
            'description' => 'Updated deep cleaning offer.',
            'is_active' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.worker_service.id', $workerService->id)
            ->assertJsonPath('data.worker_service.approval_status', WorkerService::StatusPending)
            ->assertJsonPath('data.worker_service.is_active', false)
            ->assertJsonPath('data.worker_service.rejection_reason', null);

        $this->assertDatabaseCount('worker_services', 1);
        $this->assertDatabaseHas('worker_services', [
            'id' => $workerService->id,
            'worker_id' => $worker->id,
            'service_id' => $service->id,
            'pricing_type' => WorkerService::PricingHourly,
            'price' => 450,
            'minimum_hours' => 2,
            'description' => 'Updated deep cleaning offer.',
            'is_active' => false,
            'approval_status' => WorkerService::StatusPending,
            'rejection_reason' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ]);
    }

    public function test_worker_cannot_reapply_when_service_is_not_rejected(): void
    {
        Sanctum::actingAs($worker = $this->workerUser());

        $service = Service::factory()->create(['is_active' => true]);

        WorkerService::factory()->pending()->create([
            'worker_id' => $worker->id,
            'service_id' => $service->id,
        ]);

        $this->postJson('/api/worker/services', [
            'service_id' => $service->id,
            'pricing_type' => WorkerService::PricingFixed,
            'price' => 500,
            'description' => 'Duplicate pending offer.',
            'is_active' => true,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors('service_id');
    }

    public function test_worker_can_create_filter_update_and_delete_hourly_service(): void
    {
        Sanctum::actingAs($worker = $this->workerUser());

        $service = Service::factory()->create([
            'name' => 'Electrician',
            'slug' => 'electrician',
            'is_active' => true,
        ]);

        $workerService = WorkerService::factory()->hourly()->create([
            'worker_id' => $worker->id,
            'service_id' => $service->id,
            'price' => 300,
            'minimum_hours' => 1,
        ]);

        $this->getJson('/api/worker/services?search=electric&pricing_type=hourly&is_active=1')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.worker_services.0.id', $workerService->id);

        $this->putJson("/api/worker/services/{$workerService->id}", [
            'service_id' => $service->id,
            'pricing_type' => WorkerService::PricingHourly,
            'price' => 350,
            'minimum_hours' => 2,
            'description' => 'Two hour minimum electrical work.',
            'is_active' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.worker_service.price', '350.00')
            ->assertJsonPath('data.worker_service.minimum_hours', 2)
            ->assertJsonPath('data.worker_service.is_active', false);

        $this->deleteJson("/api/worker/services/{$workerService->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Worker service deleted');

        $this->assertDatabaseMissing('worker_services', ['id' => $workerService->id]);
    }

    public function test_hourly_pricing_requires_minimum_hours(): void
    {
        Sanctum::actingAs($this->workerUser());

        $service = Service::factory()->create(['is_active' => true]);

        $this->postJson('/api/worker/services', [
            'service_id' => $service->id,
            'pricing_type' => WorkerService::PricingHourly,
            'price' => 300,
            'description' => 'Hourly work.',
            'is_active' => true,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors('minimum_hours');
    }

    public function test_worker_cannot_manage_another_workers_service(): void
    {
        $owner = $this->workerUser();
        $otherWorker = User::factory()
            ->for(Role::where('slug', 'worker')->firstOrFail())
            ->create();

        Sanctum::actingAs($otherWorker);

        $workerService = WorkerService::factory()->create(['worker_id' => $owner->id]);

        $this->getJson("/api/worker/services/{$workerService->id}")
            ->assertNotFound();
    }

    public function test_customer_cannot_access_worker_services(): void
    {
        $this->seed(RoleSeeder::class);

        Sanctum::actingAs(
            User::factory()->for(Role::where('slug', 'customer')->firstOrFail())->create(),
        );

        $this->getJson('/api/worker/services')
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    private function workerUser(): User
    {
        $this->seed(RoleSeeder::class);

        return User::factory()
            ->for(Role::where('slug', 'worker')->firstOrFail())
            ->create();
    }
}
