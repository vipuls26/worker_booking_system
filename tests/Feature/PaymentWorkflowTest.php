<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Role;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Models\WorkerService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_pay_confirmed_booking_and_commission_is_recorded(): void
    {
        $this->seed(RoleSeeder::class);

        $customer = User::factory()
            ->for(Role::where('slug', 'customer')->firstOrFail())
            ->create(['is_verified' => true]);
        $worker = User::factory()
            ->for(Role::where('slug', 'worker')->firstOrFail())
            ->create(['is_verified' => true]);
        $service = Service::factory()->create();
        $booking = Booking::factory()->create([
            'customer_id' => $customer->id,
            'worker_id' => $worker->id,
            'selected_worker_id' => $worker->id,
            'service_id' => $service->id,
            'quoted_amount' => 1000,
            'quoted_commission_rate' => 10,
            'quoted_platform_commission' => 100,
            'quoted_worker_earning' => 900,
            'status' => Booking::STATUS_CONFIRMED,
            'payment_status' => Booking::PAYMENT_UNPAID,
        ]);
        $serviceRequest = ServiceRequest::factory()->create([
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'selected_worker_id' => $worker->id,
            'booking_id' => $booking->id,
            'status' => ServiceRequest::STATUS_WORKER_SELECTED,
        ]);

        Sanctum::actingAs($customer);

        $this->postJson("/api/customer/bookings/{$serviceRequest->id}/pay", [
            'provider' => 'manual',
            'transaction_reference' => 'TEST-PAY-001',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.payment.amount', '1000.00')
            ->assertJsonPath('data.payment.platform_commission', '100.00')
            ->assertJsonPath('data.payment.worker_earning', '900.00')
            ->assertJsonPath('data.booking.booking.payment_status', Booking::PAYMENT_PAID);

        $this->assertDatabaseHas('payments', [
            'booking_id' => $booking->id,
            'customer_id' => $customer->id,
            'worker_id' => $worker->id,
            'status' => Payment::STATUS_PAID,
            'transaction_reference' => 'TEST-PAY-001',
        ]);
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'payment_status' => Booking::PAYMENT_PAID,
        ]);
    }

    public function test_payment_uses_locked_booking_quote_after_worker_changes_service_price(): void
    {
        $this->seed(RoleSeeder::class);

        $customer = User::factory()
            ->for(Role::where('slug', 'customer')->firstOrFail())
            ->create(['is_verified' => true]);
        $worker = User::factory()
            ->for(Role::where('slug', 'worker')->firstOrFail())
            ->create(['is_verified' => true]);
        $service = Service::factory()->create();

        WorkerService::factory()->create([
            'worker_id' => $worker->id,
            'service_id' => $service->id,
            'pricing_type' => WorkerService::PricingFixed,
            'price' => 2500,
            'approval_status' => WorkerService::StatusApproved,
        ]);

        $booking = Booking::factory()->create([
            'customer_id' => $customer->id,
            'worker_id' => $worker->id,
            'selected_worker_id' => $worker->id,
            'service_id' => $service->id,
            'quoted_amount' => 1000,
            'quoted_commission_rate' => 10,
            'quoted_platform_commission' => 100,
            'quoted_worker_earning' => 900,
            'status' => Booking::STATUS_COMPLETED,
            'payment_status' => Booking::PAYMENT_UNPAID,
        ]);
        $serviceRequest = ServiceRequest::factory()->create([
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'selected_worker_id' => $worker->id,
            'booking_id' => $booking->id,
            'status' => ServiceRequest::STATUS_WORKER_SELECTED,
        ]);

        Sanctum::actingAs($customer);

        $this->postJson("/api/customer/bookings/{$serviceRequest->id}/pay", [
            'provider' => 'manual',
            'transaction_reference' => 'LOCKED-QUOTE-001',
        ])
            ->assertOk()
            ->assertJsonPath('data.payment.amount', '1000.00')
            ->assertJsonPath('data.payment.platform_commission', '100.00')
            ->assertJsonPath('data.payment.worker_earning', '900.00');

        $this->assertDatabaseHas('payments', [
            'booking_id' => $booking->id,
            'amount' => 1000,
            'platform_commission' => 100,
            'worker_earning' => 900,
        ]);
    }

    public function test_payment_uses_locked_booking_commission_rate_and_audits_global_rate_difference(): void
    {
        $this->seed(RoleSeeder::class);

        $customer = User::factory()
            ->for(Role::where('slug', 'customer')->firstOrFail())
            ->create(['is_verified' => true]);
        $worker = User::factory()
            ->for(Role::where('slug', 'worker')->firstOrFail())
            ->create(['is_verified' => true]);
        $service = Service::factory()->create();
        $booking = Booking::factory()->create([
            'customer_id' => $customer->id,
            'worker_id' => $worker->id,
            'selected_worker_id' => $worker->id,
            'service_id' => $service->id,
            'quoted_amount' => 1000,
            'quoted_commission_rate' => 8,
            'quoted_platform_commission' => 80,
            'quoted_worker_earning' => 920,
            'status' => Booking::STATUS_COMPLETED,
            'payment_status' => Booking::PAYMENT_UNPAID,
        ]);
        $serviceRequest = ServiceRequest::factory()->create([
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'selected_worker_id' => $worker->id,
            'booking_id' => $booking->id,
            'status' => ServiceRequest::STATUS_WORKER_SELECTED,
        ]);

        Sanctum::actingAs($customer);

        $this->postJson("/api/customer/bookings/{$serviceRequest->id}/pay", [
            'provider' => 'manual',
            'transaction_reference' => 'LOCKED-RATE-001',
        ])
            ->assertOk()
            ->assertJsonPath('data.payment.commission_rate', '8.00')
            ->assertJsonPath('data.payment.platform_commission', '80.00')
            ->assertJsonPath('data.payment.worker_earning', '920.00');

        $payment = Payment::query()
            ->where('transaction_reference', 'LOCKED-RATE-001')
            ->firstOrFail();

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'booking_id' => $booking->id,
            'amount' => 1000,
            'commission_rate' => 8,
            'platform_commission' => 80,
            'worker_earning' => 920,
        ]);

        $auditLog = AuditLog::query()
            ->where('action', 'payment.commission_rate_changed')
            ->where('subject_type', (new Payment)->getMorphClass())
            ->where('subject_id', $payment->id)
            ->firstOrFail();

        $this->assertEquals(8.0, $auditLog->metadata['quoted_commission_rate']);
        $this->assertEquals(Booking::DefaultCommissionRate, $auditLog->metadata['current_commission_rate']);
        $this->assertEquals(2.0, $auditLog->metadata['rate_difference']);
    }
}
