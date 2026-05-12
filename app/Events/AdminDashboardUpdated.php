<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AdminDashboardUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('admin.dashboard');
    }

    public function broadcastAs(): string
    {
        return 'admin.dashboard.updated';
    }

    /**
     * @return array<string, bool>
     */
    public function broadcastWith(): array
    {
        return [
            'refresh' => true,
        ];
    }
}
