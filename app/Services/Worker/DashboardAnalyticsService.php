<?php

namespace App\Services\Worker;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Review;
use App\Models\User;
use App\Models\WorkerPayout;
use App\Models\WorkerSchedule;
use App\Models\WorkerService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardAnalyticsService
{
    /**
     * @return array<string, mixed>
     */
    public function summary(User $worker): array
    {
        $earnings = $this->paidEarnings($worker);
        $paidOut = $this->paidOut($worker);
        $pendingPayout = max(round($earnings - $paidOut, 2), 0);
        $completedBookingsCount = $this->completedBookings($worker)->count();
        $averageRating = round((float) ($worker->workerReviews()->avg('rating') ?: 0), 2);

        return [
            'earnings' => $earnings,
            'pending_payout' => $pendingPayout,
            'completed_bookings' => $completedBookingsCount,
            'cancellations' => $worker->workerBookings()->where('status', Booking::STATUS_CANCELLED)->count(),
            'average_rating' => $averageRating,
            'reviews_count' => $worker->workerReviews()->count(),
            'approved_services_count' => $worker->workerServices()
                ->where('approval_status', WorkerService::StatusApproved)
                ->where('is_active', true)
                ->whereHas('service', fn ($query) => $query->where('is_active', true))
                ->count(),
            'cards' => $this->cards($earnings, $pendingPayout, $completedBookingsCount, $averageRating),
            'earnings_chart' => $this->monthlyEarnings($worker),
            'earnings_periods' => $this->periodEarnings($worker),
            'booking_statuses' => $this->bookingStatuses($worker),
            'top_services' => $this->topServices($worker),
            'recent_reviews' => $this->recentReviews($worker),
            'availability' => $this->availabilitySummary($worker),
        ];
    }

    /**
     * @return array<int, array{label: string, value: float|int, icon: string}>
     */
    private function cards(float $earnings, float $pendingPayout, int $completedBookingsCount, float $averageRating): array
    {
        return [
            ['label' => 'Paid by customers', 'value' => $earnings, 'icon' => 'pi-indian-rupee'],
            ['label' => 'Available for payout', 'value' => $pendingPayout, 'icon' => 'pi-wallet'],
            ['label' => 'Completed bookings', 'value' => $completedBookingsCount, 'icon' => 'pi-check-circle'],
            ['label' => 'Average rating', 'value' => $averageRating, 'icon' => 'pi-star-fill'],
        ];
    }

    /**
     * @return Collection<int, array{label: string, value: float}>
     */
    private function monthlyEarnings(User $worker): Collection
    {
        $monthExpression = $this->monthExpression('paid_at');

        // Worker earnings charts include only paid customer payments for that worker.
        return Payment::query()
            ->where('worker_id', $worker->id)
            ->where('status', Payment::STATUS_PAID)
            ->selectRaw($monthExpression.' as label')
            ->selectRaw('SUM(worker_earning) as value')
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
    private function periodEarnings(User $worker): Collection
    {
        $now = CarbonImmutable::now();

        return collect([
            [
                'label' => 'Today',
                'value' => $this->paidEarningsSince($worker, $now->startOfDay()),
            ],
            [
                'label' => 'This week',
                'value' => $this->paidEarningsSince($worker, $now->startOfWeek()),
            ],
            [
                'label' => 'This month',
                'value' => $this->paidEarningsSince($worker, $now->startOfMonth()),
            ],
        ]);
    }

    private function paidEarningsSince(User $worker, CarbonImmutable $startDate): float
    {
        // Period earnings are based on settled payments since the selected date.
        return round((float) Payment::query()
            ->where('worker_id', $worker->id)
            ->where('status', Payment::STATUS_PAID)
            ->where('paid_at', '>=', $startDate)
            ->sum('worker_earning'), 2);
    }

    /**
     * @return Collection<int, array{label: string, value: int}>
     */
    private function bookingStatuses(User $worker): Collection
    {
        // Worker booking status totals show the provider's current workload mix.
        return $worker->workerBookings()
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
     * @return Collection<int, array{name: string, bookings_count: int, earnings: float}>
     */
    private function topServices(User $worker): Collection
    {
        // Top services are ranked by the worker's booking volume.
        $bookingStats = $worker->workerBookings()
            ->join('services', 'services.id', '=', 'bookings.service_id')
            ->select('services.id', 'services.name')
            ->selectRaw('COUNT(*) as bookings_count')
            ->groupBy('services.id', 'services.name')
            ->orderByDesc('bookings_count')
            ->limit(5)
            ->get()
            ->keyBy('id');

        // Earnings are attached separately so booking volume remains the ranking driver.
        $earningStats = Payment::query()
            ->join('bookings', 'bookings.id', '=', 'payments.booking_id')
            ->select('bookings.service_id')
            ->selectRaw('SUM(payments.worker_earning) as earnings')
            ->where('payments.worker_id', $worker->id)
            ->where('payments.status', Payment::STATUS_PAID)
            ->whereIn('bookings.service_id', $bookingStats->keys())
            ->groupBy('bookings.service_id')
            ->get()
            ->keyBy('service_id');

        return $bookingStats
            ->values()
            ->map(fn ($row): array => [
                'name' => (string) $row->name,
                'bookings_count' => (int) $row->bookings_count,
                'earnings' => round((float) $earningStats->get($row->id)?->earnings, 2),
            ]);
    }

    /**
     * @return Collection<int, array{customer: string, rating: int, review: string|null}>
     */
    private function recentReviews(User $worker): Collection
    {
        // Worker dashboards show the latest customer feedback for reputation awareness.
        return Review::query()
            ->with('customer:id,name')
            ->where('worker_id', $worker->id)
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (Review $review): array => [
                'customer' => (string) ($review->customer?->name ?? 'Customer'),
                'rating' => $review->rating,
                'review' => $review->review,
            ]);
    }

    private function completedBookings(User $worker): HasMany
    {
        // Completed bookings are the basis for worker completion metrics.
        return $worker->workerBookings()->where('status', Booking::STATUS_COMPLETED);
    }

    private function paidEarnings(User $worker): float
    {
        return round((float) $worker->workerPayments()
            ->where('status', Payment::STATUS_PAID)
            ->sum('worker_earning'), 2);
    }

    private function paidOut(User $worker): float
    {
        return round((float) $worker->workerPayouts()
            ->where('status', WorkerPayout::STATUS_PAID)
            ->sum('amount'), 2);
    }

    /**
     * @return array{configured_days: int, working_windows: int, off_days: int, today_windows: array<int, array{start: string, end: string}>, next_off_day: string|null}
     */
    private function availabilitySummary(User $worker): array
    {
        $schedules = $worker->workerSchedules()
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();
        $today = (int) CarbonImmutable::now()->dayOfWeek;

        return [
            'configured_days' => $schedules->pluck('day_of_week')->unique()->count(),
            'working_windows' => $schedules->where('is_off_day', false)->count(),
            'off_days' => $schedules->where('is_off_day', true)->count(),
            'today_windows' => $schedules
                ->where('day_of_week', $today)
                ->where('is_off_day', false)
                ->values()
                ->map(fn (WorkerSchedule $schedule): array => [
                    'start' => substr((string) $schedule->start_time, 0, 5),
                    'end' => substr((string) $schedule->end_time, 0, 5),
                ])
                ->all(),
            'next_off_day' => $this->nextOffDay($schedules),
        ];
    }

    /**
     * @param  Collection<int, WorkerSchedule>  $schedules
     */
    private function nextOffDay(Collection $schedules): ?string
    {
        $today = (int) CarbonImmutable::now()->dayOfWeek;

        $offDay = $schedules
            ->where('is_off_day', true)
            ->sortBy(fn (WorkerSchedule $schedule): int => ($schedule->day_of_week - $today + 7) % 7)
            ->first();

        return $offDay ? WorkerSchedule::Days[$offDay->day_of_week] : null;
    }

    private function monthExpression(string $column): string
    {
        return DB::getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', {$column})"
            : "DATE_FORMAT({$column}, '%Y-%m')";
    }
}
