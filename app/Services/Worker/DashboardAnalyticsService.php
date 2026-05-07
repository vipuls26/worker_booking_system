<?php

namespace App\Services\Worker;

use App\Models\Booking;
use App\Models\Review;
use App\Models\User;
use App\Models\WorkerSchedule;
use App\Models\WorkerService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardAnalyticsService
{
    /**
     * @return array<string, mixed>
     */
    public function summary(User $worker): array
    {
        return [
            'earnings' => round((float) $this->completedBookings($worker)->sum('total_amount'), 2),
            'completed_bookings' => $this->completedBookings($worker)->count(),
            'cancellations' => $worker->workerBookings()->where('status', Booking::STATUS_CANCELLED)->count(),
            'average_rating' => round((float) ($worker->workerReviews()->avg('rating') ?: 0), 2),
            'reviews_count' => $worker->workerReviews()->count(),
            'approved_services_count' => $worker->workerServices()
                ->where('approval_status', WorkerService::StatusApproved)
                ->where('is_active', true)
                ->whereHas('service', fn ($query) => $query->where('is_active', true))
                ->count(),
            'cards' => $this->cards($worker),
            'earnings_chart' => $this->monthlyEarnings($worker),
            'booking_statuses' => $this->bookingStatuses($worker),
            'top_services' => $this->topServices($worker),
            'recent_reviews' => $this->recentReviews($worker),
            'availability' => $this->availabilitySummary($worker),
        ];
    }

    /**
     * @return array<int, array{label: string, value: float|int, icon: string}>
     */
    private function cards(User $worker): array
    {
        return [
            ['label' => 'Earnings', 'value' => round((float) $this->completedBookings($worker)->sum('total_amount'), 2), 'icon' => 'pi-indian-rupee'],
            ['label' => 'Completed bookings', 'value' => $this->completedBookings($worker)->count(), 'icon' => 'pi-check-circle'],
            ['label' => 'Cancellations', 'value' => $worker->workerBookings()->where('status', Booking::STATUS_CANCELLED)->count(), 'icon' => 'pi-times-circle'],
            ['label' => 'Average rating', 'value' => round((float) ($worker->workerReviews()->avg('rating') ?: 0), 2), 'icon' => 'pi-star-fill'],
        ];
    }

    /**
     * @return Collection<int, array{label: string, value: float}>
     */
    private function monthlyEarnings(User $worker): Collection
    {
        $monthExpression = $this->monthExpression('booking_date');

        return $worker->workerBookings()
            ->selectRaw($monthExpression.' as label')
            ->selectRaw('SUM(total_amount) as value')
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
     * @return Collection<int, array{label: string, value: int}>
     */
    private function bookingStatuses(User $worker): Collection
    {
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
        return $worker->workerBookings()
            ->join('services', 'services.id', '=', 'bookings.service_id')
            ->select('services.name')
            ->selectRaw('COUNT(*) as bookings_count')
            ->selectRaw('SUM(CASE WHEN bookings.status = ? THEN bookings.total_amount ELSE 0 END) as earnings', [Booking::STATUS_COMPLETED])
            ->groupBy('services.id', 'services.name')
            ->orderByDesc('bookings_count')
            ->limit(5)
            ->get()
            ->map(fn ($row): array => [
                'name' => (string) $row->name,
                'bookings_count' => (int) $row->bookings_count,
                'earnings' => round((float) $row->earnings, 2),
            ]);
    }

    /**
     * @return Collection<int, array{customer: string, rating: int, review: string|null}>
     */
    private function recentReviews(User $worker): Collection
    {
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

    private function completedBookings(User $worker)
    {
        return $worker->workerBookings()->where('status', Booking::STATUS_COMPLETED);
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
