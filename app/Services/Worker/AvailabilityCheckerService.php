<?php

namespace App\Services\Worker;

use App\Models\User;
use App\Models\WorkerSchedule;
use App\Models\WorkerService;
use App\Services\Booking\BookingConflictService;
use Carbon\CarbonImmutable;

class AvailabilityCheckerService
{
    public function __construct(private readonly BookingConflictService $bookingConflicts) {}

    /**
     * Return the current application time as an immutable value so date-sensitive slot checks stay consistent.
     */
    private function currentDateTime(): CarbonImmutable
    {
        return CarbonImmutable::now();
    }

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
        $slotStepMinutes = $this->slotStepMinutes($slotMinutes);

        while ($cursor->addMinutes($slotMinutes)->lessThanOrEqualTo($scheduleEnd)) {
            $slotEnd = $cursor->addMinutes($slotMinutes);

            // Customers should only see slots that have not already started today.
            if ($this->shouldSkipPastSlot($date, $cursor)) {
                $cursor = $cursor->addMinutes($slotStepMinutes);

                continue;
            }

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
            $cursor = $cursor->addMinutes($slotStepMinutes);
        }

        return $slots;
    }

    /**
     * Move slot start times forward in smaller intervals so same-day customers can still book the next real opening.
     */
    private function slotStepMinutes(int $slotMinutes): int
    {
        return min(30, max(15, $slotMinutes));
    }

    /**
     * Skip same-day slots that already started so customers cannot request time that has passed.
     */
    private function shouldSkipPastSlot(CarbonImmutable $slotDate, CarbonImmutable $slotStart): bool
    {
        $currentDateTime = $this->currentDateTime();

        // Future dates should keep their full schedule.
        if (! $slotDate->isSameDay($currentDateTime)) {
            return false;
        }

        return $slotStart->lessThan($currentDateTime);
    }

    private function hasBookingOverlap(User $worker, string $date, string $startTime, string $endTime): bool
    {
        return $this->overlapReason($worker, $date, $startTime, $endTime) !== null;
    }

    private function overlapReason(User $worker, string $date, string $startTime, string $endTime): ?string
    {
        return $this->bookingConflicts->conflictReason(
            workerId: $worker->id,
            bookingDate: $date,
            startTime: $startTime,
            endTime: $endTime,
        );
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
