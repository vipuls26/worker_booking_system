<?php

namespace App\Services\Admin;

use App\Http\Resources\ServiceResource;
use App\Models\Booking;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardAnalyticsService
{
    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        return [
            'total_users' => User::query()->count(),
            'total_workers' => User::query()->whereHas('role', fn ($query) => $query->where('slug', 'worker'))->count(),
            'total_bookings' => Booking::query()->count(),
            'total_revenue' => (float) Booking::query()->where('status', Booking::STATUS_COMPLETED)->sum('platform_commission'),
            'gross_booking_value' => (float) Booking::query()->where('status', Booking::STATUS_COMPLETED)->sum('total_amount'),
            'worker_payouts' => (float) Booking::query()->where('status', Booking::STATUS_COMPLETED)->sum('worker_earning'),
            'cards' => $this->cards(),
            'revenue_reports' => [
                'monthly' => $this->monthlyRevenue(),
                'by_status' => $this->revenueByStatus(),
            ],
            'booking_statuses' => $this->bookingStatuses(),
            'popular_services' => $this->popularServices(),
        ];
    }

    /**
     * @return array<int, array{label: string, value: float|int, icon: string}>
     */
    private function cards(): array
    {
        return [
            ['label' => 'Total users', 'value' => User::query()->count(), 'icon' => 'pi-users'],
            ['label' => 'Total workers', 'value' => User::query()->whereHas('role', fn ($query) => $query->where('slug', 'worker'))->count(), 'icon' => 'pi-briefcase'],
            ['label' => 'Total bookings', 'value' => Booking::query()->count(), 'icon' => 'pi-calendar'],
            ['label' => 'Commission revenue', 'value' => (float) Booking::query()->where('status', Booking::STATUS_COMPLETED)->sum('platform_commission'), 'icon' => 'pi-indian-rupee'],
        ];
    }

    /**
     * @return Collection<int, array{label: string, value: float}>
     */
    private function monthlyRevenue(): Collection
    {
        $monthExpression = $this->monthExpression('booking_date');

        return Booking::query()
            ->selectRaw($monthExpression.' as label')
            ->selectRaw('SUM(platform_commission) as value')
            ->where('status', Booking::STATUS_COMPLETED)
            ->whereNotNull('booking_date')
            ->groupBy('label')
            ->orderBy('label')
            ->limit(12)
            ->get()
            ->map(fn (Booking $booking): array => [
                'label' => (string) $booking->label,
                'value' => round((float) $booking->value, 2),
            ]);
    }

    /**
     * @return Collection<int, array{label: string, value: float}>
     */
    private function revenueByStatus(): Collection
    {
        return Booking::query()
            ->select('status as label')
            ->selectRaw('SUM(platform_commission) as value')
            ->groupBy('status')
            ->orderByDesc('value')
            ->get()
            ->map(fn (Booking $booking): array => [
                'label' => (string) $booking->label,
                'value' => round((float) $booking->value, 2),
            ]);
    }

    /**
     * @return Collection<int, array{label: string, value: int}>
     */
    private function bookingStatuses(): Collection
    {
        return Booking::query()
            ->select('status as label')
            ->selectRaw('COUNT(*) as value')
            ->groupBy('status')
            ->orderByDesc('value')
            ->get()
            ->map(fn (Booking $booking): array => [
                'label' => (string) $booking->label,
                'value' => (int) $booking->value,
            ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function popularServices(): Collection
    {
        $serviceStats = Booking::query()
            ->select('service_id')
            ->selectRaw('COUNT(*) as bookings_count')
            ->selectRaw('SUM(CASE WHEN status = ? THEN platform_commission ELSE 0 END) as revenue', [Booking::STATUS_COMPLETED])
            ->groupBy('service_id')
            ->orderByDesc('bookings_count')
            ->limit(5)
            ->get()
            ->keyBy('service_id');

        return Service::query()
            ->whereIn('id', $serviceStats->keys())
            ->get()
            ->sortByDesc(fn (Service $service): int => (int) $serviceStats->get($service->id)?->bookings_count)
            ->values()
            ->map(fn (Service $service): array => [
                'service' => new ServiceResource($service),
                'bookings_count' => (int) $serviceStats->get($service->id)?->bookings_count,
                'revenue' => round((float) $serviceStats->get($service->id)?->revenue, 2),
            ]);
    }

    private function monthExpression(string $column): string
    {
        return DB::getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', {$column})"
            : "DATE_FORMAT({$column}, '%Y-%m')";
    }
}
