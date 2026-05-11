<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\CommissionSetting;
use App\Models\Role;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestWorker;
use App\Models\User;
use App\Models\WorkerProfile;
use App\Models\WorkerSchedule;
use App\Models\WorkerService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminCommissionSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_global_commission_rate_and_audit_it(): void
    {
        $admin = $this->adminUser();

        Sanctum::actingAs($admin);

        $this->patchJson('/api/admin/commission-settings', [
            'commission_rate' => 12.5,
        ])
            ->assertOk()
            ->assertJsonPath('data.commission_setting.commission_rate', '12.50')
            ->assertJsonPath('data.commission_setting.updated_by', $admin->name);

        $commissionSetting = CommissionSetting::query()->firstOrFail();

        $this->assertDatabaseHas('commission_settings', [
            'id' => $commissionSetting->id,
            'name' => CommissionSetting::GlobalSettingName,
            'commission_rate' => 12.5,
            'updated_by' => $admin->id,
        ]);

        $auditLog = AuditLog::query()
            ->where('action', 'admin.commission_rate_updated')
            ->where('subject_id', $commissionSetting->id)
            ->firstOrFail();

        $this->assertEquals(10.0, $auditLog->metadata['previous_commission_rate']);
        $this->assertEquals(12.5, $auditLog->metadata['new_commission_rate']);
    }

    public function test_admin_commission_rate_rejects_invalid_values(): void
    {
        $admin = $this->adminUser();

        Sanctum::actingAs($admin);

        foreach (['', -1, 101] as $invalidRate) {
            $this->patchJson('/api/admin/commission-settings', [
                'commission_rate' => $invalidRate,
            ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('commission_rate');
        }
    }

    public function test_updated_commission_rate_only_applies_to_new_bookings(): void
    {
        $admin = $this->adminUser();
        [$customer, $worker, $service] = $this->bookingActors();

        $oldBooking = Booking::factory()->create([
            'customer_id' => $customer->id,
            'worker_id' => $worker->id,
            'selected_worker_id' => $worker->id,
            'service_id' => $service->id,
            'quoted_amount' => 1000,
            'quoted_commission_rate' => 10,
            'quoted_platform_commission' => 100,
            'quoted_worker_earning' => 900,
        ]);

        Sanctum::actingAs($admin);

        $this->patchJson('/api/admin/commission-settings', [
            'commission_rate' => 15,
        ])->assertOk();

        $serviceRequest = ServiceRequest::factory()->create([
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'requested_date' => '2026-05-11',
            'start_time' => '10:00',
            'end_time' => '11:00',
            'address' => '123 Test Street',
            'description' => 'Need a technician.',
        ]);
        $serviceRequestWorker = ServiceRequestWorker::factory()->create([
            'service_request_id' => $serviceRequest->id,
            'worker_id' => $worker->id,
            'status' => ServiceRequestWorker::STATUS_PENDING,
            'quoted_price' => 1000,
        ]);

        Sanctum::actingAs($worker);

        $this->patchJson("/api/worker/booking-requests/{$serviceRequestWorker->id}/respond", [
            'status' => ServiceRequestWorker::STATUS_ACCEPTED,
        ])->assertOk();

        $oldBooking->refresh();
        $newBooking = Booking::query()->where('service_request_id', $serviceRequest->id)->firstOrFail();

        $this->assertEquals('10.00', $oldBooking->quoted_commission_rate);
        $this->assertEquals('100.00', $oldBooking->quoted_platform_commission);
        $this->assertEquals('900.00', $oldBooking->quoted_worker_earning);
        $this->assertEquals('15.00', $newBooking->quoted_commission_rate);
        $this->assertEquals('150.00', $newBooking->quoted_platform_commission);
        $this->assertEquals('850.00', $newBooking->quoted_worker_earning);
    }

    private function adminUser(): User
    {
        $this->seed(RoleSeeder::class);

        return User::factory()
            ->for(Role::where('slug', 'admin')->firstOrFail())
            ->create();
    }

    /**
     * @return array{User, User, Service}
     */
    private function bookingActors(): array
    {
        $customer = User::factory()
            ->for(Role::where('slug', 'customer')->firstOrFail())
            ->create(['is_verified' => true]);
        $worker = User::factory()
            ->for(Role::where('slug', 'worker')->firstOrFail())
            ->create(['is_verified' => true]);
        $service = Service::factory()->create(['is_active' => true]);

        WorkerProfile::factory()->create([
            'user_id' => $worker->id,
            'is_verified' => true,
        ]);
        WorkerService::factory()->create([
            'worker_id' => $worker->id,
            'service_id' => $service->id,
            'pricing_type' => WorkerService::PricingFixed,
            'price' => 1000,
            'is_active' => true,
            'approval_status' => WorkerService::StatusApproved,
        ]);
        WorkerSchedule::factory()->create([
            'worker_id' => $worker->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '18:00',
        ]);

        return [$customer, $worker, $service];
    }
}
