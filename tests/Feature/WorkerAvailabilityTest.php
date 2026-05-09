<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use App\Models\WorkerSchedule;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkerAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_worker_can_create_schedule_and_overlaps_are_rejected(): void
    {
        Sanctum::actingAs($worker = $this->workerUser());

        $this->postJson('/api/worker/schedules', [
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '13:00',
            'is_off_day' => false,
        ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.schedule.worker_id', $worker->id);

        $this->postJson('/api/worker/schedules', [
            'day_of_week' => 1,
            'start_time' => '12:00',
            'end_time' => '16:00',
            'is_off_day' => false,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('start_time');
    }

    public function test_worker_can_mark_weekly_off_day(): void
    {
        Sanctum::actingAs($worker = $this->workerUser());

        $this->postJson('/api/worker/schedules', [
            'day_of_week' => 0,
            'is_off_day' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('data.schedule.is_off_day', true)
            ->assertJsonPath('data.schedule.start_time', null);

        $this->assertDatabaseHas('worker_schedules', [
            'worker_id' => $worker->id,
            'day_of_week' => 0,
            'is_off_day' => true,
        ]);
    }

    public function test_availability_slots_exclude_existing_booking_time(): void
    {
        Sanctum::actingAs($worker = $this->workerUser());

        WorkerSchedule::factory()->create([
            'worker_id' => $worker->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]);

        Booking::factory()->create([
            'worker_id' => $worker->id,
            'customer_id' => $this->customerUser()->id,
            'service_id' => Service::factory()->create()->id,
            'booking_date' => '2026-05-11',
            'booking_time' => '10:00',
            'status' => Booking::STATUS_PENDING,
        ]);

        $this->getJson('/api/worker/availability?date=2026-05-11&slot_minutes=60')
            ->assertOk()
            ->assertJsonPath('data.slots.0.time', '09:00')
            ->assertJsonPath('data.slots.0.available', true)
            ->assertJsonPath('data.slots.1.time', '10:00')
            ->assertJsonPath('data.slots.1.available', false)
            ->assertJsonPath('data.slots.2.time', '11:00')
            ->assertJsonPath('data.slots.2.available', true);
    }

    public function test_customer_cannot_access_worker_availability_management(): void
    {
        Sanctum::actingAs($this->customerUser());

        $this->getJson('/api/worker/schedules')
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
