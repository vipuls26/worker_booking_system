<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use App\Models\WorkerProfile;
use App\Models\WorkerSchedule;
use App\Models\WorkerService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerWorkerSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_filter_workers_by_service_price_city_and_availability(): void
    {
        Sanctum::actingAs($this->customerUser());

        $worker = $this->workerUser();
        $service = Service::factory()->create(['name' => 'AC Repair', 'slug' => 'ac-repair']);

        WorkerProfile::factory()->create([
            'user_id' => $worker->id,
            'city' => 'Mumbai',
            'latitude' => 19.076,
            'longitude' => 72.8777,
        ]);

        WorkerService::factory()->create([
            'worker_id' => $worker->id,
            'service_id' => $service->id,
            'price' => 500,
            'is_active' => true,
        ]);

        WorkerSchedule::factory()->create([
            'worker_id' => $worker->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '18:00',
        ]);

        $this->getJson('/api/customer/workers?service_id='.$service->id.'&max_price=600&city=Mumbai&available_date=2026-05-11&available_time=10:00')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.workers.0.id', $worker->id)
            ->assertJsonPath('data.workers.0.services.0.service.name', 'AC Repair');
    }

    public function test_customer_can_view_worker_details(): void
    {
        Sanctum::actingAs($this->customerUser());

        $worker = $this->workerUser();
        WorkerProfile::factory()->create(['user_id' => $worker->id]);

        $this->getJson("/api/customer/workers/{$worker->id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.worker.id', $worker->id);
    }

    public function test_worker_cannot_access_customer_worker_search(): void
    {
        Sanctum::actingAs($this->workerUser());

        $this->getJson('/api/customer/workers')
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

    private function customerUser(): User
    {
        $this->seed(RoleSeeder::class);

        return User::factory()
            ->for(Role::where('slug', 'customer')->firstOrFail())
            ->create();
    }
}
