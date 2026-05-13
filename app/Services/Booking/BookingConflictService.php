<?php

namespace App\Services\Booking;

use App\Models\Booking;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestWorker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class BookingConflictService
{
    /**
     * Check whether a worker already has a blocking booking or reservation for the requested slot.
     */
    public function hasConflict(
        int $workerId,
        string $bookingDate,
        string $startTime,
        string $endTime,
        ?int $ignoreBookingId = null,
        ?int $ignoreServiceRequestId = null,
    ): bool {
        return $this->hasBlockingBookingConflict($workerId, $bookingDate, $startTime, $endTime, $ignoreBookingId)
            || $this->hasAcceptedReservationConflict($workerId, $bookingDate, $startTime, $endTime, $ignoreServiceRequestId);
    }

    /**
     * Explain whether the slot is blocked by a real booking or a temporary reservation.
     */
    public function conflictReason(
        int $workerId,
        string $bookingDate,
        string $startTime,
        string $endTime,
        ?int $ignoreBookingId = null,
        ?int $ignoreServiceRequestId = null,
    ): ?string {
        // Confirmed work should be shown before tentative reservations.
        if ($this->hasBlockingBookingConflict($workerId, $bookingDate, $startTime, $endTime, $ignoreBookingId)) {
            return 'booked';
        }

        if ($this->hasAcceptedReservationConflict($workerId, $bookingDate, $startTime, $endTime, $ignoreServiceRequestId)) {
            return 'reserved';
        }

        return null;
    }

    /**
     * Apply worker-search conflict filtering for one requested slot.
     */
    public function excludeConflictingWorkers(Builder $query, string $bookingDate, string $startTime, string $endTime): void
    {
        // Workers with a blocking booking should not appear in search results for this slot.
        $query->whereNotIn('users.id', function ($subQuery) use ($bookingDate, $startTime, $endTime): void {
            $subQuery
                ->select('worker_id')
                ->from('bookings')
                ->whereDate('booking_date', $bookingDate)
                ->whereIn('status', Booking::BLOCKING_STATUSES)
                ->where('start_time', '<', $endTime)
                ->where('end_time', '>', $startTime);
        });

        // Accepted worker responses reserve the slot until the customer finishes selection.
        $query->whereNotIn('users.id', function ($subQuery) use ($bookingDate, $startTime, $endTime): void {
            $subQuery
                ->select('service_request_workers.worker_id')
                ->from('service_request_workers')
                ->join('service_requests', 'service_requests.id', '=', 'service_request_workers.service_request_id')
                ->where('service_request_workers.status', ServiceRequestWorker::STATUS_ACCEPTED)
                ->where('service_requests.status', ServiceRequest::STATUS_OPEN)
                ->whereDate('service_requests.requested_date', $bookingDate)
                ->where('service_requests.start_time', '<', $endTime)
                ->where('service_requests.end_time', '>', $startTime);
        });
    }

    /**
     * Return other pending worker requests that overlap a finalized booking.
     *
     * @return Collection<int, ServiceRequestWorker>
     */
    public function overlappingPendingRequestsForBooking(Booking $booking, ?int $ignoreWorkerRequestId = null): Collection
    {
        return ServiceRequestWorker::query()
            ->with(['serviceRequest.customer', 'serviceRequest.service'])
            ->where('worker_id', $booking->worker_id)
            ->where('status', ServiceRequestWorker::STATUS_PENDING)
            ->when($ignoreWorkerRequestId, fn (Builder $query) => $query->whereKeyNot($ignoreWorkerRequestId))
            ->whereHas('serviceRequest', function (Builder $query) use ($booking): void {
                $query
                    ->where('status', ServiceRequest::STATUS_OPEN)
                    ->whereDate('requested_date', $booking->booking_date?->toDateString())
                    ->where('start_time', '<', $booking->end_time)
                    ->where('end_time', '>', $booking->start_time);
            })
            ->get();
    }

    /**
     * Check whether a saved booking already blocks the requested worker slot.
     */
    public function hasBlockingBookingConflict(
        int $workerId,
        string $bookingDate,
        string $startTime,
        string $endTime,
        ?int $ignoreBookingId = null,
    ): bool {
        return Booking::query()
            ->where('worker_id', $workerId)
            ->when($ignoreBookingId, fn (Builder $query) => $query->whereKeyNot($ignoreBookingId))
            ->whereDate('booking_date', $bookingDate)
            ->whereIn('status', Booking::BLOCKING_STATUSES)
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime)
            ->exists();
    }

    /**
     * Check whether an accepted open request is temporarily reserving the requested worker slot.
     */
    public function hasAcceptedReservationConflict(
        int $workerId,
        string $bookingDate,
        string $startTime,
        string $endTime,
        ?int $ignoreServiceRequestId = null,
    ): bool {
        return ServiceRequestWorker::query()
            ->where('worker_id', $workerId)
            ->where('status', ServiceRequestWorker::STATUS_ACCEPTED)
            ->whereHas('serviceRequest', function (Builder $query) use ($bookingDate, $startTime, $endTime, $ignoreServiceRequestId): void {
                $query
                    ->where('status', ServiceRequest::STATUS_OPEN)
                    ->whereDate('requested_date', $bookingDate)
                    ->when($ignoreServiceRequestId, fn (Builder $serviceRequestQuery) => $serviceRequestQuery->whereKeyNot($ignoreServiceRequestId))
                    ->where('start_time', '<', $endTime)
                    ->where('end_time', '>', $startTime);
            })
            ->exists();
    }
}
