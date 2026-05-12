<?php

namespace App\Events;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BookingStatusChanged implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Booking $booking,
        public string $oldStatus,
        public string $newStatus,
        public ?User $actor = null,
        public ?string $reason = null,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return collect([$this->booking->customer_id, $this->booking->worker_id])
            ->filter()
            ->unique()
            ->map(fn (int $userId): PrivateChannel => new PrivateChannel('users.'.$userId))
            ->values()
            ->all();
    }

    public function broadcastAs(): string
    {
        return 'booking.status.changed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'booking_id' => $this->booking->id,
            'service_request_id' => $this->booking->service_request_id,
            'customer_id' => $this->booking->customer_id,
            'worker_id' => $this->booking->worker_id,
            'old_status' => $this->oldStatus,
            'new_status' => $this->newStatus,
            'reason' => $this->reason,
        ];
    }
}
