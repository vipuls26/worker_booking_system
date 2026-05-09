<?php

namespace App\Notifications;

use App\Models\ServiceRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class ServiceRequestWorkflowNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        private readonly ServiceRequest $serviceRequest,
        private readonly string $event,
        private readonly string $title,
        private readonly string $message,
    ) {
        $this->serviceRequest->loadMissing('service');
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
            'service_request_id' => $this->serviceRequest->id,
            'service_id' => $this->serviceRequest->service_id,
            'service_name' => $this->serviceRequest->service?->name,
            'status' => $this->serviceRequest->status,
            'url' => $this->urlFor($notifiable),
            'broadcast_ready' => true,
        ];
    }

    private function urlFor(object $notifiable): string
    {
        if (method_exists($notifiable, 'hasRole') && $notifiable->hasRole('worker')) {
            return '/worker/booking-requests';
        }

        if (method_exists($notifiable, 'hasRole') && $notifiable->hasRole('customer')) {
            return '/customer/bookings/'.$this->serviceRequest->id;
        }

        return '/admin/dashboard';
    }
}
