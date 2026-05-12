<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use App\Notifications\BookingWorkflowNotification;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminWorkerBlockingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_active_booking_count_before_blocking_worker(): void
    {
        [$admin, $worker, $customer, $service] = $this->bookingUsers();

        Booking::factory()->create([
            'customer_id' => $customer->id,
            'worker_id' => $worker->id,
            'service_id' => $service->id,
            'status' => Booking::STATUS_ACCEPTED,
        ]);

        Booking::factory()->create([
            'customer_id' => $customer->id,
            'worker_id' => $worker->id,
            'service_id' => $service->id,
            'status' => Booking::STATUS_COMPLETED,
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/users?search='.$worker->email)
            ->assertOk()
            ->assertJsonPath('data.users.0.active_worker_bookings_count', 1);
    }

    public function test_full_blocking_worker_cancels_future_bookings_and_notifies_customers(): void
    {
        Notification::fake();

        [$admin, $worker, $customer, $service] = $this->bookingUsers();

        $paidBooking = Booking::factory()->create([
            'customer_id' => $customer->id,
            'worker_id' => $worker->id,
            'service_id' => $service->id,
            'status' => Booking::STATUS_ACCEPTED,
            'payment_status' => Booking::PAYMENT_PAID,
            'paid_at' => now(),
        ]);

        Payment::factory()->create([
            'booking_id' => $paidBooking->id,
            'customer_id' => $customer->id,
            'worker_id' => $worker->id,
            'amount' => $paidBooking->quoted_amount,
            'status' => Payment::STATUS_PAID,
        ]);

        $confirmedBooking = Booking::factory()->create([
            'customer_id' => $customer->id,
            'worker_id' => $worker->id,
            'service_id' => $service->id,
            'status' => Booking::STATUS_CONFIRMED,
            'payment_status' => Booking::PAYMENT_UNPAID,
        ]);

        $inProgressBooking = Booking::factory()->create([
            'customer_id' => $customer->id,
            'worker_id' => $worker->id,
            'service_id' => $service->id,
            'status' => Booking::STATUS_IN_PROGRESS,
        ]);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/users/{$worker->id}/block", [
            'block_type' => User::STATUS_FULLY_BLOCKED,
        ])
            ->assertOk()
            ->assertJsonPath('data.user.account_status', User::STATUS_FULLY_BLOCKED)
            ->assertJsonPath('data.user.is_blocked', true)
            ->assertJsonPath('data.user.active_worker_bookings_count', 1);

        $this->assertDatabaseHas('bookings', [
            'id' => $paidBooking->id,
            'status' => Booking::STATUS_CANCELLED,
            'cancelled_by' => $admin->id,
            'cancelled_reason' => 'Worker blocked by admin',
            'payment_status' => Booking::PAYMENT_REFUND_REVIEW,
        ]);

        $this->assertDatabaseHas('bookings', [
            'id' => $confirmedBooking->id,
            'status' => Booking::STATUS_CANCELLED,
            'cancelled_by' => $admin->id,
            'cancelled_reason' => 'Worker blocked by admin',
            'payment_status' => Booking::PAYMENT_UNPAID,
        ]);

        $this->assertDatabaseHas('bookings', [
            'id' => $inProgressBooking->id,
            'status' => Booking::STATUS_IN_PROGRESS,
        ]);

        $this->assertDatabaseHas('booking_activities', [
            'booking_id' => $paidBooking->id,
            'actor_id' => $admin->id,
            'from_status' => Booking::STATUS_ACCEPTED,
            'to_status' => Booking::STATUS_CANCELLED,
            'event' => 'worker_blocked_booking_cancelled',
            'note' => 'Worker blocked by admin',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'action' => 'admin.worker_blocked_booking_cancelled',
            'subject_type' => (new Booking)->getMorphClass(),
            'subject_id' => $paidBooking->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'action' => 'admin.user_fully_blocked',
            'subject_type' => (new User)->getMorphClass(),
            'subject_id' => $worker->id,
        ]);

        Notification::assertSentTo(
            $customer,
            BookingWorkflowNotification::class,
            function (BookingWorkflowNotification $notification) use ($customer): bool {
                return $notification->toArray($customer)['event'] === 'worker_blocked_booking_cancelled';
            }
        );
    }

    public function test_partial_blocking_worker_cancels_pending_work_only(): void
    {
        [$admin, $worker, $customer, $service] = $this->bookingUsers();

        $pendingBooking = Booking::factory()->create([
            'customer_id' => $customer->id,
            'worker_id' => $worker->id,
            'service_id' => $service->id,
            'status' => Booking::STATUS_PENDING,
        ]);

        $confirmedBooking = Booking::factory()->create([
            'customer_id' => $customer->id,
            'worker_id' => $worker->id,
            'service_id' => $service->id,
            'status' => Booking::STATUS_CONFIRMED,
        ]);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/users/{$worker->id}/block", [
            'block_type' => User::STATUS_PARTIALLY_BLOCKED,
        ])
            ->assertOk()
            ->assertJsonPath('data.user.account_status', User::STATUS_PARTIALLY_BLOCKED)
            ->assertJsonPath('data.user.is_blocked', false)
            ->assertJsonPath('data.user.active_worker_bookings_count', 1);

        $this->assertDatabaseHas('bookings', [
            'id' => $pendingBooking->id,
            'status' => Booking::STATUS_CANCELLED,
            'cancelled_reason' => 'Account partially blocked by admin',
        ]);

        $this->assertDatabaseHas('bookings', [
            'id' => $confirmedBooking->id,
            'status' => Booking::STATUS_CONFIRMED,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'action' => 'admin.user_partially_blocked',
            'subject_type' => (new User)->getMorphClass(),
            'subject_id' => $worker->id,
        ]);
    }

    /**
     * Create the admin, worker, customer, and service needed for worker blocking tests.
     *
     * @return array{User, User, User, Service}
     */
    private function bookingUsers(): array
    {
        $this->seed(RoleSeeder::class);

        $admin = User::factory()
            ->for(Role::where('slug', 'admin')->firstOrFail())
            ->create();

        $worker = User::factory()
            ->for(Role::where('slug', 'worker')->firstOrFail())
            ->create();

        $customer = User::factory()
            ->for(Role::where('slug', 'customer')->firstOrFail())
            ->create();

        $service = Service::factory()->create(['is_active' => true]);

        return [$admin, $worker, $customer, $service];
    }
}
