<?php

namespace Tests\Feature;

use App\Models\Booking;
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
            'is_verified' => true,
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

    public function test_customer_can_search_workers_by_name_service_and_skill(): void
    {
        Sanctum::actingAs($this->customerUser());

        $service = Service::factory()->create(['name' => 'Deep Cleaning', 'slug' => 'deep-cleaning']);
        $matchingWorker = $this->workerUser();
        $otherWorker = $this->workerUser();

        WorkerProfile::factory()->create([
            'user_id' => $matchingWorker->id,
            'city' => 'Pune',
            'bio' => 'Expert cleaner for family homes.',
            'skills' => ['kitchen deep clean', 'sofa shampoo'],
            'is_verified' => true,
        ]);

        WorkerProfile::factory()->create([
            'user_id' => $otherWorker->id,
            'city' => 'Delhi',
            'bio' => 'General home maintenance support.',
            'skills' => ['plumbing'],
            'is_verified' => true,
        ]);

        WorkerService::factory()->create([
            'worker_id' => $matchingWorker->id,
            'service_id' => $service->id,
            'description' => 'Deep cleaning for kitchens and living rooms.',
            'is_active' => true,
            'approval_status' => WorkerService::StatusApproved,
        ]);

        WorkerService::factory()->create([
            'worker_id' => $otherWorker->id,
            'service_id' => $service->id,
            'description' => 'Regular cleaning only.',
            'is_active' => true,
            'approval_status' => WorkerService::StatusApproved,
        ]);

        $this->getJson('/api/customer/workers?search=sofa')
            ->assertOk()
            ->assertJsonCount(1, 'data.workers')
            ->assertJsonPath('data.workers.0.id', $matchingWorker->id);
    }

    public function test_customer_search_hides_worker_with_blocking_booking_from_another_service(): void
    {
        Sanctum::actingAs($this->customerUser());

        $worker = $this->workerUser();
        $searchService = Service::factory()->create(['name' => 'AC Repair', 'slug' => 'ac-repair']);
        $otherService = Service::factory()->create(['name' => 'Plumbing', 'slug' => 'plumbing']);

        WorkerProfile::factory()->create([
            'user_id' => $worker->id,
            'city' => 'Mumbai',
            'is_verified' => true,
        ]);

        WorkerService::factory()->create([
            'worker_id' => $worker->id,
            'service_id' => $searchService->id,
            'price' => 500,
            'is_active' => true,
            'approval_status' => WorkerService::StatusApproved,
        ]);

        WorkerSchedule::factory()->create([
            'worker_id' => $worker->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '18:00',
        ]);

        Booking::factory()->create([
            'worker_id' => $worker->id,
            'customer_id' => $this->customerUser()->id,
            'service_id' => $otherService->id,
            'booking_date' => '2026-05-11',
            'booking_time' => '10:00',
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => Booking::STATUS_CONFIRMED,
        ]);

        $this->getJson('/api/customer/workers?service_id='.$searchService->id.'&available_date=2026-05-11&available_time=10:30')
            ->assertOk()
            ->assertJsonCount(0, 'data.workers');
    }

    public function test_customer_can_view_worker_details(): void
    {
        Sanctum::actingAs($this->customerUser());

        $worker = $this->workerUser();
        WorkerProfile::factory()->create([
            'user_id' => $worker->id,
            'is_verified' => true,
        ]);

        $this->getJson("/api/customer/workers/{$worker->id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.worker.id', $worker->id);
    }

    public function test_customer_can_sort_workers_by_experience(): void
    {
        Sanctum::actingAs($this->customerUser());

        $service = Service::factory()->create();
        $juniorWorker = $this->searchableWorker($service, 2);
        $seniorWorker = $this->searchableWorker($service, 12);

        $this->getJson('/api/customer/workers?sort=experience')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.workers.0.id', $seniorWorker->id)
            ->assertJsonPath('data.workers.1.id', $juniorWorker->id);
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
            ->create(['is_verified' => true]);
    }

    private function customerUser(): User
    {
        $this->seed(RoleSeeder::class);

        return User::factory()
            ->for(Role::where('slug', 'customer')->firstOrFail())
            ->create(['is_verified' => true]);
    }

    private function searchableWorker(Service $service, int $experienceYears): User
    {
        $worker = $this->workerUser();

        WorkerProfile::factory()->create([
            'user_id' => $worker->id,
            'experience_years' => $experienceYears,
            'is_verified' => true,
        ]);

        WorkerService::factory()->create([
            'worker_id' => $worker->id,
            'service_id' => $service->id,
            'is_active' => true,
            'approval_status' => WorkerService::StatusApproved,
        ]);

        return $worker;
    }
}
