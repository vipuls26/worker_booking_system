<?php

namespace App\Services\Booking;

use App\Models\Booking;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestWorker;
use App\Models\User;
use Carbon\CarbonImmutable;

class AvailabilityService
{
    public function isWorkerAvailable(User $worker, string $date, string $startTime, int $durationMinutes = 60, ?int $ignoreBookingId = null, ?int $ignoreServiceRequestId = null): bool
    {
        $dateValue = CarbonImmutable::parse($date);
        $start = CarbonImmutable::parse($date.' '.$startTime);
        $end = $start->addMinutes($durationMinutes);

        return $this->scheduleAllows($worker, $dateValue, $start, $end)
            && ! $this->hasOverlappingBooking($worker, $dateValue->toDateString(), $start->format('H:i:s'), $end->format('H:i:s'), $ignoreBookingId, $ignoreServiceRequestId);
    }

    public function hasOverlappingBooking(User $worker, string $date, string $startTime, string $endTime, ?int $ignoreBookingId = null, ?int $ignoreServiceRequestId = null): bool
    {
        return Booking::query()
            ->where('worker_id', $worker->id)
            ->when($ignoreBookingId, fn ($query) => $query->whereKeyNot($ignoreBookingId))
            ->whereDate('booking_date', $date)
            ->whereIn('status', Booking::ActiveStatuses)
            ->get(['id', 'booking_time', 'start_time', 'end_time'])
            ->contains(fn (Booking $booking): bool => $this->overlaps($booking, $date, $startTime, $endTime))
            || $this->hasAcceptedRequestOverlap($worker, $date, $startTime, $endTime, $ignoreServiceRequestId);
    }

    private function hasAcceptedRequestOverlap(User $worker, string $date, string $startTime, string $endTime, ?int $ignoreServiceRequestId = null): bool
    {
        return ServiceRequestWorker::query()
            ->where('worker_id', $worker->id)
            ->where('status', ServiceRequestWorker::STATUS_ACCEPTED)
            ->whereHas('serviceRequest', function ($query) use ($date, $ignoreServiceRequestId): void {
                $query
                    ->whereDate('requested_date', $date)
                    ->where('status', ServiceRequest::STATUS_OPEN)
                    ->when($ignoreServiceRequestId, fn ($query) => $query->whereKeyNot($ignoreServiceRequestId));
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

    private function scheduleAllows(User $worker, CarbonImmutable $date, CarbonImmutable $start, CarbonImmutable $end): bool
    {
        return $worker->workerSchedules()
            ->where('day_of_week', (int) $date->dayOfWeek)
            ->where('is_off_day', false)
            ->where('start_time', '<=', $start->format('H:i:s'))
            ->where('end_time', '>=', $end->format('H:i:s'))
            ->exists();
    }
}
