<?php

namespace App\Services\Admin;

use App\Http\Resources\ServiceResource;
use App\Models\Booking;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DashboardAnalyticsService
{
    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $popularServiceIds = Booking::query()
            ->select('service_id', DB::raw('count(*) as bookings_count'))
            ->groupBy('service_id')
            ->orderByDesc('bookings_count')
            ->limit(5)
            ->pluck('bookings_count', 'service_id');

        $services = Service::query()
            ->whereIn('id', $popularServiceIds->keys())
            ->get()
            ->sortByDesc(fn (Service $service): int => (int) $popularServiceIds->get($service->id))
            ->values();

        return [
            'total_users' => User::query()->count(),
            'total_workers' => User::query()->whereHas('role', fn ($query) => $query->where('slug', 'worker'))->count(),
            'total_bookings' => Booking::query()->count(),
            'total_revenue' => (float) Booking::query()->where('status', Booking::STATUS_COMPLETED)->sum('total_amount'),
            'popular_services' => ServiceResource::collection($services),
        ];
    }
}
