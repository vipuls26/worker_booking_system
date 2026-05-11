<?php

namespace App\Services\Booking;

use App\Models\Booking;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Models\WorkerService;
use App\Services\Worker\AvailabilityCheckerService;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class BookAgainService
{
    private const AvailabilityLookaheadDays = 30;

    public function __construct(private readonly AvailabilityCheckerService $availability) {}

    /**
     * Build safe booking form defaults from a completed booking without creating anything.
     *
     * @return array<string, mixed>
     */
    public function prefill(User $customer, ServiceRequest $serviceRequest): array
    {
        $sourceBooking = $this->completedSourceBooking($customer, $serviceRequest);
        $workerService = $this->bookableWorkerService($sourceBooking);
        $availableDate = $this->firstAvailableDate($sourceBooking, $workerService);
        $startTime = (string) ($sourceBooking->start_time ?: $sourceBooking->booking_time);
        $endTime = (string) $sourceBooking->end_time;

        return [
            'source_booking_id' => $sourceBooking->id,
            'worker_id' => $sourceBooking->worker_id,
            'worker_name' => $sourceBooking->worker?->name,
            'service_id' => $sourceBooking->service_id,
            'service_name' => $sourceBooking->service?->name,
            'booking_date' => $availableDate,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'duration_minutes' => $this->durationMinutes($sourceBooking),
            'address' => $sourceBooking->address,
            'issue_description' => $sourceBooking->issue_description ?: $sourceBooking->notes,
            'current_price' => $workerService->price,
            'pricing_type' => $workerService->pricing_type,
        ];
    }

    /**
     * Validate that a submitted booking request is still tied to the completed source booking.
     *
     * @param  array<string, mixed>  $data
     */
    public function assertSourceCanCreate(User $customer, array $data): ?Booking
    {
        // Normal booking creation does not need book-again source checks.
        if (empty($data['recreated_from_booking_id'])) {
            return null;
        }

        $sourceBooking = Booking::query()
            ->with(['worker.workerProfile', 'service'])
            ->find((int) $data['recreated_from_booking_id']);

        // Customers can only reuse a booking they completed with the same worker and service.
        if (! $sourceBooking || $sourceBooking->customer_id !== $customer->id || $sourceBooking->status !== Booking::STATUS_COMPLETED) {
            throw ValidationException::withMessages([
                'recreated_from_booking_id' => ['Only your completed bookings can be booked again.'],
            ]);
        }

        // Book again must preserve the original worker and service pair.
        if (empty($data['worker_id']) || (int) $data['worker_id'] !== $sourceBooking->worker_id || (int) $data['service_id'] !== $sourceBooking->service_id) {
            throw ValidationException::withMessages([
                'worker_id' => ['Book again must use the same worker and service as the completed booking.'],
            ]);
        }

        $this->bookableWorkerService($sourceBooking);

        return $sourceBooking;
    }

    /**
     * Resolve a completed source booking from the service request shown to the customer.
     */
    private function completedSourceBooking(User $customer, ServiceRequest $serviceRequest): Booking
    {
        $sourceBooking = $serviceRequest->loadMissing(['booking.worker.workerProfile', 'booking.service'])->booking;

        // Only completed bookings owned by this customer can seed a repeat booking.
        if (! $sourceBooking || $sourceBooking->customer_id !== $customer->id || $sourceBooking->status !== Booking::STATUS_COMPLETED) {
            throw ValidationException::withMessages([
                'booking' => ['Only your completed bookings can be booked again.'],
            ]);
        }

        return $sourceBooking;
    }

    /**
     * Confirm the original worker still has an approved, active offering for this service.
     */
    private function bookableWorkerService(Booking $sourceBooking): WorkerService
    {
        $workerService = WorkerService::query()
            ->with(['worker.workerProfile', 'service'])
            ->where('worker_id', $sourceBooking->worker_id)
            ->where('service_id', $sourceBooking->service_id)
            ->where('is_active', true)
            ->where('approval_status', WorkerService::StatusApproved)
            ->whereHas('service', fn ($query) => $query->where('is_active', true))
            ->whereHas('worker', function ($query): void {
                $query
                    ->where('is_blocked', false)
                    ->where('is_verified', true)
                    ->whereNotNull('email_verified_at')
                    ->whereHas('role', fn ($roleQuery) => $roleQuery->where('slug', 'worker'))
                    ->whereHas('workerProfile', fn ($profileQuery) => $profileQuery->where('is_verified', true));
            })
            ->first();

        // Customers should know why the repeat booking cannot continue before choosing a date.
        if (! $workerService) {
            throw ValidationException::withMessages([
                'service_id' => ['This worker or service is no longer available for booking.'],
            ]);
        }

        return $workerService;
    }

    /**
     * Choose a valid future date while preserving the old date only when possible.
     */
    private function defaultBookingDate(Booking $sourceBooking): string
    {
        $bookingDate = CarbonImmutable::parse($sourceBooking->booking_date);

        // Past completed bookings need a future default so the form can submit after review.
        if ($bookingDate->isPast()) {
            return now()->addDay()->toDateString();
        }

        return $bookingDate->toDateString();
    }

    /**
     * Find the first future day with at least one open slot for the same worker and service.
     */
    private function firstAvailableDate(Booking $sourceBooking, WorkerService $workerService): string
    {
        $durationMinutes = $this->durationMinutes($sourceBooking);
        $defaultDate = CarbonImmutable::parse($this->defaultBookingDate($sourceBooking));

        for ($dayOffset = 0; $dayOffset < self::AvailabilityLookaheadDays; $dayOffset++) {
            $bookingDate = $defaultDate->addDays($dayOffset)->toDateString();
            $availableSlots = collect($this->availability->slotsForDate(
                worker: $workerService->worker,
                date: $bookingDate,
                slotMinutes: $durationMinutes,
                workerService: $workerService,
            ));

            // At least one open slot means customers can proceed to the booking form.
            if ($availableSlots->contains(fn (array $slot): bool => (bool) $slot['available'])) {
                return $bookingDate;
            }
        }

        throw ValidationException::withMessages([
            'start_time' => ['This worker has no available slots for this service right now.'],
        ]);
    }

    /**
     * Recalculate duration from the original booking window instead of using old pricing.
     */
    private function durationMinutes(Booking $sourceBooking): int
    {
        $startTime = (string) ($sourceBooking->start_time ?: $sourceBooking->booking_time);

        // Fall back to the booking form's minimum duration when older bookings do not have an end time.
        if (! $sourceBooking->end_time) {
            return 60;
        }

        return CarbonImmutable::parse($sourceBooking->booking_date->toDateString().' '.$startTime)
            ->diffInMinutes(CarbonImmutable::parse($sourceBooking->booking_date->toDateString().' '.$sourceBooking->end_time));
    }
}
