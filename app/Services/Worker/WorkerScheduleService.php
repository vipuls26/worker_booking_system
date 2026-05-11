<?php

namespace App\Services\Worker;

use App\Models\Booking;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestWorker;
use App\Models\User;
use App\Models\WorkerSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

class WorkerScheduleService
{
    /**
     * @return Collection<int, WorkerSchedule>
     */
    public function weeklySchedule(User $worker): Collection
    {
        // Workers manage availability in weekday and time order.
        return $worker->workerSchedules()
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();
    }

    /**
     * @param  array{day_of_week: int, start_time?: string|null, end_time?: string|null, is_off_day: bool}  $data
     */
    public function create(User $worker, array $data): WorkerSchedule
    {
        return $worker->workerSchedules()->create($data);
    }

    /**
     * @param  array{day_of_week: int, start_time?: string|null, end_time?: string|null, is_off_day: bool}  $data
     */
    public function update(WorkerSchedule $schedule, array $data): WorkerSchedule
    {
        $schedule->update($data);

        return $schedule->refresh();
    }

    public function delete(WorkerSchedule $schedule): void
    {
        $schedule->delete();
    }

    /**
     * @param  array{day_of_week: int, start_time?: string|null, end_time?: string|null, is_off_day: bool}  $data
     */
    public function overlaps(User $worker, array $data, ?int $ignoreId = null): bool
    {
        // Off-days and incomplete windows cannot overlap working availability.
        if ($data['is_off_day'] || empty($data['start_time']) || empty($data['end_time'])) {
            return false;
        }

        // Working windows overlap when they share the same day and intersect in time.
        return $worker->workerSchedules()
            ->where('day_of_week', $data['day_of_week'])
            ->where('is_off_day', false)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->where('start_time', '<', $data['end_time'])
            ->where('end_time', '>', $data['start_time'])
            ->exists();
    }

    /**
     * @param  array{day_of_week: int, start_time?: string|null, end_time?: string|null, is_off_day: bool}  $data
     */
    public function conflictsWithDayMode(User $worker, array $data, ?int $ignoreId = null): ?string
    {
        $query = $worker->workerSchedules()
            ->where('day_of_week', $data['day_of_week'])
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId));

        // A full day off cannot coexist with working windows for the same day.
        if ($data['is_off_day'] && (clone $query)->exists()) {
            return 'Remove working windows before marking this day as off.';
        }

        // Working windows cannot be added while the day is marked unavailable.
        if (! $data['is_off_day'] && (clone $query)->where('is_off_day', true)->exists()) {
            return 'This day is marked as off. Remove the off-day entry before adding working windows.';
        }

        return null;
    }

    /**
     * Explain why a worker cannot receive a booking for the requested date and time.
     *
     * Customers need precise validation feedback when a direct worker booking falls outside schedule,
     * lands on an off-day, or overlaps an existing reservation.
     */
    public function bookingAvailabilityError(User $worker, string $bookingDate, string $startTime, string $endTime, ?int $ignoreBookingId = null, ?int $ignoreServiceRequestId = null): ?string
    {
        $bookingDateValue = CarbonImmutable::parse($bookingDate);
        $startDateTime = CarbonImmutable::parse($bookingDate.' '.$startTime);
        $endDateTime = CarbonImmutable::parse($bookingDate.' '.$endTime);

        // Off-day records block the whole date even if stale working windows still exist.
        if ($this->isOffDay($worker, $bookingDateValue)) {
            return 'This worker is not available on the selected day.';
        }

        // Bookings must fit fully inside one of the worker's configured working windows.
        if (! $this->isInsideWorkingWindow($worker, $bookingDateValue, $startDateTime, $endDateTime)) {
            return 'This worker is not scheduled during the selected time.';
        }

        // Existing bookings and accepted requests protect workers from overlapping commitments.
        if ($this->hasOverlappingBooking($worker, $bookingDateValue->toDateString(), $startDateTime->format('H:i:s'), $endDateTime->format('H:i:s'), $ignoreBookingId, $ignoreServiceRequestId)) {
            return 'This worker already has a booking that overlaps the selected time.';
        }

        return null;
    }

    /**
     * Determine whether a worker can receive a booking for the requested window.
     */
    public function isAvailableForBooking(User $worker, string $bookingDate, string $startTime, string $endTime, ?int $ignoreBookingId = null, ?int $ignoreServiceRequestId = null): bool
    {
        return $this->bookingAvailabilityError($worker, $bookingDate, $startTime, $endTime, $ignoreBookingId, $ignoreServiceRequestId) === null;
    }

    /**
     * Check whether the requested booking window overlaps existing worker commitments.
     */
    public function hasOverlappingBooking(User $worker, string $bookingDate, string $startTime, string $endTime, ?int $ignoreBookingId = null, ?int $ignoreServiceRequestId = null): bool
    {
        // Accepted, confirmed, and in-progress bookings block the worker from taking an overlapping slot.
        $hasBookingOverlap = Booking::query()
            ->where('worker_id', $worker->id)
            ->when($ignoreBookingId, fn ($query) => $query->whereKeyNot($ignoreBookingId))
            ->whereDate('booking_date', $bookingDate)
            ->whereIn('status', Booking::BookingOverlapStatuses)
            ->get(['id', 'booking_time', 'start_time', 'end_time'])
            ->contains(fn (Booking $booking): bool => $this->bookingOverlaps($booking, $bookingDate, $startTime, $endTime));

        if ($hasBookingOverlap) {
            return true;
        }

        return $this->hasAcceptedRequestOverlap($worker, $bookingDate, $startTime, $endTime, $ignoreServiceRequestId);
    }

    /**
     * Determine whether a worker has marked the whole requested day as unavailable.
     */
    private function isOffDay(User $worker, CarbonImmutable $bookingDate): bool
    {
        // Any off-day entry for the weekday wins over working windows.
        return $worker->workerSchedules()
            ->where('day_of_week', (int) $bookingDate->dayOfWeek)
            ->where('is_off_day', true)
            ->exists();
    }

    /**
     * Determine whether the booking window fits inside one working schedule window.
     */
    private function isInsideWorkingWindow(User $worker, CarbonImmutable $bookingDate, CarbonImmutable $startDateTime, CarbonImmutable $endDateTime): bool
    {
        // Working windows must cover the full booking start and end time.
        return $worker->workerSchedules()
            ->where('day_of_week', (int) $bookingDate->dayOfWeek)
            ->where('is_off_day', false)
            ->where('start_time', '<=', $startDateTime->format('H:i:s'))
            ->where('end_time', '>=', $endDateTime->format('H:i:s'))
            ->exists();
    }

    /**
     * Check accepted service requests that temporarily reserve worker availability.
     */
    private function hasAcceptedRequestOverlap(User $worker, string $bookingDate, string $startTime, string $endTime, ?int $ignoreServiceRequestId = null): bool
    {
        // Accepted open service requests reserve the worker until customer selection is resolved.
        return ServiceRequestWorker::query()
            ->where('worker_id', $worker->id)
            ->where('status', ServiceRequestWorker::STATUS_ACCEPTED)
            ->whereHas('serviceRequest', function ($query) use ($bookingDate, $ignoreServiceRequestId): void {
                $query
                    ->whereDate('requested_date', $bookingDate)
                    ->where('status', ServiceRequest::STATUS_OPEN)
                    ->when($ignoreServiceRequestId, fn ($query) => $query->whereKeyNot($ignoreServiceRequestId));
            })
            ->with('serviceRequest:id,requested_date,start_time,end_time')
            ->get()
            ->contains(function (ServiceRequestWorker $serviceRequestWorker) use ($bookingDate, $startTime, $endTime): bool {
                // Stale worker request rows should not block future availability.
                if ($serviceRequestWorker->serviceRequest === null) {
                    return false;
                }

                $booking = new Booking([
                    'booking_time' => $serviceRequestWorker->serviceRequest->start_time,
                    'start_time' => $serviceRequestWorker->serviceRequest->start_time,
                    'end_time' => $serviceRequestWorker->serviceRequest->end_time,
                ]);

                return $this->bookingOverlaps($booking, $bookingDate, $startTime, $endTime);
            });
    }

    /**
     * Compare two booking windows for any shared time.
     */
    private function bookingOverlaps(Booking $booking, string $bookingDate, string $startTime, string $endTime): bool
    {
        $bookingStart = CarbonImmutable::parse($bookingDate.' '.($booking->start_time ?: $booking->booking_time));
        $bookingEnd = $booking->end_time
            ? CarbonImmutable::parse($bookingDate.' '.$booking->end_time)
            : $bookingStart->addHour();
        $slotStart = CarbonImmutable::parse($bookingDate.' '.$startTime);
        $slotEnd = CarbonImmutable::parse($bookingDate.' '.$endTime);

        return $bookingStart->lessThan($slotEnd) && $bookingEnd->greaterThan($slotStart);
    }
}
