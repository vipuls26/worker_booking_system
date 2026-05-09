<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingRequest;
use App\Models\Role;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestWorker;
use App\Models\User;
use App\Models\WorkerProfile;
use App\Models\WorkerSchedule;
use App\Models\WorkerService;
use App\Notifications\BookingWorkflowNotification;
use App\Notifications\ServiceRequestWorkflowNotification;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookingAutoMatchingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_booking_auto_matches_verified_available_workers(): void
    {
        Notification::fake();
        $this->seed(RoleSeeder::class);

        $customer = $this->customer();
        $service = Service::factory()->create();
        $bookingDate = now()->addDays(3)->toDateString();
        $dayOfWeek = (int) now()->addDays(3)->dayOfWeek;
        $availableWorker = $this->verifiedWorker();
        $blockedWorker = $this->verifiedWorker(['is_blocked' => true]);
        $busyWorker = $this->verifiedWorker();

        $this->attachServiceAndSchedule($availableWorker, $service, $dayOfWeek);
        $this->attachServiceAndSchedule($blockedWorker, $service, $dayOfWeek);
        $this->attachServiceAndSchedule($busyWorker, $service, $dayOfWeek);

        Booking::factory()->create([
            'customer_id' => $customer->id,
            'worker_id' => $busyWorker->id,
            'selected_worker_id' => $busyWorker->id,
            'service_id' => $service->id,
            'booking_date' => $bookingDate,
            'booking_time' => '10:00',
            'start_time' => '10:00',
            'end_time' => '11:00',
            'status' => Booking::STATUS_CONFIRMED,
        ]);

        Sanctum::actingAs($customer);

        $this->postJson('/api/customer/bookings', [
            'service_id' => $service->id,
            'booking_date' => $bookingDate,
            'start_time' => '10:00',
            'end_time' => '11:00',
            'address' => '123 Main Road',
            'issue_description' => 'Fan repair needed',
        ])
            ->assertCreated()
            ->assertJsonPath('data.booking.status', Booking::STATUS_REQUESTED)
            ->assertJsonPath('data.booking.worker', null);

        $booking = Booking::query()->latest('id')->firstOrFail();

        $this->assertSame(Booking::STATUS_REQUESTED, $booking->status);
        $this->assertNull($booking->worker_id);
        $this->assertDatabaseHas('booking_requests', [
            'booking_id' => $booking->id,
            'worker_id' => $availableWorker->id,
            'status' => BookingRequest::STATUS_PENDING,
        ]);
        $this->assertDatabaseMissing('booking_requests', [
            'booking_id' => $booking->id,
            'worker_id' => $blockedWorker->id,
        ]);
        $this->assertDatabaseMissing('booking_requests', [
            'booking_id' => $booking->id,
            'worker_id' => $busyWorker->id,
        ]);
    }

    public function test_customer_selecting_worker_confirms_booking_and_closes_other_requests(): void
    {
        Notification::fake();
        $this->seed(RoleSeeder::class);

        $customer = $this->customer();
        $service = Service::factory()->create();
        $bookingDate = now()->addDays(4)->toDateString();
        $dayOfWeek = (int) now()->addDays(4)->dayOfWeek;
        $firstWorker = $this->verifiedWorker();
        $secondWorker = $this->verifiedWorker();

        $this->attachServiceAndSchedule($firstWorker, $service, $dayOfWeek);
        $this->attachServiceAndSchedule($secondWorker, $service, $dayOfWeek);

        Sanctum::actingAs($customer);

        $this->postJson('/api/customer/bookings', [
            'service_id' => $service->id,
            'booking_date' => $bookingDate,
            'start_time' => '12:00',
            'end_time' => '13:00',
            'address' => '456 Service Street',
            'issue_description' => 'AC service needed',
        ])->assertCreated();

        $booking = Booking::query()->latest('id')->firstOrFail();

        $booking->bookingRequests()->update([
            'status' => BookingRequest::STATUS_ACCEPTED,
            'responded_at' => now(),
        ]);

        $selectedRequest = $booking->bookingRequests()->where('worker_id', $firstWorker->id)->firstOrFail();

        $this->patchJson("/api/customer/bookings/{$booking->id}/select-worker", [
            'booking_request_id' => $selectedRequest->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.booking.status', Booking::STATUS_CONFIRMED)
            ->assertJsonPath('data.booking.selected_worker_id', $firstWorker->id);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'worker_id' => $firstWorker->id,
            'selected_worker_id' => $firstWorker->id,
            'status' => Booking::STATUS_CONFIRMED,
        ]);
        $this->assertDatabaseHas('booking_requests', [
            'id' => $selectedRequest->id,
            'status' => BookingRequest::STATUS_SELECTED,
        ]);
        $this->assertDatabaseHas('booking_requests', [
            'booking_id' => $booking->id,
            'worker_id' => $secondWorker->id,
            'status' => BookingRequest::STATUS_AUTO_CANCELLED,
        ]);
    }

    public function test_direct_worker_request_is_confirmed_when_worker_accepts(): void
    {
        Notification::fake();
        $this->seed(RoleSeeder::class);

        $customer = $this->customer();
        $worker = $this->verifiedWorker();
        $service = Service::factory()->create();
        $bookingDate = now()->addDays(5)->toDateString();
        $dayOfWeek = (int) now()->addDays(5)->dayOfWeek;

        $this->attachServiceAndSchedule($worker, $service, $dayOfWeek);

        Sanctum::actingAs($customer);

        $serviceRequestId = $this->postJson('/api/customer/bookings', [
            'worker_id' => $worker->id,
            'service_id' => $service->id,
            'booking_date' => $bookingDate,
            'start_time' => '12:00',
            'end_time' => '13:00',
            'address' => '456 Service Street',
            'issue_description' => 'AC service needed',
        ])
            ->assertCreated()
            ->assertJsonPath('data.booking.status', ServiceRequest::STATUS_OPEN)
            ->json('data.booking.id');

        $serviceRequestWorker = ServiceRequestWorker::query()
            ->where('service_request_id', $serviceRequestId)
            ->where('worker_id', $worker->id)
            ->firstOrFail();

        Notification::assertSentTo(
            $worker,
            fn (ServiceRequestWorkflowNotification $notification): bool => $notification->toArray($worker)['event'] === 'service_request_received'
                && $notification->toArray($worker)['title'] === 'New service request',
        );

        Sanctum::actingAs($worker);

        $this->patchJson("/api/worker/booking-requests/{$serviceRequestWorker->id}/respond", [
            'status' => ServiceRequestWorker::STATUS_ACCEPTED,
        ])
            ->assertOk()
            ->assertJsonPath('data.booking_request.status', ServiceRequestWorker::STATUS_SELECTED)
            ->assertJsonPath('data.booking_request.service_request.status', ServiceRequest::STATUS_WORKER_SELECTED)
            ->assertJsonPath('data.booking_request.service_request.selected_worker_id', $worker->id);

        Notification::assertSentTo(
            $customer,
            fn (BookingWorkflowNotification $notification): bool => $notification->toArray($customer)['event'] === 'booking_accepted'
                && $notification->toArray($customer)['title'] === 'Worker accepted your request',
        );

        $this->assertDatabaseHas('service_requests', [
            'id' => $serviceRequestId,
            'selected_worker_id' => $worker->id,
            'status' => ServiceRequest::STATUS_WORKER_SELECTED,
        ]);
        $this->assertDatabaseHas('service_request_workers', [
            'id' => $serviceRequestWorker->id,
            'status' => ServiceRequestWorker::STATUS_SELECTED,
        ]);
        $this->assertDatabaseHas('bookings', [
            'service_request_id' => $serviceRequestId,
            'worker_id' => $worker->id,
            'selected_worker_id' => $worker->id,
            'status' => Booking::STATUS_CONFIRMED,
        ]);
    }

    private function customer(): User
    {
        return User::factory()
            ->for(Role::where('slug', 'customer')->firstOrFail())
            ->create(['is_verified' => true]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function verifiedWorker(array $overrides = []): User
    {
        $worker = User::factory()
            ->for(Role::where('slug', 'worker')->firstOrFail())
            ->create([
                'is_verified' => true,
                ...$overrides,
            ]);

        WorkerProfile::factory()->create([
            'user_id' => $worker->id,
            'is_verified' => true,
        ]);

        return $worker;
    }

    private function attachServiceAndSchedule(User $worker, Service $service, int $dayOfWeek): void
    {
        WorkerService::factory()->create([
            'worker_id' => $worker->id,
            'service_id' => $service->id,
            'is_active' => true,
            'approval_status' => WorkerService::StatusApproved,
        ]);

        WorkerSchedule::factory()->create([
            'worker_id' => $worker->id,
            'day_of_week' => $dayOfWeek,
            'start_time' => '09:00',
            'end_time' => '18:00',
            'is_off_day' => false,
        ]);
    }
}
