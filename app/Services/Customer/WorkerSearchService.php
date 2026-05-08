<?php

namespace App\Services\Customer;

use App\Models\Booking;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestWorker;
use App\Models\User;
use App\Models\WorkerService;
use App\Services\Worker\AvailabilityCheckerService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class WorkerSearchService
{
    public function __construct(private readonly AvailabilityCheckerService $availability) {}

    public function paginate(Request $request): LengthAwarePaginator
    {
        $query = User::query()
            ->select('users.*')
            ->whereHas('role', fn ($query) => $query->where('slug', 'worker'))
            ->where('users.is_blocked', false)
            ->whereNotNull('users.email_verified_at')
            ->where('users.is_verified', true)
            ->whereHas('workerProfile', fn ($query) => $query->where('is_verified', true))
            ->whereHas('workerServices', fn ($query) => $query
                ->where('is_active', true)
                ->where('approval_status', 'approved')
                ->whereHas('service', fn ($query) => $query->where('is_active', true)))
            ->with([
                'role',
                'workerProfile',
                'workerServices' => fn ($query) => $query
                    ->where('is_active', true)
                    ->where('approval_status', 'approved')
                    ->whereHas('service', fn ($query) => $query->where('is_active', true))
                    ->with('service:id,name,slug,icon,is_active')
                    ->orderBy('price'),
            ])
            ->withAvg('workerReviews as rating_average', 'rating')
            ->withCount('workerReviews as reviews_count')
            ->withMin(['workerServices as min_service_price' => fn ($query) => $query
                ->where('is_active', true)
                ->where('approval_status', 'approved')
                ->whereHas('service', fn ($query) => $query->where('is_active', true))], 'price')
            ->withCount(['workerServices as active_services_count' => fn ($query) => $query
                ->where('is_active', true)
                ->where('approval_status', 'approved')
                ->whereHas('service', fn ($query) => $query->where('is_active', true))]);

        $this->applyServiceFilters($query, $request);
        $this->applyRatingFilter($query, $request);
        $this->applyCityFilter($query, $request);
        $this->applyAvailabilityFilter($query, $request);
        $this->applySorting($query, $request);

        return $query->paginate($request->integer('per_page', 12));
    }

    public function findWorker(User $worker): User
    {
        abort_unless(
            $worker->hasRole('worker')
            && ! $worker->is_blocked
            && $worker->email_verified_at !== null
            && $worker->is_verified
            && $worker->workerProfile()->where('is_verified', true)->exists(),
            404,
        );

        $worker = $worker->load([
            'role',
            'workerProfile',
            'workerServices' => fn ($query) => $query
                ->where('is_active', true)
                ->where('approval_status', 'approved')
                ->whereHas('service', fn ($query) => $query->where('is_active', true))
                ->with('service:id,name,slug,icon,is_active')
                ->orderBy('price'),
            'workerSchedules' => fn ($query) => $query
                ->orderBy('day_of_week')
                ->orderBy('start_time'),
            'workerReviews' => fn ($query) => $query
                ->with('customer.role')
                ->latest()
                ->limit(5),
        ])
            ->loadAvg('workerReviews as rating_average', 'rating')
            ->loadCount([
                'workerReviews as reviews_count',
                'workerServices as active_services_count' => fn ($query) => $query
                    ->where('is_active', true)
                    ->where('approval_status', 'approved')
                    ->whereHas('service', fn ($query) => $query->where('is_active', true)),
            ]);

        $worker->setAttribute('min_service_price', $worker->workerServices->min('price'));

        return $worker;
    }

    private function applyServiceFilters($query, Request $request): void
    {
        $query
            ->when($request->filled('service_id'), function ($query) use ($request): void {
                $query->whereHas('workerServices', function ($query) use ($request): void {
                    $query
                        ->where('is_active', true)
                        ->where('approval_status', 'approved')
                        ->whereHas('service', fn ($query) => $query->where('is_active', true))
                        ->where('service_id', $request->integer('service_id'));
                });
            })
            ->when($request->filled('service_slug'), function ($query) use ($request): void {
                $query->whereHas('workerServices', function ($query) use ($request): void {
                    $query
                        ->where('is_active', true)
                        ->where('approval_status', 'approved')
                        ->whereHas('service', fn ($query) => $query->where('is_active', true))
                        ->whereHas('service', fn ($query) => $query->where('slug', $request->string('service_slug')->toString()));
                });
            })
            ->when($request->filled('max_price'), function ($query) use ($request): void {
                $query->whereHas('workerServices', function ($query) use ($request): void {
                    $query
                        ->where('is_active', true)
                        ->where('approval_status', 'approved')
                        ->whereHas('service', fn ($query) => $query->where('is_active', true))
                        ->where('price', '<=', $request->input('max_price'));
                });
            });
    }

    private function applyCityFilter($query, Request $request): void
    {
        $query->when($request->filled('city'), function ($query) use ($request): void {
            $query->whereHas('workerProfile', function ($query) use ($request): void {
                $query->where('city', 'like', '%'.$request->string('city')->toString().'%');
            });
        });
    }

    private function applyAvailabilityFilter($query, Request $request): void
    {
        if (! $request->filled('available_date')) {
            return;
        }

        $time = $request->string('available_time')->toString();
        $durationMinutes = max(15, $request->integer('slot_minutes', 60));

        $query->whereIn('users.id', function ($query) use ($request, $time, $durationMinutes): void {
            $query
                ->select('worker_id')
                ->from('worker_schedules')
                ->where('day_of_week', CarbonImmutable::parse($request->string('available_date')->toString())->dayOfWeek)
                ->where('is_off_day', false)
                ->when($time !== '', function ($query) use ($time, $durationMinutes): void {
                    $endTime = CarbonImmutable::parse($time)->addMinutes($durationMinutes)->format('H:i');

                    $query
                        ->where('start_time', '<=', $time)
                        ->where('end_time', '>=', $endTime);
                });
        });

        if ($time === '') {
            return;
        }

        $endTime = CarbonImmutable::parse($request->string('available_date')->toString().' '.$time)
            ->addMinutes($durationMinutes)
            ->format('H:i:s');

        $query->whereDoesntHave('workerBookings', function ($query) use ($request, $time, $endTime): void {
            $query
                ->whereDate('booking_date', $request->string('available_date')->toString())
                ->whereIn('status', Booking::ActiveStatuses)
                ->where(function ($query) use ($time, $endTime): void {
                    $query
                        ->where('start_time', '<', $endTime)
                        ->where('end_time', '>', $time);
                });
        });

        $query->whereNotIn('users.id', function ($query) use ($request, $time, $endTime): void {
            $query
                ->select('service_request_workers.worker_id')
                ->from('service_request_workers')
                ->join('service_requests', 'service_requests.id', '=', 'service_request_workers.service_request_id')
                ->where('service_request_workers.status', ServiceRequestWorker::STATUS_ACCEPTED)
                ->where('service_requests.status', ServiceRequest::STATUS_OPEN)
                ->whereDate('service_requests.requested_date', $request->string('available_date')->toString())
                ->where('service_requests.start_time', '<', $endTime)
                ->where('service_requests.end_time', '>', $time);
        });
    }

    private function applyRatingFilter($query, Request $request): void
    {
        if ($request->filled('min_rating')) {
            $query->having('rating_average', '>=', (float) $request->input('min_rating'));
        }
    }

    private function applySorting($query, Request $request): void
    {
        match ($request->string('sort')->toString()) {
            'price_high' => $query->orderByDesc('min_service_price'),
            'rating' => $query->orderByDesc('rating_average'),
            'experience' => $query->join('worker_profiles as sort_profiles', 'sort_profiles.user_id', '=', 'users.id')
                ->orderByDesc('sort_profiles.experience_years'),
            default => $query->orderBy('min_service_price')->latest('users.id'),
        };
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function availabilityForDetails(User $worker, ?string $date, int $slotMinutes = 60, ?int $serviceId = null): Collection
    {
        if (! $date) {
            return collect();
        }

        $workerService = $serviceId
            ? $worker->workerServices
                ->first(fn (WorkerService $workerService): bool => (int) $workerService->service_id === $serviceId)
            : null;

        return collect($this->availability->slotsForDate($worker, $date, $slotMinutes, $workerService));
    }
}
