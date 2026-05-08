<?php

namespace App\Notifications;

use App\Models\Review;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class ReviewReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new notification instance.
     */
    public function __construct(private readonly Review $review)
    {
        $this->review->loadMissing(['booking', 'customer', 'worker']);
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
        $isWorkerFeedback = $this->review->type === Review::TypeWorkerToCustomer;
        $reviewerName = $isWorkerFeedback
            ? ($this->review->worker?->name ?? 'A worker')
            : ($this->review->customer?->name ?? 'A customer');

        return [
            'event' => 'review_received',
            'title' => $isWorkerFeedback ? 'New worker feedback received' : 'New review received',
            'message' => sprintf('%s rated you %d stars.', $reviewerName, $this->review->rating),
            'booking_id' => $this->review->booking_id,
            'service_request_id' => $this->review->booking?->service_request_id,
            'review_id' => $this->review->id,
            'rating' => $this->review->rating,
            'url' => $isWorkerFeedback ? '/customer/bookings/'.($this->review->booking?->service_request_id ?: $this->review->booking_id) : '/worker/reviews',
            'broadcast_ready' => true,
        ];
    }
}
