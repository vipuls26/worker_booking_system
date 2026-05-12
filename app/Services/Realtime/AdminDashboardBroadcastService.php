<?php

namespace App\Services\Realtime;

use App\Events\AdminDashboardUpdated;

class AdminDashboardBroadcastService
{
    /**
     * Notify connected admin dashboards to refresh lightweight counters and charts.
     */
    public function broadcastRefresh(): void
    {
        event(new AdminDashboardUpdated);
    }
}
