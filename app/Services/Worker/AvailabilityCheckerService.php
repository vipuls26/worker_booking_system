<?php

namespace App\Services\Worker;

use App\Models\Booking;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestWorker;
use App\Models\User;
use App\Models\WorkerSchedule;
use Carbon\CarbonImmutable;

class AvailabilityCheckerService
{
    /**
     * @return array<int, array{time: string, available: bool}>
     */
    public function slotsForDate(User $worker, string $date, int $slotMinutes = 60): array
    {
        $dateValue = CarbonImmutable::parse($date);
        $dayOfWeek = (int) $dateValue->dayOfWeek;

        $schedules = $worker->workerSchedules()
            ->where('day_of_week', $dayOfWeek)
            ->orderBy('start_time')
            ->get();

        if ($schedules->contains(fn (WorkerSchedule $schedule): bool => $schedule->is_off_day)) {
            return [];
        }

        return $schedules
            ->filter(fn (WorkerSchedule $schedule): bool => ! $schedule->is_off_day)
            ->flatMap(fn (WorkerSchedule $schedule): array => $this->slotsForSchedule($worker, $dateValue, $schedule, $slotMinutes))
            ->values()
            ->all();
    }

    public function isAvailable(User $worker, string $date, string $time, int $durationMinutes = 60): bool
    {
        $dateValue = CarbonImmutable::parse($date);
        $start = CarbonImmutable::parse($date.' '.$time);
        $end = $start->addMinutes($durationMinutes);

        $withinSchedule = $worker->workerSchedules()
            ->where('day_of_week', (int) $dateValue->dayOfWeek)
            ->where('is_off_day', false)
            ->where('start_time', '<=', $start->format('H:i:s'))
            ->where('end_time', '>=', $end->format('H:i:s'))
            ->exists();

        return $withinSchedule && ! $this->hasBookingOverlap($worker, $dateValue->toDateString(), $start->format('H:i:s'), $end->format('H:i:s'));
    }

    /**
     * @return array<int, array{time: string, available: bool}>
     */
    private function slotsForSchedule(User $worker, CarbonImmutable $date, WorkerSchedule $schedule, int $slotMinutes): array
    {
        $slots = [];
        $cursor = CarbonImmutable::parse($date->toDateString().' '.$schedule->start_time);
        $scheduleEnd = CarbonImmutable::parse($date->toDateString().' '.$schedule->end_time);

        while ($cursor->addMinutes($slotMinutes)->lessThanOrEqualTo($scheduleEnd)) {
            $slotEnd = $cursor->addMinutes($slotMinutes);
            $slots[] = [
                'time' => $cursor->format('H:i'),
                'available' => ! $this->hasBookingOverlap($worker, $date->toDateString(), $cursor->format('H:i:s'), $slotEnd->format('H:i:s')),
            ];
            $cursor = $slotEnd;
        }

        return $slots;
    }

    private function hasBookingOverlap(User $worker, string $date, string $startTime, string $endTime): bool
    {
        return Booking::query()
            ->select(['booking_time', 'start_time', 'end_time'])
            ->where('worker_id', $worker->id)
            ->whereDate('booking_date', $date)
            ->whereIn('status', Booking::ActiveStatuses)
            ->get()
            ->contains(fn (Booking $booking): bool => $this->overlaps($booking, $date, $startTime, $endTime))
            || $this->hasAcceptedRequestOverlap($worker, $date, $startTime, $endTime);
    }

    private function hasAcceptedRequestOverlap(User $worker, string $date, string $startTime, string $endTime): bool
    {
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
}
