<?php

namespace App\Notifications;

use App\Models\WorkerVerification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class VerificationStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly WorkerVerification $verification,
        private readonly string $title,
        private readonly string $message,
    ) {
        $this->afterCommit();
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'event' => 'verification_status_updated',
            'title' => $this->title,
            'message' => $this->message,
            'verification_id' => $this->verification->id,
            'status' => $this->verification->status,
            'rejection_reason' => $this->verification->rejection_reason,
            'url' => '/worker/profile',
            'broadcast_ready' => true,
        ];
    }

    /**
     * Broadcast the saved payload so dropdown and toast updates share one format.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
