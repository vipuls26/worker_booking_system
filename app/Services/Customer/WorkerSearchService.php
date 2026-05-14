<?php

namespace App\Services\Customer;

use App\Models\User;
use App\Models\WorkerService;
use App\Services\Booking\BookingConflictService;
use App\Services\Worker\AvailabilityCheckerService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class WorkerSearchService
{
    public function __construct(
        private readonly AvailabilityCheckerService $availability,
        private readonly BookingConflictService $bookingConflicts,
    ) {}

    public function paginate(Request $request): LengthAwarePaginator
    {
        // Public worker search shows only verified workers with approved active services.
        $query = User::query()
            ->select('users.*')
            ->whereHas('role', fn ($query) => $query->where('slug', 'worker'))
            ->where('users.account_status', User::STATUS_ACTIVE)
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

        $this->applySearchFilter($query, $request);
        $this->applyServiceFilters($query, $request);
        $this->applyRatingFilter($query, $request);
        $this->applyCityFilter($query, $request);
        $this->applyAvailabilityFilter($query, $request);
        $this->applySorting($query, $request);

        return $query->paginate($request->integer('per_page', 12));
    }

    public function findWorker(User $worker): User
    {
        // Worker detail pages should be available only for marketplace-ready workers.
        abort_unless(
            $worker->hasRole('worker')
            && $worker->isActive()
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

    /**
     * Apply the broad marketplace search term across worker, profile, and service data.
     *
     * @param  Builder<User>  $query
     */
    private function applySearchFilter(Builder $query, Request $request): void
    {
        // Search should help customers find workers by name, city, bio, skills, or service names.
        if (! $request->filled('search')) {
            return;
        }

        $searchTerm = $request->string('search')->trim()->toString();

        $query->where(function (Builder $builder) use ($searchTerm): void {
            $builder
                ->where('users.name', 'like', "%{$searchTerm}%")
                ->orWhereHas('workerProfile', function (Builder $profileQuery) use ($searchTerm): void {
                    $profileQuery
                        ->where('city', 'like', "%{$searchTerm}%")
                        ->orWhere('bio', 'like', "%{$searchTerm}%")
                        ->orWhereJsonContains('skills', $searchTerm);
                })
                ->orWhereHas('workerServices', function (Builder $serviceQuery) use ($searchTerm): void {
                    $serviceQuery
                        ->where('is_active', true)
                        ->where('approval_status', 'approved')
                        ->where(function (Builder $descriptionQuery) use ($searchTerm): void {
                            $descriptionQuery
                                ->where('description', 'like', "%{$searchTerm}%")
                                ->orWhereHas('service', function (Builder $catalogQuery) use ($searchTerm): void {
                                    $catalogQuery
                                        ->where('name', 'like', "%{$searchTerm}%")
                                        ->orWhere('slug', 'like', "%{$searchTerm}%")
                                        ->orWhere('description', 'like', "%{$searchTerm}%");
                                });
                        });
                });
        });
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
        // Without a date, search should not restrict workers by schedule availability.
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

        // Date-only availability checks stop after confirming workers have a working window that day.
        if ($time === '') {
            return;
        }

        $endTime = CarbonImmutable::parse($request->string('available_date')->toString().' '.$time)
            ->addMinutes($durationMinutes)
            ->format('H:i:s');

        $this->bookingConflicts->excludeConflictingWorkers(
            query: $query,
            bookingDate: $request->string('available_date')->toString(),
            startTime: $time,
            endTime: $endTime,
        );
    }

    private function applyRatingFilter($query, Request $request): void
    {
        // Minimum rating is optional because new workers may not have reviews yet.
        if ($request->filled('min_rating')) {
            $query->having('rating_average', '>=', (float) $request->input('min_rating'));
        }
    }

    private function applySorting($query, Request $request): void
    {
        $sort = $request->string('sort')->toString();

        // Customers expect pricing, rating, and experience sorts to be explicit and stable.
        if ($sort === 'price_high') {
            $query->orderByDesc('min_service_price')->latest('users.id');

            return;
        }

        // Rating-first sorting should still keep new ties predictable.
        if ($sort === 'rating') {
            $query->orderByDesc('rating_average')->latest('users.id');

            return;
        }

        // Experience sorting needs the worker profile join because the value lives outside users.
        if ($sort === 'experience') {
            $query
                ->join('worker_profiles as sort_profiles', 'sort_profiles.user_id', '=', 'users.id')
                ->orderByDesc('sort_profiles.experience_years')
                ->latest('users.id');

            return;
        }

        $query->orderBy('min_service_price')->latest('users.id');
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function availabilityForDetails(User $worker, ?string $date, int $slotMinutes = 60, ?int $serviceId = null): Collection
    {
        // Worker detail pages show no slots until the customer chooses a date.
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
