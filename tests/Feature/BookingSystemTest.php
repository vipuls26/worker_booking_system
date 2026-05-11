<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingRequest;
use App\Models\Review;
use App\Models\Role;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestWorker;
use App\Models\User;
use App\Models\WorkerSchedule;
use App\Models\WorkerService;
use App\Services\Booking\AvailabilityService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookingSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_create_booking_request(): void
    {
        [$customer, $worker, $service] = $this->bookingActors();
        Sanctum::actingAs($customer);

        $this->postJson('/api/customer/bookings', [
            'worker_id' => $worker->id,
            'service_id' => $service->id,
            'booking_date' => '2026-05-11',
            'start_time' => '10:00',
            'end_time' => '11:00',
            'address' => '123 Test Street',
            'issue_description' => 'AC is not cooling.',
        ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.booking.status', Booking::STATUS_PENDING)
            ->assertJsonPath('data.booking.total_amount', '500.00')
            ->assertJsonPath('data.booking.timeline.0.event', 'booking_created');

        $this->assertDatabaseHas('bookings', [
            'customer_id' => $customer->id,
            'worker_id' => $worker->id,
            'service_id' => $service->id,
            'start_time' => '10:00',
            'status' => Booking::STATUS_PENDING,
        ]);

        $this->assertDatabaseHas('booking_requests', [
            'worker_id' => $worker->id,
            'status' => BookingRequest::STATUS_SELECTED,
        ]);

        $this->assertDatabaseHas('booking_activities', [
            'actor_id' => $customer->id,
            'to_status' => Booking::STATUS_PENDING,
            'event' => 'booking_created',
        ]);
    }

    public function test_booking_creation_rejects_overlapping_booking(): void
    {
        [$customer, $worker, $service] = $this->bookingActors();

        Booking::factory()->create([
            'customer_id' => $customer->id,
            'worker_id' => $worker->id,
            'service_id' => $service->id,
            'booking_date' => '2026-05-11',
            'booking_time' => '10:00',
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => Booking::STATUS_CONFIRMED,
        ]);

        Sanctum::actingAs($customer);

        $this->postJson('/api/customer/bookings', [
            'worker_id' => $worker->id,
            'service_id' => $service->id,
            'booking_date' => '2026-05-11',
            'start_time' => '10:30',
            'end_time' => '11:30',
            'address' => '123 Test Street',
            'issue_description' => 'Need help.',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('start_time')
            ->assertJsonPath('errors.start_time.0', 'This worker already has a booking that overlaps the selected time.');
    }

    public function test_service_layer_rejects_in_progress_booking_overlap(): void
    {
        [$customer, $worker, $service] = $this->bookingActors();

        Booking::factory()->create([
            'customer_id' => $customer->id,
            'worker_id' => $worker->id,
            'service_id' => $service->id,
            'booking_date' => '2026-05-11',
            'booking_time' => '14:00',
            'start_time' => '14:00',
            'end_time' => '16:00',
            'status' => Booking::STATUS_IN_PROGRESS,
        ]);

        $hasOverlap = app(AvailabilityService::class)->hasOverlappingBooking(
            worker: $worker,
            date: '2026-05-11',
            startTime: '15:00',
            endTime: '17:00',
        );

        $this->assertTrue($hasOverlap);

        $hasBackToBackOverlap = app(AvailabilityService::class)->hasOverlappingBooking(
            worker: $worker,
            date: '2026-05-11',
            startTime: '16:00',
            endTime: '17:00',
        );

        $this->assertFalse($hasBackToBackOverlap);
    }

    public function test_worker_can_move_booking_through_status_workflow(): void
    {
        [$customer, $worker, $service] = $this->bookingActors();

        $booking = Booking::factory()->create([
            'customer_id' => $customer->id,
            'worker_id' => $worker->id,
            'service_id' => $service->id,
            'status' => Booking::STATUS_PENDING,
        ]);

        Sanctum::actingAs($worker);

        $this->patchJson("/api/worker/bookings/{$booking->id}/status", ['status' => Booking::STATUS_ACCEPTED])
            ->assertOk()
            ->assertJsonPath('data.booking.status', Booking::STATUS_ACCEPTED);

        $this->patchJson("/api/worker/bookings/{$booking->id}/status", ['status' => Booking::STATUS_IN_PROGRESS])
            ->assertOk()
            ->assertJsonPath('data.booking.status', Booking::STATUS_IN_PROGRESS);

        $this->patchJson("/api/worker/bookings/{$booking->id}/status", ['status' => Booking::STATUS_COMPLETED])
            ->assertOk()
            ->assertJsonPath('data.booking.status', Booking::STATUS_COMPLETED);

        $this->assertDatabaseHas('booking_activities', [
            'booking_id' => $booking->id,
            'actor_id' => $worker->id,
            'from_status' => Booking::STATUS_IN_PROGRESS,
            'to_status' => Booking::STATUS_COMPLETED,
            'event' => 'work_completed',
        ]);
    }

    public function test_worker_must_provide_reason_when_cancelling_booking_request(): void
    {
        [$customer, $worker, $service] = $this->bookingActors();
        $serviceRequest = ServiceRequest::factory()->create([
            'customer_id' => $customer->id,
            'service_id' => $service->id,
        ]);
        $bookingRequest = ServiceRequestWorker::factory()->create([
            'service_request_id' => $serviceRequest->id,
            'worker_id' => $worker->id,
            'status' => ServiceRequestWorker::STATUS_PENDING,
        ]);

        Sanctum::actingAs($worker);

        $this->patchJson("/api/worker/booking-requests/{$bookingRequest->id}/respond", [
            'status' => ServiceRequestWorker::STATUS_CANCELLED,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('response_reason');
    }

    public function test_worker_can_cancel_booking_request_with_reason(): void
    {
        [$customer, $worker, $service] = $this->bookingActors();
        $serviceRequest = ServiceRequest::factory()->create([
            'customer_id' => $customer->id,
            'service_id' => $service->id,
        ]);
        $bookingRequest = ServiceRequestWorker::factory()->create([
            'service_request_id' => $serviceRequest->id,
            'worker_id' => $worker->id,
            'status' => ServiceRequestWorker::STATUS_PENDING,
        ]);

        Sanctum::actingAs($worker);

        $this->patchJson("/api/worker/booking-requests/{$bookingRequest->id}/respond", [
            'status' => ServiceRequestWorker::STATUS_CANCELLED,
            'response_reason' => 'Already booked with another customer at this time.',
        ])
            ->assertOk()
            ->assertJsonPath('data.booking_request.status', ServiceRequestWorker::STATUS_CANCELLED)
            ->assertJsonPath('data.booking_request.response_reason', 'Already booked with another customer at this time.');

        $this->assertDatabaseHas('service_request_workers', [
            'id' => $bookingRequest->id,
            'status' => ServiceRequestWorker::STATUS_CANCELLED,
            'response_reason' => 'Already booked with another customer at this time.',
        ]);
    }

    public function test_single_worker_request_is_confirmed_when_worker_accepts(): void
    {
        [$customer, $worker, $service] = $this->bookingActors();
        $serviceRequest = ServiceRequest::factory()->create([
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'requested_date' => '2026-05-11',
            'start_time' => '10:00',
            'end_time' => '11:00',
            'address' => '123 Test Street',
            'description' => 'Need a technician.',
        ]);
        $bookingRequest = ServiceRequestWorker::factory()->create([
            'service_request_id' => $serviceRequest->id,
            'worker_id' => $worker->id,
            'status' => ServiceRequestWorker::STATUS_PENDING,
            'quoted_price' => 500,
        ]);

        Sanctum::actingAs($worker);

        $this->patchJson("/api/worker/booking-requests/{$bookingRequest->id}/respond", [
            'status' => ServiceRequestWorker::STATUS_ACCEPTED,
        ])
            ->assertOk()
            ->assertJsonPath('data.booking_request.status', ServiceRequestWorker::STATUS_SELECTED);

        $this->assertDatabaseHas('service_requests', [
            'id' => $serviceRequest->id,
            'selected_worker_id' => $worker->id,
            'status' => ServiceRequest::STATUS_WORKER_SELECTED,
        ]);
        $this->assertDatabaseHas('bookings', [
            'customer_id' => $customer->id,
            'worker_id' => $worker->id,
            'service_id' => $service->id,
            'status' => Booking::STATUS_CONFIRMED,
        ]);
        $this->assertDatabaseHas('service_request_workers', [
            'id' => $bookingRequest->id,
            'status' => ServiceRequestWorker::STATUS_SELECTED,
        ]);
    }

    public function test_worker_must_provide_reason_when_cancelling_booking(): void
    {
        [$customer, $worker, $service] = $this->bookingActors();

        $booking = Booking::factory()->create([
            'customer_id' => $customer->id,
            'worker_id' => $worker->id,
            'service_id' => $service->id,
            'status' => Booking::STATUS_PENDING,
        ]);

        Sanctum::actingAs($worker);

        $this->patchJson("/api/worker/bookings/{$booking->id}/status", [
            'status' => Booking::STATUS_CANCELLED,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('cancelled_reason');
    }

    public function test_worker_can_cancel_booking_with_reason(): void
    {
        [$customer, $worker, $service] = $this->bookingActors();

        $booking = Booking::factory()->create([
            'customer_id' => $customer->id,
            'worker_id' => $worker->id,
            'service_id' => $service->id,
            'status' => Booking::STATUS_ACCEPTED,
        ]);

        Sanctum::actingAs($worker);

        $this->patchJson("/api/worker/bookings/{$booking->id}/status", [
            'status' => Booking::STATUS_CANCELLED,
            'cancelled_reason' => 'Emergency repair call, cannot attend this slot.',
        ])
            ->assertOk()
            ->assertJsonPath('data.booking.status', Booking::STATUS_CANCELLED)
            ->assertJsonPath('data.booking.cancelled_by', $worker->id)
            ->assertJsonPath('data.booking.cancelled_reason', 'Emergency repair call, cannot attend this slot.');

        $this->assertDatabaseHas('booking_activities', [
            'booking_id' => $booking->id,
            'actor_id' => $worker->id,
            'from_status' => Booking::STATUS_PENDING,
            'to_status' => Booking::STATUS_CANCELLED,
            'event' => 'booking_cancelled',
            'note' => 'Emergency repair call, cannot attend this slot.',
        ]);
    }

    public function test_customer_cannot_cancel_after_worker_accepts_booking(): void
    {
        [$customer, $worker, $service] = $this->bookingActors();

        $booking = Booking::factory()->create([
            'customer_id' => $customer->id,
            'worker_id' => $worker->id,
            'service_id' => $service->id,
            'status' => Booking::STATUS_ACCEPTED,
        ]);

        Sanctum::actingAs($customer);

        $this->patchJson("/api/customer/bookings/{$booking->id}/cancel", [
            'cancelled_reason' => 'Changed mind.',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_customer_can_review_completed_booking_once(): void
    {
        [$customer, $worker, $service] = $this->bookingActors();

        $booking = Booking::factory()->create([
            'customer_id' => $customer->id,
            'worker_id' => $worker->id,
            'service_id' => $service->id,
            'status' => Booking::STATUS_COMPLETED,
        ]);

        Sanctum::actingAs($customer);

        $this->postJson("/api/customer/bookings/{$booking->id}/review", [
            'rating' => 5,
            'review' => 'Great work and very professional.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.review.rating', 5)
            ->assertJsonPath('data.review.worker_id', $worker->id);

        $this->assertDatabaseHas('reviews', [
            'booking_id' => $booking->id,
            'customer_id' => $customer->id,
            'worker_id' => $worker->id,
            'rating' => 5,
        ]);

        $this->postJson("/api/customer/bookings/{$booking->id}/review", [
            'rating' => 4,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('booking_id');
    }

    public function test_incomplete_booking_cannot_be_reviewed(): void
    {
        [$customer, $worker, $service] = $this->bookingActors();

        $booking = Booking::factory()->create([
            'customer_id' => $customer->id,
            'worker_id' => $worker->id,
            'service_id' => $service->id,
            'status' => Booking::STATUS_ACCEPTED,
        ]);

        Sanctum::actingAs($customer);

        $this->postJson("/api/customer/bookings/{$booking->id}/review", [
            'rating' => 5,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('booking_id');
    }

    public function test_worker_can_view_reviews_and_average_rating(): void
    {
        [$customer, $worker, $service] = $this->bookingActors();

        $booking = Booking::factory()->create([
            'customer_id' => $customer->id,
            'worker_id' => $worker->id,
            'service_id' => $service->id,
            'status' => Booking::STATUS_COMPLETED,
        ]);

        Review::factory()->create([
            'booking_id' => $booking->id,
            'customer_id' => $customer->id,
            'worker_id' => $worker->id,
            'rating' => 4,
            'review' => 'Solid service.',
        ]);

        Sanctum::actingAs($worker);

        $this->getJson('/api/worker/reviews')
            ->assertOk()
            ->assertJsonPath('data.summary.average', 4.0)
            ->assertJsonPath('data.summary.count', 1)
            ->assertJsonPath('data.reviews.0.rating', 4);
    }

    public function test_customer_can_select_final_worker_from_multi_worker_request(): void
    {
        [$customer, $firstWorker, $service] = $this->bookingActors();
        $secondWorker = User::factory()->for(Role::where('slug', 'worker')->firstOrFail())->create();

        WorkerService::factory()->create([
            'worker_id' => $secondWorker->id,
            'service_id' => $service->id,
            'pricing_type' => WorkerService::PricingFixed,
            'price' => 700,
            'is_active' => true,
        ]);

        WorkerSchedule::factory()->create([
            'worker_id' => $secondWorker->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '18:00',
        ]);

        Sanctum::actingAs($customer);

        $bookingId = $this->postJson('/api/customer/bookings', [
            'worker_ids' => [$firstWorker->id, $secondWorker->id],
            'service_id' => $service->id,
            'booking_date' => '2026-05-11',
            'start_time' => '10:00',
            'end_time' => '11:00',
            'address' => '123 Test Street',
            'issue_description' => 'Need a technician.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.booking.status', Booking::STATUS_REQUESTED)
            ->json('data.booking.id');

        Sanctum::actingAs($secondWorker);

        $bookingRequest = BookingRequest::where('booking_id', $bookingId)
            ->where('worker_id', $secondWorker->id)
            ->firstOrFail();

        $this->patchJson("/api/worker/booking-requests/{$bookingRequest->id}/respond", [
            'status' => BookingRequest::STATUS_ACCEPTED,
        ])
            ->assertOk()
            ->assertJsonPath('data.booking_request.status', BookingRequest::STATUS_ACCEPTED);

        Sanctum::actingAs($customer);

        $this->patchJson("/api/customer/bookings/{$bookingId}/select-worker", [
            'booking_request_id' => $bookingRequest->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.booking.status', Booking::STATUS_PENDING)
            ->assertJsonPath('data.booking.worker.id', $secondWorker->id)
            ->assertJsonPath('data.booking.timeline.2.event', 'worker_selected');

        $this->assertDatabaseHas('booking_requests', [
            'id' => $bookingRequest->id,
            'status' => BookingRequest::STATUS_SELECTED,
        ]);

        $this->assertDatabaseHas('booking_requests', [
            'booking_id' => $bookingId,
            'worker_id' => $firstWorker->id,
            'status' => BookingRequest::STATUS_CANCELLED,
        ]);

        $this->assertDatabaseHas('booking_activities', [
            'booking_id' => $bookingId,
            'actor_id' => $secondWorker->id,
            'event' => 'booking_request_accepted',
        ]);
    }

    /**
     * @return array{User, User, Service}
     */
    private function bookingActors(): array
    {
        $this->seed(RoleSeeder::class);

        $customer = User::factory()->for(Role::where('slug', 'customer')->firstOrFail())->create();
        $worker = User::factory()->for(Role::where('slug', 'worker')->firstOrFail())->create();
        $service = Service::factory()->create(['is_active' => true]);

        WorkerService::factory()->create([
            'worker_id' => $worker->id,
            'service_id' => $service->id,
            'pricing_type' => WorkerService::PricingFixed,
            'price' => 500,
            'is_active' => true,
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
