<?php

namespace App\Listeners;

use App\Events\BookingStatusChanged;
use App\Models\Booking;
use App\Notifications\BookingWorkflowNotification;
use App\Services\Audit\AuditLogger;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Queue\InteractsWithQueue;

class LogBookingStatusChanged implements ShouldQueueAfterCommit
{
    use InteractsWithQueue;

    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(BookingStatusChanged $event): void
    {
        $booking = $event->booking->loadMissing(['customer', 'worker', 'service']);

        $this->audit->record('booking.status_changed', $event->actor, $booking, [
            'from_status' => $event->oldStatus,
            'to_status' => $event->newStatus,
            'reason' => $event->reason,
        ]);

        if ($event->newStatus === Booking::STATUS_CANCELLED && $event->actor?->id === $booking->customer_id) {
            $booking->worker?->notify(new BookingWorkflowNotification(
                booking: $booking,
                event: 'booking_cancelled',
                title: 'Booking cancelled',
                message: sprintf('%s cancelled a booking.', $booking->customer?->name ?? 'Customer'),
            ));

            return;
        }

        $messages = [
            Booking::STATUS_ACCEPTED => ['booking_accepted', 'Booking accepted', 'Your booking has been accepted.'],
            Booking::STATUS_REJECTED => ['booking_rejected', 'Booking rejected', 'Your booking has been rejected.'],
            Booking::STATUS_IN_PROGRESS => ['work_started', 'Work started', 'Your worker has started the job.'],
            Booking::STATUS_COMPLETED => ['work_completed', 'Work completed', 'Your booking has been completed.'],
            Booking::STATUS_CANCELLED => ['booking_cancelled', 'Booking cancelled', 'Your booking has been cancelled.'],
        ];

        if (! isset($messages[$event->newStatus])) {
            return;
        }

        [$notificationEvent, $title, $message] = $messages[$event->newStatus];

        $booking->customer?->notify(new BookingWorkflowNotification(
            booking: $booking,
            event: $notificationEvent,
            title: $title,
            message: $message,
        ));
    }
}
