<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\User;
use App\Models\WorkerSchedule;
use App\Models\WorkerService;
use App\Models\WorkerVerification;
use Database\Seeders\E2ETestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class E2ETestSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Ensure the Playwright seed creates the expected customer, worker, and service data.
     */
    public function test_e2e_seeder_creates_predictable_booking_data(): void
    {
        $this->seed(E2ETestSeeder::class);

        $customerUser = User::query()->where('email', 'e2e.customer@example.com')->first();
        $workerUser = User::query()->where('email', 'e2e.worker@example.com')->first();
        $service = Service::query()->where('slug', 'e2e-ac-repair')->first();

        // Playwright login depends on a verified customer account.
        $this->assertNotNull($customerUser);
        $this->assertSame('customer', $customerUser->role->slug);
        $this->assertDatabaseHas('customer_profiles', [
            'user_id' => $customerUser->id,
            'address' => '123 E2E Street, Ahmedabad',
        ]);

        // Worker search depends on approved worker profile, verification, and service records.
        $this->assertNotNull($workerUser);
        $this->assertSame('worker', $workerUser->role->slug);
        $this->assertDatabaseHas('worker_profiles', [
            'user_id' => $workerUser->id,
            'city' => 'Ahmedabad',
            'is_verified' => true,
        ]);
        $this->assertDatabaseHas('worker_verifications', [
            'user_id' => $workerUser->id,
            'status' => WorkerVerification::STATUS_APPROVED,
        ]);
        $this->assertDatabaseHas('worker_services', [
            'worker_id' => $workerUser->id,
            'service_id' => $service?->id,
            'approval_status' => WorkerService::StatusApproved,
            'is_active' => true,
        ]);

        // The example booking flow needs at least one visible service and a full-week schedule.
        $this->assertNotNull($service);
        $this->assertSame('E2E AC Repair', $service->name);
        $this->assertSame(7, WorkerSchedule::query()->where('worker_id', $workerUser->id)->count());
    }
}
