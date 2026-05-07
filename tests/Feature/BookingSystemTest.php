<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingRequest;
use App\Models\Review;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use App\Models\WorkerSchedule;
use App\Models\WorkerService;
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
            'status' => Booking::STATUS_ACCEPTED,
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
            ->assertJsonValidationErrors('start_time');
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
