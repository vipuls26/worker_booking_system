<?php

namespace App\Services\Admin;

use App\Http\Resources\ServiceResource;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;
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
            'total_revenue' => (float) Payment::query()->where('status', Payment::STATUS_PAID)->sum('platform_commission'),
            'gross_booking_value' => (float) Payment::query()->where('status', Payment::STATUS_PAID)->sum('amount'),
            'worker_payouts' => (float) Payment::query()->where('status', Payment::STATUS_PAID)->sum('worker_earning'),
            'cards' => $this->cards(),
            'revenue_reports' => [
                'monthly' => $this->monthlyRevenue(),
                'periods' => $this->periodRevenue(),
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
            ['label' => 'Commission revenue', 'value' => (float) Payment::query()->where('status', Payment::STATUS_PAID)->sum('platform_commission'), 'icon' => 'pi-indian-rupee'],
        ];
    }

    /**
     * @return Collection<int, array{label: string, value: float}>
     */
    private function monthlyRevenue(): Collection
    {
        $monthExpression = $this->monthExpression('paid_at');

        return Payment::query()
            ->selectRaw($monthExpression.' as label')
            ->selectRaw('SUM(platform_commission) as value')
            ->where('status', Payment::STATUS_PAID)
            ->whereNotNull('paid_at')
            ->groupBy('label')
            ->orderBy('label')
            ->limit(12)
            ->get()
            ->map(fn (Payment $payment): array => [
                'label' => (string) $payment->label,
                'value' => round((float) $payment->value, 2),
            ]);
    }

    /**
     * @return Collection<int, array{label: string, value: float}>
     */
    private function revenueByStatus(): Collection
    {
        return Payment::query()
            ->select('status as label')
            ->selectRaw('SUM(platform_commission) as value')
            ->groupBy('status')
            ->orderByDesc('value')
            ->get()
            ->map(fn (Payment $payment): array => [
                'label' => (string) $payment->label,
                'value' => round((float) $payment->value, 2),
            ]);
    }

    /**
     * @return Collection<int, array{label: string, value: float}>
     */
    private function periodRevenue(): Collection
    {
        $now = CarbonImmutable::now();

        return collect([
            [
                'label' => 'Today',
                'value' => $this->paidCommissionSince($now->startOfDay()),
            ],
            [
                'label' => 'This week',
                'value' => $this->paidCommissionSince($now->startOfWeek()),
            ],
            [
                'label' => 'This month',
                'value' => $this->paidCommissionSince($now->startOfMonth()),
            ],
        ]);
    }

    private function paidCommissionSince(CarbonImmutable $startDate): float
    {
        return round((float) Payment::query()
            ->where('status', Payment::STATUS_PAID)
            ->where('paid_at', '>=', $startDate)
            ->sum('platform_commission'), 2);
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
        $bookingStats = Booking::query()
            ->select('service_id')
            ->selectRaw('COUNT(*) as bookings_count')
            ->groupBy('service_id')
            ->orderByDesc('bookings_count')
            ->limit(5)
            ->get()
            ->keyBy('service_id');

        $revenueStats = Payment::query()
            ->join('bookings', 'bookings.id', '=', 'payments.booking_id')
            ->select('bookings.service_id')
            ->selectRaw('SUM(payments.platform_commission) as revenue')
            ->where('payments.status', Payment::STATUS_PAID)
            ->whereIn('bookings.service_id', $bookingStats->keys())
            ->groupBy('bookings.service_id')
            ->get()
            ->keyBy('service_id');

        return Service::query()
            ->whereIn('id', $bookingStats->keys())
            ->get()
            ->sortByDesc(fn (Service $service): int => (int) $bookingStats->get($service->id)?->bookings_count)
            ->values()
            ->map(fn (Service $service): array => [
                'service' => new ServiceResource($service),
                'bookings_count' => (int) $bookingStats->get($service->id)?->bookings_count,
                'revenue' => round((float) $revenueStats->get($service->id)?->revenue, 2),
            ]);
    }

    private function monthExpression(string $column): string
    {
        return DB::getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', {$column})"
            : "DATE_FORMAT({$column}, '%Y-%m')";
    }
}
