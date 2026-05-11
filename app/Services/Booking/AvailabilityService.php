<?php

namespace App\Services\Booking;

use App\Models\User;
use App\Services\Worker\WorkerScheduleService;
use Carbon\CarbonImmutable;

class AvailabilityService
{
    public function __construct(private readonly WorkerScheduleService $workerSchedules) {}

    public function isWorkerAvailable(User $worker, string $date, string $startTime, int $durationMinutes = 60, ?int $ignoreBookingId = null, ?int $ignoreServiceRequestId = null): bool
    {
        $start = CarbonImmutable::parse($date.' '.$startTime);
        $end = $start->addMinutes($durationMinutes);

        return $this->workerSchedules->isAvailableForBooking($worker, $date, $start->format('H:i:s'), $end->format('H:i:s'), $ignoreBookingId, $ignoreServiceRequestId);
    }

    public function hasOverlappingBooking(User $worker, string $date, string $startTime, string $endTime, ?int $ignoreBookingId = null, ?int $ignoreServiceRequestId = null): bool
    {
        return $this->workerSchedules->hasOverlappingBooking($worker, $date, $startTime, $endTime, $ignoreBookingId, $ignoreServiceRequestId);
    }
}
