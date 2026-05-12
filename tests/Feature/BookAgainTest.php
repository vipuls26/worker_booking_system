<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\CustomerProfile;
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

class BookAgainTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_prepare_book_again_from_completed_booking(): void
    {
        [$customer, $worker, $service, $sourceServiceRequest, $sourceBooking] = $this->completedBookingActors();
        Sanctum::actingAs($customer);

        $this->postJson("/api/customer/bookings/{$sourceServiceRequest->id}/book-again")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.prefill.source_booking_id', $sourceBooking->id)
            ->assertJsonPath('data.prefill.worker_id', $worker->id)
            ->assertJsonPath('data.prefill.service_id', $service->id)
            ->assertJsonPath('data.prefill.address', '123 Test Street')
            ->assertJsonPath('data.prefill.issue_description', 'Need a technician again.');
    }

    public function test_book_again_creates_new_service_request_with_current_pricing(): void
    {
        [$customer, $worker, $service, $sourceServiceRequest, $sourceBooking] = $this->completedBookingActors();

        WorkerService::query()
            ->where('worker_id', $worker->id)
            ->where('service_id', $service->id)
            ->update(['price' => 900]);

        Sanctum::actingAs($customer);

        $newServiceRequestId = $this->postJson('/api/customer/bookings', [
            'source_booking_id' => $sourceBooking->id,
            'worker_id' => $worker->id,
            'service_id' => $service->id,
            'booking_date' => '2026-05-18',
            'start_time' => '10:00',
            'end_time' => '11:00',
            'address' => '456 Repeat Street',
            'issue_description' => 'Repeat visit with updated notes.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.booking.recreated_from_booking_id', $sourceBooking->id)
            ->assertJsonPath('data.booking.total_amount', '900.00')
            ->json('data.booking.id');

        $this->assertNotEquals($sourceServiceRequest->id, $newServiceRequestId);
        $this->assertDatabaseHas('service_requests', [
            'id' => $newServiceRequestId,
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'recreated_from_booking_id' => $sourceBooking->id,
            'estimated_amount' => 900,
            'status' => ServiceRequest::STATUS_OPEN,
        ]);
    }

    public function test_book_again_rejects_worker_who_is_no_longer_bookable(): void
    {
        [$customer, $worker, $service, $sourceServiceRequest] = $this->completedBookingActors();

        $worker->update(['account_status' => User::STATUS_FULLY_BLOCKED, 'is_blocked' => true]);
        Sanctum::actingAs($customer);

        $this->postJson("/api/customer/bookings/{$sourceServiceRequest->id}/book-again")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('service_id')
            ->assertJsonPath('errors.service_id.0', 'This worker or service is no longer available for booking.');
    }

    public function test_book_again_is_unavailable_when_worker_has_no_future_slots(): void
    {
        [$customer, $worker, $service, $sourceServiceRequest] = $this->completedBookingActors();

        $worker->workerSchedules()->delete();
        Sanctum::actingAs($customer);

        $this->postJson("/api/customer/bookings/{$sourceServiceRequest->id}/book-again")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('start_time')
            ->assertJsonPath('errors.start_time.0', 'This worker has no available slots for this service right now.');
    }

    public function test_new_booking_records_recreated_activity_after_worker_accepts(): void
    {
        [$customer, $worker, $service, $sourceServiceRequest, $sourceBooking] = $this->completedBookingActors();
        Sanctum::actingAs($customer);

        $newServiceRequestId = $this->postJson('/api/customer/bookings', [
            'source_booking_id' => $sourceBooking->id,
            'worker_id' => $worker->id,
            'service_id' => $service->id,
            'booking_date' => '2026-05-18',
            'start_time' => '10:00',
            'end_time' => '11:00',
            'address' => '456 Repeat Street',
            'issue_description' => 'Repeat visit with updated notes.',
        ])->json('data.booking.id');

        $bookingRequest = ServiceRequestWorker::query()
            ->where('service_request_id', $newServiceRequestId)
            ->where('worker_id', $worker->id)
            ->firstOrFail();

        Sanctum::actingAs($worker);

        $this->patchJson("/api/worker/booking-requests/{$bookingRequest->id}/respond", [
            'status' => ServiceRequestWorker::STATUS_ACCEPTED,
        ])->assertOk();

        $newBooking = Booking::query()
            ->where('service_request_id', $newServiceRequestId)
            ->firstOrFail();

        $this->assertDatabaseHas('booking_activities', [
            'booking_id' => $newBooking->id,
            'actor_id' => $worker->id,
            'event' => 'booking_recreated',
            'note' => "Booking recreated from completed booking #{$sourceBooking->id}",
        ]);
    }

    /**
     * Create a completed booking with an active customer, worker, service, and schedule.
     *
     * @return array{User, User, Service, ServiceRequest, Booking}
     */
    private function completedBookingActors(): array
    {
        $this->seed(RoleSeeder::class);

        $customer = User::factory()
            ->for(Role::where('slug', 'customer')->firstOrFail())
            ->create(['is_verified' => true]);
        CustomerProfile::create([
            'user_id' => $customer->id,
            'address' => '123 Test Street',
        ]);

        $worker = User::factory()
            ->for(Role::where('slug', 'worker')->firstOrFail())
            ->create(['is_verified' => true]);
        WorkerProfile::factory()->create([
            'user_id' => $worker->id,
            'is_verified' => true,
        ]);

        $service = Service::factory()->create(['is_active' => true]);
        WorkerService::factory()->create([
            'worker_id' => $worker->id,
            'service_id' => $service->id,
            'pricing_type' => WorkerService::PricingFixed,
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

        $sourceServiceRequest = ServiceRequest::factory()->create([
            'customer_id' => $customer->id,
            'selected_worker_id' => $worker->id,
            'service_id' => $service->id,
            'requested_date' => '2026-05-11',
            'start_time' => '10:00',
            'end_time' => '11:00',
            'address' => '123 Test Street',
            'description' => 'Need a technician again.',
            'estimated_amount' => 500,
            'status' => ServiceRequest::STATUS_WORKER_SELECTED,
        ]);

        $sourceBooking = Booking::factory()->create([
            'service_request_id' => $sourceServiceRequest->id,
            'customer_id' => $customer->id,
            'worker_id' => $worker->id,
            'selected_worker_id' => $worker->id,
            'service_id' => $service->id,
            'booking_date' => '2026-05-11',
            'booking_time' => '10:00',
            'start_time' => '10:00',
            'end_time' => '11:00',
            'address' => '123 Test Street',
            'notes' => 'Need a technician again.',
            'issue_description' => 'Need a technician again.',
            'quoted_amount' => 500,
            'status' => Booking::STATUS_COMPLETED,
        ]);

        $sourceServiceRequest->update(['booking_id' => $sourceBooking->id]);

        return [$customer, $worker, $service, $sourceServiceRequest->refresh(), $sourceBooking];
    }
}
