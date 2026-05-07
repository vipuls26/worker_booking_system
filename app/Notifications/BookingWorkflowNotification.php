<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class BookingWorkflowNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        private readonly Booking $booking,
        private readonly string $event,
        private readonly string $title,
        private readonly string $message,
    ) {
        $this->afterCommit();
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'event' => $this->event,
            'title' => $this->title,
            'message' => $this->message,
            'booking_id' => $this->booking->id,
            'service_id' => $this->booking->service_id,
            'service_name' => $this->booking->service?->name,
            'status' => $this->booking->status,
            'url' => $this->urlFor($notifiable),
            'broadcast_ready' => true,
        ];
    }

    private function urlFor(object $notifiable): string
    {
        if (method_exists($notifiable, 'hasRole') && $notifiable->hasRole('worker')) {
            if ($this->event === 'booking_received' && $this->booking->worker_id !== $notifiable->id) {
                return '/worker/booking-requests';
            }

            return '/worker/bookings';
        }

        if (method_exists($notifiable, 'hasRole') && $notifiable->hasRole('customer')) {
            return '/customer/bookings/'.$this->booking->id;
        }

        return '/admin/bookings';
    }
}
