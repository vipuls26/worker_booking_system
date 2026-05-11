<?php

namespace App\Listeners;

use App\Events\BookingCreated;
use App\Models\ServiceRequestWorker;
use App\Notifications\BookingWorkflowNotification;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Queue\InteractsWithQueue;

class SendBookingCreatedNotifications implements ShouldQueueAfterCommit
{
    use InteractsWithQueue;

    public function handle(BookingCreated $event): void
    {
        $booking = $event->booking->loadMissing(['customer', 'worker', 'service', 'serviceRequest.workers.worker']);
        $isSingleWorkerRequest = $booking->serviceRequest?->workers?->count() === 1;

        // The selected worker needs confirmation that the customer chose them.
        $booking->worker?->notify(new BookingWorkflowNotification(
            booking: $booking,
            event: 'booking_confirmed',
            title: 'Booking confirmed',
            message: sprintf('%s selected you for %s.', $booking->customer?->name ?? 'A customer', $booking->service?->name ?? 'a booking'),
        ));

        // Customers see different copy when a single invited worker auto-accepts.
        $booking->customer?->notify(new BookingWorkflowNotification(
            booking: $booking,
            event: $isSingleWorkerRequest ? 'booking_accepted' : 'booking_confirmed',
            title: $isSingleWorkerRequest ? 'Worker accepted your request' : 'Booking confirmed',
            message: $isSingleWorkerRequest
                ? sprintf('%s accepted your %s request.', $booking->worker?->name ?? 'The worker', $booking->service?->name ?? 'service')
            : sprintf('Your %s booking is confirmed with %s.', $booking->service?->name ?? 'service', $booking->worker?->name ?? 'the selected worker'),
        ));

        // Workers who were not selected should know the request is closed.
        $booking->serviceRequest?->workers()
            ->where('status', ServiceRequestWorker::STATUS_NOT_SELECTED)
            ->with('worker')
            ->get()
            ->each(function (ServiceRequestWorker $notSelectedWorker) use ($booking): void {
                $notSelectedWorker->worker?->notify(new BookingWorkflowNotification(
                    booking: $booking,
                    event: 'booking_request_closed',
                    title: 'Booking request closed',
                    message: sprintf('The customer selected another worker for %s.', $booking->service?->name ?? 'this booking'),
                ));
            });
    }
}
