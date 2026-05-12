<?php

namespace App\Events;

use App\Models\Booking;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BookingCreated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public Booking $booking) {}

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
        return 'booking.created';
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
            'status' => $this->booking->status,
        ];
    }
}
