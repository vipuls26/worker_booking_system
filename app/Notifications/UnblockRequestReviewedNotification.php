<?php

namespace App\Notifications;

use App\Models\UnblockRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class UnblockRequestReviewedNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new notification instance.
     */
    public function __construct(private readonly UnblockRequest $unblockRequest)
    {
        $this->unblockRequest->loadMissing('reviewer');
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
        $isApproved = $this->unblockRequest->status === UnblockRequest::STATUS_APPROVED;

        return [
            'event' => $isApproved ? 'unblock_request_approved' : 'unblock_request_rejected',
            'title' => $isApproved ? 'Account unblocked' : 'Unblock request rejected',
            'message' => $isApproved
                ? 'Your unblock request was approved. You can access your account again.'
                : 'Your unblock request was rejected. Please review the admin note before submitting again.',
            'unblock_request_id' => $this->unblockRequest->id,
            'status' => $this->unblockRequest->status,
            'admin_note' => $this->unblockRequest->admin_note,
            'reviewed_by' => $this->unblockRequest->reviewed_by,
            'reviewer_name' => $this->unblockRequest->reviewer?->name,
            'url' => '/account/unblock-request',
            'broadcast_ready' => true,
        ];
    }
}
