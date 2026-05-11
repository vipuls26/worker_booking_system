<?php

namespace App\Services\Worker;

use App\Models\Booking;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestWorker;
use App\Models\User;
use App\Models\WorkerSchedule;
use App\Models\WorkerService;
use Carbon\CarbonImmutable;

class AvailabilityCheckerService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function slotsForDate(User $worker, string $date, int $slotMinutes = 60, ?WorkerService $workerService = null): array
    {
        $dateValue = CarbonImmutable::parse($date);
        $dayOfWeek = (int) $dateValue->dayOfWeek;

        // Availability slots are built from the worker's schedule for the requested weekday.
        $schedules = $worker->workerSchedules()
            ->where('day_of_week', $dayOfWeek)
            ->orderBy('start_time')
            ->get();

        // A marked off-day blocks the whole date even if old working windows still exist.
        if ($schedules->contains(fn (WorkerSchedule $schedule): bool => $schedule->is_off_day)) {
            return [];
        }

        return $schedules
            ->filter(fn (WorkerSchedule $schedule): bool => ! $schedule->is_off_day)
            ->flatMap(fn (WorkerSchedule $schedule): array => $this->slotsForSchedule($worker, $dateValue, $schedule, $slotMinutes, $workerService))
            ->values()
            ->all();
    }

    public function isAvailable(User $worker, string $date, string $time, int $durationMinutes = 60): bool
    {
        $dateValue = CarbonImmutable::parse($date);
        $start = CarbonImmutable::parse($date.' '.$time);
        $end = $start->addMinutes($durationMinutes);

        // Workers are available only when the requested slot fits inside a working window.
        $withinSchedule = $worker->workerSchedules()
            ->where('day_of_week', (int) $dateValue->dayOfWeek)
            ->where('is_off_day', false)
            ->where('start_time', '<=', $start->format('H:i:s'))
            ->where('end_time', '>=', $end->format('H:i:s'))
            ->exists();

        return $withinSchedule && ! $this->hasBookingOverlap($worker, $dateValue->toDateString(), $start->format('H:i:s'), $end->format('H:i:s'));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function slotsForSchedule(User $worker, CarbonImmutable $date, WorkerSchedule $schedule, int $slotMinutes, ?WorkerService $workerService): array
    {
        $slots = [];
        $cursor = CarbonImmutable::parse($date->toDateString().' '.$schedule->start_time);
        $scheduleEnd = CarbonImmutable::parse($date->toDateString().' '.$schedule->end_time);

        while ($cursor->addMinutes($slotMinutes)->lessThanOrEqualTo($scheduleEnd)) {
            $slotEnd = $cursor->addMinutes($slotMinutes);
            $overlap = $this->overlapReason($worker, $date->toDateString(), $cursor->format('H:i:s'), $slotEnd->format('H:i:s'));
            $slots[] = [
                'time' => $cursor->format('H:i'),
                'start_time' => $cursor->format('H:i'),
                'end_time' => $slotEnd->format('H:i'),
                'duration_minutes' => $slotMinutes,
                'available' => $overlap === null,
                'reason' => $overlap,
                'label' => $cursor->format('H:i').' - '.$slotEnd->format('H:i'),
                'pricing_type' => $workerService?->pricing_type,
                'price' => $workerService?->price,
                'minimum_hours' => $workerService?->minimum_hours,
                'estimated_total' => $workerService ? $this->estimatedTotal($workerService, $slotMinutes) : null,
            ];
            $cursor = $slotEnd;
        }

        return $slots;
    }

    private function hasBookingOverlap(User $worker, string $date, string $startTime, string $endTime): bool
    {
        return $this->overlapReason($worker, $date, $startTime, $endTime) !== null;
    }

    private function overlapReason(User $worker, string $date, string $startTime, string $endTime): ?string
    {
        // Confirmed or active bookings block the same worker from being offered again.
        $hasConfirmedBooking = Booking::query()
            ->select(['booking_time', 'start_time', 'end_time'])
            ->where('worker_id', $worker->id)
            ->whereDate('booking_date', $date)
            ->whereIn('status', Booking::ActiveStatuses)
            ->get()
            ->contains(fn (Booking $booking): bool => $this->overlaps($booking, $date, $startTime, $endTime));

        // Existing bookings take precedence over tentative request reservations in the UI.
        if ($hasConfirmedBooking) {
            return 'booked';
        }

        // Accepted service requests reserve the worker until the customer chooses a final worker.
        if ($this->hasAcceptedRequestOverlap($worker, $date, $startTime, $endTime)) {
            return 'reserved';
        }

        return null;
    }

    private function hasAcceptedRequestOverlap(User $worker, string $date, string $startTime, string $endTime): bool
    {
        // Pending customer selection should still protect accepted workers from double booking.
        return ServiceRequestWorker::query()
            ->where('worker_id', $worker->id)
            ->where('status', ServiceRequestWorker::STATUS_ACCEPTED)
            ->whereHas('serviceRequest', function ($query) use ($date): void {
                $query
                    ->whereDate('requested_date', $date)
                    ->where('status', ServiceRequest::STATUS_OPEN);
            })
            ->with('serviceRequest:id,requested_date,start_time,end_time')
            ->get()
            ->contains(function (ServiceRequestWorker $serviceRequestWorker) use ($date, $startTime, $endTime): bool {
                // Missing parent requests are ignored so stale rows do not block worker availability.
                if ($serviceRequestWorker->serviceRequest === null) {
                    return false;
                }

                $booking = new Booking([
                    'booking_time' => $serviceRequestWorker->serviceRequest->start_time,
                    'start_time' => $serviceRequestWorker->serviceRequest->start_time,
                    'end_time' => $serviceRequestWorker->serviceRequest->end_time,
                ]);

                return $this->overlaps($booking, $date, $startTime, $endTime);
            });
    }

    private function overlaps(Booking $booking, string $date, string $startTime, string $endTime): bool
    {
        $bookingStart = CarbonImmutable::parse($date.' '.($booking->start_time ?: $booking->booking_time));
        $bookingEnd = $booking->end_time
            ? CarbonImmutable::parse($date.' '.$booking->end_time)
            : $bookingStart->addHour();
        $slotStart = CarbonImmutable::parse($date.' '.$startTime);
        $slotEnd = CarbonImmutable::parse($date.' '.$endTime);

        return $bookingStart->lessThan($slotEnd) && $bookingEnd->greaterThan($slotStart);
    }

    private function estimatedTotal(WorkerService $workerService, int $durationMinutes): float
    {
        // Hourly services honor minimum hours when showing customers a slot estimate.
        if ($workerService->pricing_type === WorkerService::PricingHourly) {
            $hours = max($workerService->minimum_hours ?: 1, (int) ceil($durationMinutes / 60));

            return round((float) $workerService->price * $hours, 2);
        }

        return (float) $workerService->price;
    }
}
