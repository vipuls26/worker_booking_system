<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Review;
use App\Models\Role;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestWorker;
use App\Models\User;
use App\Models\WorkerSchedule;
use App\Models\WorkerService;
use App\Notifications\ServiceRequestWorkflowNotification;
use App\Services\Booking\AvailabilityService;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
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
            ->assertJsonPath('data.booking.status', ServiceRequest::STATUS_WORKER_SELECTED)
            ->assertJsonPath('data.booking.total_amount', '500.00')
            ->assertJsonPath('data.booking.timeline.0.event', 'worker_selected');

        $serviceRequest = ServiceRequest::query()->latest('id')->firstOrFail();
        $booking = Booking::query()->latest('id')->firstOrFail();

        $this->assertDatabaseHas('service_requests', [
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'selected_worker_id' => $worker->id,
            'status' => ServiceRequest::STATUS_WORKER_SELECTED,
        ]);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'service_request_id' => $serviceRequest->id,
            'worker_id' => $worker->id,
            'status' => Booking::STATUS_CONFIRMED,
        ]);

        $this->assertDatabaseHas('service_request_workers', [
            'service_request_id' => $serviceRequest->id,
            'worker_id' => $worker->id,
            'status' => ServiceRequestWorker::STATUS_SELECTED,
        ]);

        $this->assertDatabaseHas('booking_activities', [
            'booking_id' => $booking->id,
            'to_status' => Booking::STATUS_CONFIRMED,
            'event' => 'worker_selected',
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

    public function test_booking_creation_rejects_overlapping_booking_from_another_service(): void
    {
        [$customer, $worker, $service] = $this->bookingActors();
        $otherService = Service::factory()->create(['is_active' => true]);

        Booking::factory()->create([
            'customer_id' => $customer->id,
            'worker_id' => $worker->id,
            'service_id' => $otherService->id,
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

    public function test_booking_creation_rejects_worker_off_day(): void
    {
        [$customer, $worker, $service] = $this->bookingActors();

        WorkerSchedule::factory()->offDay()->create([
            'worker_id' => $worker->id,
            'day_of_week' => 1,
        ]);

        Sanctum::actingAs($customer);

        $this->postJson('/api/customer/bookings', [
            'worker_id' => $worker->id,
            'service_id' => $service->id,
            'booking_date' => '2026-05-11',
            'start_time' => '10:00',
            'end_time' => '11:00',
            'address' => '123 Test Street',
            'issue_description' => 'Need help.',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('start_time')
            ->assertJsonPath('errors.start_time.0', 'This worker is not available on the selected day.');
    }

    public function test_booking_creation_rejects_time_outside_worker_schedule(): void
    {
        [$customer, $worker, $service] = $this->bookingActors();

        Sanctum::actingAs($customer);

        $this->postJson('/api/customer/bookings', [
            'worker_id' => $worker->id,
            'service_id' => $service->id,
            'booking_date' => '2026-05-11',
            'start_time' => '08:00',
            'end_time' => '09:00',
            'address' => '123 Test Street',
            'issue_description' => 'Need help.',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('start_time')
            ->assertJsonPath('errors.start_time.0', 'This worker is not scheduled during the selected time.');
    }

    public function test_customer_can_create_booking_request_for_a_later_time_today(): void
    {
        CarbonImmutable::setTestNow('2026-05-11 10:30:00');

        try {
            [$customer, $worker, $service] = $this->bookingActors();
            Sanctum::actingAs($customer);

            $this->postJson('/api/customer/bookings', [
                'worker_id' => $worker->id,
                'service_id' => $service->id,
                'booking_date' => '2026-05-11',
                'start_time' => '11:00',
                'end_time' => '12:00',
                'address' => '123 Test Street',
                'issue_description' => 'Need help later today.',
            ])
                ->assertCreated()
                ->assertJsonPath('success', true);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_customer_can_create_booking_request_with_a_fifteen_minute_offset(): void
    {
        [$customer, $worker, $service] = $this->bookingActors();
        Sanctum::actingAs($customer);

        $this->postJson('/api/customer/bookings', [
            'worker_id' => $worker->id,
            'service_id' => $service->id,
            'booking_date' => '2026-05-11',
            'start_time' => '10:15',
            'end_time' => '11:15',
            'address' => '123 Test Street',
            'issue_description' => 'Need a slightly later arrival.',
        ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.booking.start_time', '10:15')
            ->assertJsonPath('data.booking.end_time', '11:15');
    }

    public function test_customer_cannot_create_duplicate_active_request_for_same_slot(): void
    {
        [$customer, $worker, $service] = $this->bookingActors();
        $this->addMatchingWorkerForService($service);

        Sanctum::actingAs($customer);

        $payload = [
            'service_id' => $service->id,
            'booking_date' => '2026-05-11',
            'start_time' => '10:00',
            'end_time' => '11:00',
            'address' => '123 Test Street',
            'issue_description' => 'Need a technician.',
        ];

        $this->postJson('/api/customer/bookings', $payload)
            ->assertCreated()
            ->assertJsonPath('data.booking.status', ServiceRequest::STATUS_OPEN);

        $this->postJson('/api/customer/bookings', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('booking_date')
            ->assertJsonPath('errors.booking_date.0', 'You already have an active booking request for this slot.');

        $this->assertDatabaseCount('service_requests', 1);
    }

    public function test_customer_booking_create_reuses_first_response_for_same_idempotency_key(): void
    {
        [$customer, $worker, $service] = $this->bookingActors();
        $this->addMatchingWorkerForService($service);

        Sanctum::actingAs($customer);

        $payload = [
            'service_id' => $service->id,
            'booking_date' => '2026-05-11',
            'start_time' => '10:00',
            'end_time' => '11:00',
            'address' => '123 Test Street',
            'issue_description' => 'Need a technician.',
        ];

        $headers = ['X-Idempotency-Key' => 'customer-booking-create-test'];

        $firstResponse = $this->withHeaders($headers)
            ->postJson('/api/customer/bookings', $payload)
            ->assertCreated()
            ->assertJsonPath('data.booking.status', ServiceRequest::STATUS_OPEN);

        $firstBookingId = $firstResponse->json('data.booking.id');

        $this->withHeaders($headers)
            ->postJson('/api/customer/bookings', $payload)
            ->assertCreated()
            ->assertJsonPath('data.booking.id', $firstBookingId);

        $this->assertDatabaseCount('service_requests', 1);
    }

    public function test_customer_worker_selection_reuses_first_response_for_same_idempotency_key(): void
    {
        [$customer, $firstWorker, $service] = $this->bookingActors();
        $secondWorker = $this->addMatchingWorkerForService($service);

        Sanctum::actingAs($customer);

        $serviceRequestId = $this->postJson('/api/customer/bookings', [
            'service_id' => $service->id,
            'booking_date' => '2026-05-11',
            'start_time' => '10:00',
            'end_time' => '11:00',
            'address' => '123 Test Street',
            'issue_description' => 'Need a technician.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.booking.status', ServiceRequest::STATUS_OPEN)
            ->json('data.booking.id');

        Sanctum::actingAs($secondWorker);

        $workerRequest = ServiceRequestWorker::query()
            ->where('service_request_id', $serviceRequestId)
            ->where('worker_id', $secondWorker->id)
            ->firstOrFail();

        $this->patchJson("/api/worker/booking-requests/{$workerRequest->id}/respond", [
            'status' => ServiceRequestWorker::STATUS_ACCEPTED,
        ])->assertOk();

        Sanctum::actingAs($customer);

        $headers = ['X-Idempotency-Key' => 'customer-booking-selection-test'];

        $firstResponse = $this->withHeaders($headers)
            ->patchJson("/api/customer/bookings/{$serviceRequestId}/select-worker", [
                'worker_request_id' => $workerRequest->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.booking.status', ServiceRequest::STATUS_WORKER_SELECTED)
            ->assertJsonPath('data.booking.worker.id', $secondWorker->id);

        $bookingId = $firstResponse->json('data.booking.booking_id');

        $this->withHeaders($headers)
            ->patchJson("/api/customer/bookings/{$serviceRequestId}/select-worker", [
                'worker_request_id' => $workerRequest->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.booking.booking_id', $bookingId);

        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseHas('service_request_workers', [
            'service_request_id' => $serviceRequestId,
            'worker_id' => $firstWorker->id,
            'status' => ServiceRequestWorker::STATUS_NOT_SELECTED,
        ]);
    }

    public function test_customer_cannot_create_booking_request_for_a_past_time_today(): void
    {
        CarbonImmutable::setTestNow('2026-05-11 10:30:00');

        try {
            [$customer, $worker, $service] = $this->bookingActors();
            Sanctum::actingAs($customer);

            $this->postJson('/api/customer/bookings', [
                'worker_id' => $worker->id,
                'service_id' => $service->id,
                'booking_date' => '2026-05-11',
                'start_time' => '10:00',
                'end_time' => '11:00',
                'address' => '123 Test Street',
                'issue_description' => 'Need urgent help.',
            ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('start_time')
                ->assertJsonPath('errors.start_time.0', 'Please choose the current time or a future time.');
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_direct_hourly_booking_must_match_the_worker_required_hours(): void
    {
        [$customer, $worker, $service] = $this->bookingActors();

        WorkerService::query()
            ->where('worker_id', $worker->id)
            ->where('service_id', $service->id)
            ->update([
                'pricing_type' => WorkerService::PricingHourly,
                'minimum_hours' => 2,
                'price' => 200,
            ]);

        Sanctum::actingAs($customer);

        $this->postJson('/api/customer/bookings', [
            'worker_id' => $worker->id,
            'service_id' => $service->id,
            'booking_date' => '2026-05-11',
            'start_time' => '10:00',
            'duration_minutes' => 180,
            'end_time' => '13:00',
            'address' => '123 Test Street',
            'issue_description' => 'Need help.',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('duration_minutes')
            ->assertJsonPath('errors.duration_minutes.0', 'This worker requires exactly 2 hours for this hourly service.');
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
        CarbonImmutable::setTestNow('2026-05-11 10:00:00');

        try {
            [$customer, $worker, $service] = $this->bookingActors();

            $booking = Booking::factory()->create([
                'customer_id' => $customer->id,
                'worker_id' => $worker->id,
                'service_id' => $service->id,
                'booking_date' => '2026-05-11',
                'start_time' => '09:00',
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
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_worker_can_start_booking_at_scheduled_time(): void
    {
        CarbonImmutable::setTestNow('2026-05-11 10:00:00');

        try {
            [$customer, $worker, $service] = $this->bookingActors();

            $booking = Booking::factory()->create([
                'customer_id' => $customer->id,
                'worker_id' => $worker->id,
                'service_id' => $service->id,
                'booking_date' => '2026-05-11',
                'start_time' => '10:00',
                'status' => Booking::STATUS_CONFIRMED,
            ]);

            Sanctum::actingAs($worker);

            $this->patchJson("/api/worker/bookings/{$booking->id}/start")
                ->assertOk()
                ->assertJsonPath('success', true)
                ->assertJsonPath('message', 'Booking started successfully')
                ->assertJsonPath('data.booking.status', Booking::STATUS_IN_PROGRESS);

            $this->assertDatabaseHas('booking_activities', [
                'booking_id' => $booking->id,
                'actor_id' => $worker->id,
                'from_status' => Booking::STATUS_CONFIRMED,
                'to_status' => Booking::STATUS_IN_PROGRESS,
                'event' => 'work_started',
            ]);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_worker_booking_status_update_reuses_first_response_for_same_idempotency_key(): void
    {
        [$customer, $worker, $service] = $this->bookingActors();

        $booking = Booking::factory()->create([
            'customer_id' => $customer->id,
            'worker_id' => $worker->id,
            'service_id' => $service->id,
            'status' => Booking::STATUS_PENDING,
        ]);

        Sanctum::actingAs($worker);

        $headers = ['X-Idempotency-Key' => 'worker-booking-status-update-test'];

        $this->withHeaders($headers)
            ->patchJson("/api/worker/bookings/{$booking->id}/status", [
                'status' => Booking::STATUS_ACCEPTED,
            ])
            ->assertOk()
            ->assertJsonPath('data.booking.status', Booking::STATUS_ACCEPTED);

        $this->withHeaders($headers)
            ->patchJson("/api/worker/bookings/{$booking->id}/status", [
                'status' => Booking::STATUS_ACCEPTED,
            ])
            ->assertOk()
            ->assertJsonPath('data.booking.status', Booking::STATUS_ACCEPTED);
    }

    public function test_worker_cannot_start_booking_before_scheduled_time(): void
    {
        CarbonImmutable::setTestNow('2026-05-11 09:30:00');

        try {
            [$customer, $worker, $service] = $this->bookingActors();

            $booking = Booking::factory()->create([
                'customer_id' => $customer->id,
                'worker_id' => $worker->id,
                'service_id' => $service->id,
                'booking_date' => '2026-05-11',
                'start_time' => '10:00',
                'status' => Booking::STATUS_CONFIRMED,
            ]);

            Sanctum::actingAs($worker);

            $this->patchJson("/api/worker/bookings/{$booking->id}/start")
                ->assertUnprocessable()
                ->assertJsonValidationErrors('status')
                ->assertJsonPath('errors.status.0', 'This booking can only be started on or after the scheduled start time.');
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_other_worker_cannot_start_assigned_booking(): void
    {
        [$customer, $assignedWorker, $service] = $this->bookingActors();
        $otherWorker = User::factory()->for(Role::where('slug', 'worker')->firstOrFail())->create();

        $booking = Booking::factory()->create([
            'customer_id' => $customer->id,
            'worker_id' => $assignedWorker->id,
            'service_id' => $service->id,
            'status' => Booking::STATUS_CONFIRMED,
        ]);

        Sanctum::actingAs($otherWorker);

        $this->patchJson("/api/worker/bookings/{$booking->id}/start")
            ->assertForbidden();
    }

    public function test_worker_cannot_start_cancelled_booking(): void
    {
        [$customer, $worker, $service] = $this->bookingActors();

        $booking = Booking::factory()->create([
            'customer_id' => $customer->id,
            'worker_id' => $worker->id,
            'service_id' => $service->id,
            'status' => Booking::STATUS_CANCELLED,
        ]);

        Sanctum::actingAs($worker);

        $this->patchJson("/api/worker/bookings/{$booking->id}/start")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status')
            ->assertJsonPath('errors.status.0', 'Cancelled bookings cannot be started.');
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
            ->assertJsonPath('data.worker_request.status', ServiceRequestWorker::STATUS_CANCELLED)
            ->assertJsonPath('data.worker_request.response_reason', 'Already booked with another customer at this time.');

        $this->assertDatabaseHas('service_request_workers', [
            'id' => $bookingRequest->id,
            'status' => ServiceRequestWorker::STATUS_CANCELLED,
            'response_reason' => 'Already booked with another customer at this time.',
        ]);
    }

    public function test_worker_booking_request_response_reuses_first_response_for_same_idempotency_key(): void
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

        $headers = ['X-Idempotency-Key' => 'worker-booking-request-response-test'];

        $this->withHeaders($headers)
            ->patchJson("/api/worker/booking-requests/{$bookingRequest->id}/respond", [
                'status' => ServiceRequestWorker::STATUS_CANCELLED,
                'response_reason' => 'Already booked with another customer at this time.',
            ])
            ->assertOk()
            ->assertJsonPath('data.worker_request.status', ServiceRequestWorker::STATUS_CANCELLED);

        $this->withHeaders($headers)
            ->patchJson("/api/worker/booking-requests/{$bookingRequest->id}/respond", [
                'status' => ServiceRequestWorker::STATUS_CANCELLED,
                'response_reason' => 'Already booked with another customer at this time.',
            ])
            ->assertOk()
            ->assertJsonPath('data.worker_request.status', ServiceRequestWorker::STATUS_CANCELLED);
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
            ->assertJsonPath('data.worker_request.status', ServiceRequestWorker::STATUS_SELECTED);

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

    public function test_customer_is_notified_when_worker_accepts_multi_worker_request(): void
    {
        Notification::fake();

        [$customer, $worker, $service] = $this->bookingActors();
        $secondWorker = User::factory()->for(Role::where('slug', 'worker')->firstOrFail())->create();

        WorkerService::factory()->create([
            'worker_id' => $secondWorker->id,
            'service_id' => $service->id,
            'pricing_type' => WorkerService::PricingFixed,
            'price' => 550,
            'is_active' => true,
        ]);

        WorkerSchedule::factory()->create([
            'worker_id' => $secondWorker->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '18:00',
        ]);

        $serviceRequest = ServiceRequest::factory()->create([
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'requested_date' => '2026-05-11',
            'start_time' => '10:00',
            'end_time' => '11:00',
            'address' => '123 Test Street',
            'description' => 'Need a technician.',
            'status' => ServiceRequest::STATUS_OPEN,
        ]);

        $bookingRequest = ServiceRequestWorker::factory()->create([
            'service_request_id' => $serviceRequest->id,
            'worker_id' => $worker->id,
            'status' => ServiceRequestWorker::STATUS_PENDING,
            'quoted_price' => 500,
        ]);

        ServiceRequestWorker::factory()->create([
            'service_request_id' => $serviceRequest->id,
            'worker_id' => $secondWorker->id,
            'status' => ServiceRequestWorker::STATUS_PENDING,
            'quoted_price' => 550,
        ]);

        Sanctum::actingAs($worker);

        $this->patchJson("/api/worker/booking-requests/{$bookingRequest->id}/respond", [
            'status' => ServiceRequestWorker::STATUS_ACCEPTED,
        ])->assertOk();

        Notification::assertSentTo(
            $customer,
            ServiceRequestWorkflowNotification::class,
            function (ServiceRequestWorkflowNotification $notification) use ($customer): bool {
                return $notification->toArray($customer)['event'] === 'service_request_accepted';
            }
        );
    }

    public function test_customer_gets_clear_notification_when_all_workers_decline_request(): void
    {
        Notification::fake();

        [$customer, $firstWorker, $service] = $this->bookingActors();
        $secondWorker = $this->addMatchingWorkerForService($service);

        $serviceRequest = ServiceRequest::factory()->create([
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'requested_date' => '2026-05-11',
            'start_time' => '10:00',
            'end_time' => '11:00',
            'address' => '123 Test Street',
            'description' => 'Need a technician.',
            'status' => ServiceRequest::STATUS_OPEN,
        ]);

        $firstWorkerRequest = ServiceRequestWorker::factory()->create([
            'service_request_id' => $serviceRequest->id,
            'worker_id' => $firstWorker->id,
            'status' => ServiceRequestWorker::STATUS_PENDING,
        ]);

        $secondWorkerRequest = ServiceRequestWorker::factory()->create([
            'service_request_id' => $serviceRequest->id,
            'worker_id' => $secondWorker->id,
            'status' => ServiceRequestWorker::STATUS_PENDING,
        ]);

        Sanctum::actingAs($firstWorker);

        $this->patchJson("/api/worker/booking-requests/{$firstWorkerRequest->id}/respond", [
            'status' => ServiceRequestWorker::STATUS_REJECTED,
        ])->assertOk();

        Sanctum::actingAs($secondWorker);

        $this->patchJson("/api/worker/booking-requests/{$secondWorkerRequest->id}/respond", [
            'status' => ServiceRequestWorker::STATUS_CANCELLED,
            'response_reason' => 'No longer available for this slot.',
        ])->assertOk();

        $this->assertDatabaseHas('service_requests', [
            'id' => $serviceRequest->id,
            'status' => ServiceRequest::STATUS_CANCELLED,
        ]);

        Notification::assertSentTo(
            $customer,
            ServiceRequestWorkflowNotification::class,
            function (ServiceRequestWorkflowNotification $notification) use ($customer): bool {
                $payload = $notification->toArray($customer);

                return $payload['event'] === 'service_request_unavailable'
                    && $payload['title'] === 'No workers available';
            }
        );
    }

    public function test_customer_cancelling_open_service_request_notifies_related_workers(): void
    {
        Notification::fake();

        [$customer, $worker, $service] = $this->bookingActors();

        $serviceRequest = ServiceRequest::factory()->create([
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'requested_date' => '2026-05-11',
            'start_time' => '10:00',
            'end_time' => '11:00',
            'address' => '123 Test Street',
            'description' => 'Need a technician.',
            'status' => ServiceRequest::STATUS_OPEN,
        ]);

        ServiceRequestWorker::factory()->create([
            'service_request_id' => $serviceRequest->id,
            'worker_id' => $worker->id,
            'status' => ServiceRequestWorker::STATUS_PENDING,
        ]);

        Sanctum::actingAs($customer);

        $this->patchJson("/api/customer/bookings/{$serviceRequest->id}/cancel", [
            'cancelled_reason' => 'No longer needed.',
        ])->assertOk();

        Notification::assertSentTo(
            $worker,
            ServiceRequestWorkflowNotification::class,
            function (ServiceRequestWorkflowNotification $notification) use ($worker): bool {
                return $notification->toArray($worker)['event'] === 'service_request_cancelled';
            }
        );
    }

    public function test_overlapping_pending_requests_move_to_awaiting_reschedule_after_worker_accepts(): void
    {
        Notification::fake();

        [$firstCustomer, $worker, $service] = $this->bookingActors();
        $secondCustomer = User::factory()->for(Role::where('slug', 'customer')->firstOrFail())->create();

        $acceptedServiceRequest = ServiceRequest::factory()->create([
            'customer_id' => $firstCustomer->id,
            'service_id' => $service->id,
            'requested_date' => '2026-05-11',
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => ServiceRequest::STATUS_OPEN,
        ]);
        $acceptedWorkerRequest = ServiceRequestWorker::factory()->create([
            'service_request_id' => $acceptedServiceRequest->id,
            'worker_id' => $worker->id,
            'status' => ServiceRequestWorker::STATUS_PENDING,
            'quoted_price' => 500,
        ]);

        $conflictingServiceRequest = ServiceRequest::factory()->create([
            'customer_id' => $secondCustomer->id,
            'service_id' => $service->id,
            'requested_date' => '2026-05-11',
            'start_time' => '10:30',
            'end_time' => '11:30',
            'status' => ServiceRequest::STATUS_OPEN,
        ]);
        $conflictingWorkerRequest = ServiceRequestWorker::factory()->create([
            'service_request_id' => $conflictingServiceRequest->id,
            'worker_id' => $worker->id,
            'status' => ServiceRequestWorker::STATUS_PENDING,
        ]);

        Sanctum::actingAs($worker);

        $this->patchJson("/api/worker/booking-requests/{$acceptedWorkerRequest->id}/respond", [
            'status' => ServiceRequestWorker::STATUS_ACCEPTED,
        ])
            ->assertOk()
            ->assertJsonPath('data.worker_request.status', ServiceRequestWorker::STATUS_SELECTED);

        $this->assertDatabaseHas('service_request_workers', [
            'id' => $conflictingWorkerRequest->id,
            'status' => ServiceRequestWorker::STATUS_AWAITING_RESCHEDULE,
            'response_reason' => 'Worker is no longer available for selected time slot.',
        ]);

        Notification::assertSentTo(
            $secondCustomer,
            ServiceRequestWorkflowNotification::class,
            function (ServiceRequestWorkflowNotification $notification) use ($secondCustomer): bool {
                return $notification->toArray($secondCustomer)['event'] === 'service_request_awaiting_reschedule';
            }
        );
    }

    public function test_worker_pending_request_list_excludes_awaiting_reschedule_requests(): void
    {
        [$customer, $worker, $service] = $this->bookingActors();

        $pendingServiceRequest = ServiceRequest::factory()->create([
            'customer_id' => $customer->id,
            'service_id' => $service->id,
        ]);
        ServiceRequestWorker::factory()->create([
            'service_request_id' => $pendingServiceRequest->id,
            'worker_id' => $worker->id,
            'status' => ServiceRequestWorker::STATUS_PENDING,
        ]);

        $awaitingRescheduleServiceRequest = ServiceRequest::factory()->create([
            'customer_id' => $customer->id,
            'service_id' => $service->id,
        ]);
        ServiceRequestWorker::factory()->create([
            'service_request_id' => $awaitingRescheduleServiceRequest->id,
            'worker_id' => $worker->id,
            'status' => ServiceRequestWorker::STATUS_AWAITING_RESCHEDULE,
        ]);

        Sanctum::actingAs($worker);

        $this->getJson('/api/worker/booking-requests')
            ->assertOk()
            ->assertJsonCount(1, 'data.worker_requests')
            ->assertJsonPath('data.worker_requests.0.status', ServiceRequestWorker::STATUS_PENDING);
    }

    public function test_worker_booking_request_list_supports_search(): void
    {
        [$customer, $worker, $service] = $this->bookingActors();

        $matchingServiceRequest = ServiceRequest::factory()->create([
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'address' => '42 Market Road',
            'description' => 'Kitchen sink leaking badly.',
        ]);

        $matchingWorkerRequest = ServiceRequestWorker::factory()->create([
            'service_request_id' => $matchingServiceRequest->id,
            'worker_id' => $worker->id,
            'status' => ServiceRequestWorker::STATUS_PENDING,
        ]);

        $otherServiceRequest = ServiceRequest::factory()->create([
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'address' => '8 Lake View',
            'description' => 'Garden cleanup request.',
        ]);

        ServiceRequestWorker::factory()->create([
            'service_request_id' => $otherServiceRequest->id,
            'worker_id' => $worker->id,
            'status' => ServiceRequestWorker::STATUS_PENDING,
        ]);

        Sanctum::actingAs($worker);

        $this->getJson('/api/worker/booking-requests?search=Kitchen')
            ->assertOk()
            ->assertJsonCount(1, 'data.worker_requests')
            ->assertJsonPath('data.worker_requests.0.id', $matchingWorkerRequest->id);
    }

    public function test_customer_can_reschedule_request_that_is_awaiting_reschedule(): void
    {
        [$customer, $worker, $service] = $this->bookingActors();

        $serviceRequest = ServiceRequest::factory()->create([
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'requested_date' => '2026-05-11',
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => ServiceRequest::STATUS_OPEN,
        ]);
        $workerRequest = ServiceRequestWorker::factory()->create([
            'service_request_id' => $serviceRequest->id,
            'worker_id' => $worker->id,
            'status' => ServiceRequestWorker::STATUS_AWAITING_RESCHEDULE,
            'response_reason' => 'Worker is no longer available for selected time slot.',
            'responded_at' => now(),
        ]);

        Sanctum::actingAs($customer);

        $this->patchJson("/api/customer/bookings/{$serviceRequest->id}/reschedule", [
            'booking_date' => '2026-05-12',
            'start_time' => '12:00',
            'end_time' => '13:00',
            'duration_minutes' => 60,
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.booking.requested_date', '2026-05-12')
            ->assertJsonPath('data.booking.start_time', '12:00')
            ->assertJsonPath('data.booking.end_time', '13:00');

        $this->assertDatabaseHas('service_requests', [
            'id' => $serviceRequest->id,
            'requested_date' => '2026-05-12',
            'start_time' => '12:00',
            'end_time' => '13:00',
        ]);

        $this->assertDatabaseHas('service_request_workers', [
            'id' => $workerRequest->id,
            'status' => ServiceRequestWorker::STATUS_PENDING,
            'response_reason' => null,
        ]);
    }

    public function test_customer_cannot_reschedule_request_to_a_past_date(): void
    {
        CarbonImmutable::setTestNow('2026-05-11 10:30:00');

        try {
            [$customer, $worker, $service] = $this->bookingActors();

            $serviceRequest = ServiceRequest::factory()->create([
                'customer_id' => $customer->id,
                'service_id' => $service->id,
                'requested_date' => '2026-05-11',
                'start_time' => '12:00',
                'end_time' => '13:00',
                'status' => ServiceRequest::STATUS_OPEN,
            ]);
            ServiceRequestWorker::factory()->create([
                'service_request_id' => $serviceRequest->id,
                'worker_id' => $worker->id,
                'status' => ServiceRequestWorker::STATUS_AWAITING_RESCHEDULE,
                'response_reason' => 'Worker is no longer available for selected time slot.',
                'responded_at' => now(),
            ]);

            Sanctum::actingAs($customer);

            $this->patchJson("/api/customer/bookings/{$serviceRequest->id}/reschedule", [
                'booking_date' => '2026-05-10',
                'start_time' => '12:00',
                'end_time' => '13:00',
                'duration_minutes' => 60,
            ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('booking_date')
                ->assertJsonPath('errors.booking_date.0', 'The booking date must be today or a future date.');
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_customer_cannot_reschedule_request_to_a_past_time_today(): void
    {
        CarbonImmutable::setTestNow('2026-05-11 10:30:00');

        try {
            [$customer, $worker, $service] = $this->bookingActors();

            $serviceRequest = ServiceRequest::factory()->create([
                'customer_id' => $customer->id,
                'service_id' => $service->id,
                'requested_date' => '2026-05-11',
                'start_time' => '12:00',
                'end_time' => '13:00',
                'status' => ServiceRequest::STATUS_OPEN,
            ]);
            ServiceRequestWorker::factory()->create([
                'service_request_id' => $serviceRequest->id,
                'worker_id' => $worker->id,
                'status' => ServiceRequestWorker::STATUS_AWAITING_RESCHEDULE,
                'response_reason' => 'Worker is no longer available for selected time slot.',
                'responded_at' => now(),
            ]);

            Sanctum::actingAs($customer);

            $this->patchJson("/api/customer/bookings/{$serviceRequest->id}/reschedule", [
                'booking_date' => '2026-05-11',
                'start_time' => '10:00',
                'end_time' => '11:00',
                'duration_minutes' => 60,
            ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('start_time')
                ->assertJsonPath('errors.start_time.0', 'Please choose the current time or a future time.');
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_customer_cannot_create_booking_more_than_one_month_ahead(): void
    {
        CarbonImmutable::setTestNow('2026-05-11 10:30:00');

        try {
            [$customer, $worker, $service] = $this->bookingActors();
            Sanctum::actingAs($customer);

            $this->postJson('/api/customer/bookings', [
                'worker_id' => $worker->id,
                'service_id' => $service->id,
                'booking_date' => '2026-06-12',
                'start_time' => '10:00',
                'end_time' => '11:00',
                'address' => '123 Test Street',
                'issue_description' => 'AC is not cooling.',
            ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('booking_date')
                ->assertJsonPath('errors.booking_date.0', 'The booking date must be within one month from today.');
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_customer_cannot_reschedule_request_more_than_one_month_ahead(): void
    {
        CarbonImmutable::setTestNow('2026-05-11 10:30:00');

        try {
            [$customer, $worker, $service] = $this->bookingActors();

            $serviceRequest = ServiceRequest::factory()->create([
                'customer_id' => $customer->id,
                'service_id' => $service->id,
                'requested_date' => '2026-05-11',
                'start_time' => '12:00',
                'end_time' => '13:00',
                'status' => ServiceRequest::STATUS_OPEN,
            ]);
            ServiceRequestWorker::factory()->create([
                'service_request_id' => $serviceRequest->id,
                'worker_id' => $worker->id,
                'status' => ServiceRequestWorker::STATUS_AWAITING_RESCHEDULE,
                'response_reason' => 'Worker is no longer available for selected time slot.',
                'responded_at' => now(),
            ]);

            Sanctum::actingAs($customer);

            $this->patchJson("/api/customer/bookings/{$serviceRequest->id}/reschedule", [
                'booking_date' => '2026-06-12',
                'start_time' => '12:00',
                'end_time' => '13:00',
                'duration_minutes' => 60,
            ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('booking_date')
                ->assertJsonPath('errors.booking_date.0', 'The booking date must be within one month from today.');
        } finally {
            CarbonImmutable::setTestNow();
        }
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

    public function test_worker_booking_list_supports_search(): void
    {
        [$customer, $worker, $service] = $this->bookingActors();

        $matchingBooking = Booking::factory()->create([
            'customer_id' => $customer->id,
            'worker_id' => $worker->id,
            'service_id' => $service->id,
            'status' => Booking::STATUS_CONFIRMED,
            'address' => '21 River Street',
            'issue_description' => 'Bathroom tap replacement needed.',
        ]);

        Booking::factory()->create([
            'customer_id' => $customer->id,
            'worker_id' => $worker->id,
            'service_id' => $service->id,
            'status' => Booking::STATUS_COMPLETED,
            'address' => '11 Hill Road',
            'issue_description' => 'Ceiling fan service.',
        ]);

        Sanctum::actingAs($worker);

        $this->getJson('/api/worker/bookings?search=Bathroom')
            ->assertOk()
            ->assertJsonCount(1, 'data.bookings')
            ->assertJsonPath('data.bookings.0.id', $matchingBooking->id);
    }

    public function test_worker_reviews_support_search_and_rating_filters(): void
    {
        [$customer, $worker, $service] = $this->bookingActors();
        $otherCustomer = User::factory()->for(Role::where('slug', 'customer')->firstOrFail())->create([
            'name' => 'Second Customer',
        ]);

        $matchingBooking = Booking::factory()->create([
            'customer_id' => $customer->id,
            'worker_id' => $worker->id,
            'service_id' => $service->id,
            'status' => Booking::STATUS_COMPLETED,
        ]);

        Review::factory()->create([
            'booking_id' => $matchingBooking->id,
            'customer_id' => $customer->id,
            'worker_id' => $worker->id,
            'rating' => 5,
            'review' => 'Excellent plumbing cleanup.',
        ]);

        $otherBooking = Booking::factory()->create([
            'customer_id' => $otherCustomer->id,
            'worker_id' => $worker->id,
            'service_id' => $service->id,
            'status' => Booking::STATUS_COMPLETED,
        ]);

        Review::factory()->create([
            'booking_id' => $otherBooking->id,
            'customer_id' => $otherCustomer->id,
            'worker_id' => $worker->id,
            'rating' => 3,
            'review' => 'Average overall support.',
        ]);

        Sanctum::actingAs($worker);

        $this->getJson('/api/worker/reviews?search=plumbing&rating=5')
            ->assertOk()
            ->assertJsonCount(1, 'data.reviews')
            ->assertJsonPath('data.reviews.0.booking_id', $matchingBooking->id)
            ->assertJsonPath('data.reviews.0.rating', 5);
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

        $serviceRequestId = $this->postJson('/api/customer/bookings', [
            'service_id' => $service->id,
            'booking_date' => '2026-05-11',
            'start_time' => '10:00',
            'end_time' => '11:00',
            'address' => '123 Test Street',
            'issue_description' => 'Need a technician.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.booking.status', ServiceRequest::STATUS_OPEN)
            ->json('data.booking.id');

        Sanctum::actingAs($secondWorker);

        $workerRequest = ServiceRequestWorker::where('service_request_id', $serviceRequestId)
            ->where('worker_id', $secondWorker->id)
            ->firstOrFail();

        $this->patchJson("/api/worker/booking-requests/{$workerRequest->id}/respond", [
            'status' => ServiceRequestWorker::STATUS_ACCEPTED,
        ])
            ->assertOk()
            ->assertJsonPath('data.worker_request.status', ServiceRequestWorker::STATUS_ACCEPTED);

        Sanctum::actingAs($customer);

        $this->patchJson("/api/customer/bookings/{$serviceRequestId}/select-worker", [
            'worker_request_id' => $workerRequest->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.booking.status', ServiceRequest::STATUS_WORKER_SELECTED)
            ->assertJsonPath('data.booking.worker.id', $secondWorker->id)
            ->assertJsonPath('data.booking.timeline.0.event', 'worker_selected');

        $this->assertDatabaseHas('service_request_workers', [
            'id' => $workerRequest->id,
            'status' => ServiceRequestWorker::STATUS_SELECTED,
        ]);

        $this->assertDatabaseHas('service_request_workers', [
            'service_request_id' => $serviceRequestId,
            'worker_id' => $firstWorker->id,
            'status' => ServiceRequestWorker::STATUS_NOT_SELECTED,
        ]);

        $this->assertDatabaseHas('booking_activities', [
            'actor_id' => $customer->id,
            'event' => 'worker_selected',
        ]);
    }

    public function test_worker_acceptance_rejects_overlap_from_another_service(): void
    {
        [$customer, $worker, $service] = $this->bookingActors();
        $otherService = Service::factory()->create(['is_active' => true]);

        Booking::factory()->create([
            'customer_id' => $customer->id,
            'worker_id' => $worker->id,
            'service_id' => $otherService->id,
            'booking_date' => '2026-05-11',
            'booking_time' => '10:00',
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => Booking::STATUS_ACCEPTED,
        ]);

        $serviceRequest = ServiceRequest::factory()->create([
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'requested_date' => '2026-05-11',
            'start_time' => '10:30',
            'end_time' => '11:30',
            'status' => ServiceRequest::STATUS_OPEN,
        ]);

        $bookingRequest = ServiceRequestWorker::factory()->create([
            'service_request_id' => $serviceRequest->id,
            'worker_id' => $worker->id,
            'status' => ServiceRequestWorker::STATUS_PENDING,
        ]);

        Sanctum::actingAs($worker);

        $this->patchJson("/api/worker/booking-requests/{$bookingRequest->id}/respond", [
            'status' => ServiceRequestWorker::STATUS_ACCEPTED,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status')
            ->assertJsonPath('errors.status.0', 'You are no longer available for this booking slot.');
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

    private function addMatchingWorkerForService(Service $service): User
    {
        $worker = User::factory()->for(Role::where('slug', 'worker')->firstOrFail())->create();

        WorkerService::factory()->create([
            'worker_id' => $worker->id,
            'service_id' => $service->id,
            'pricing_type' => WorkerService::PricingFixed,
            'price' => 550,
            'is_active' => true,
        ]);

        WorkerSchedule::factory()->create([
            'worker_id' => $worker->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '18:00',
        ]);

        return $worker;
    }
}
