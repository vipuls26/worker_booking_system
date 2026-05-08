<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Dispute;
use App\Models\Role;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DisputeWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_open_dispute_for_confirmed_booking(): void
    {
        [$customer, $worker, $booking] = $this->createConfirmedBooking();

        Sanctum::actingAs($customer);

        $this->postJson('/api/disputes', [
            'booking_id' => $booking->id,
            'category' => 'service_issue',
            'title' => 'Worker arrived late',
            'description' => 'The worker arrived much later than the confirmed time.',
        ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.dispute.status', Dispute::STATUS_OPEN)
            ->assertJsonPath('data.dispute.against_user.id', $worker->id);

        $this->assertDatabaseHas('disputes', [
            'booking_id' => $booking->id,
            'opened_by' => $customer->id,
            'against_user_id' => $worker->id,
            'status' => Dispute::STATUS_OPEN,
        ]);
    }

    public function test_admin_can_move_dispute_through_resolution_workflow(): void
    {
        [$customer, , $booking] = $this->createConfirmedBooking();
        $admin = User::factory()
            ->for(Role::where('slug', 'admin')->firstOrFail())
            ->create(['is_verified' => true]);
        $dispute = Dispute::factory()->create([
            'booking_id' => $booking->id,
            'service_request_id' => $booking->service_request_id,
            'opened_by' => $customer->id,
            'status' => Dispute::STATUS_OPEN,
        ]);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/admin/disputes/{$dispute->id}", [
            'status' => Dispute::STATUS_RESOLVED,
            'resolution_note' => 'Refund discussion completed outside the platform.',
        ])
            ->assertOk()
            ->assertJsonPath('data.dispute.status', Dispute::STATUS_RESOLVED)
            ->assertJsonPath('data.dispute.resolved_by.id', $admin->id);

        $this->assertDatabaseHas('dispute_status_histories', [
            'dispute_id' => $dispute->id,
            'from_status' => Dispute::STATUS_OPEN,
            'to_status' => Dispute::STATUS_RESOLVED,
        ]);
    }

    /**
     * @return array{0: User, 1: User, 2: Booking}
     */
    private function createConfirmedBooking(): array
    {
        $this->seed(RoleSeeder::class);

        $customer = User::factory()
            ->for(Role::where('slug', 'customer')->firstOrFail())
            ->create(['is_verified' => true, 'email_verified_at' => now()]);
        $worker = User::factory()
            ->for(Role::where('slug', 'worker')->firstOrFail())
            ->create(['is_verified' => true, 'email_verified_at' => now()]);
        $service = Service::factory()->create();
        $booking = Booking::factory()->create([
            'customer_id' => $customer->id,
            'worker_id' => $worker->id,
            'selected_worker_id' => $worker->id,
            'service_id' => $service->id,
            'status' => Booking::STATUS_CONFIRMED,
        ]);
        $serviceRequest = ServiceRequest::factory()->create([
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'selected_worker_id' => $worker->id,
            'booking_id' => $booking->id,
            'status' => ServiceRequest::STATUS_WORKER_SELECTED,
        ]);
        $booking->update(['service_request_id' => $serviceRequest->id]);

        return [$customer, $worker, $booking->refresh()];
    }
}
