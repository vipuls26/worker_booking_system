<?php

namespace App\Services\Booking;

use App\Events\BookingCreated;
use App\Events\BookingStatusChanged;
use App\Models\Booking;
use App\Models\BookingRequest;
use App\Models\User;
use App\Models\WorkerService;
use App\Notifications\BookingWorkflowNotification;
use App\Services\Worker\AvailabilityCheckerService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingService
{
    public function __construct(
        private readonly AvailabilityCheckerService $availability,
        private readonly BookingWorkflowService $workflow,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $customer, array $data): Booking
    {
        return DB::transaction(function () use ($customer, $data): Booking {
            $durationMinutes = $this->durationMinutes($data);
            $workerIds = collect($data['worker_ids'] ?? [])
                ->push($data['worker_id'] ?? null)
                ->filter()
                ->unique()
                ->values();
            $workers = User::query()
                ->whereKey($workerIds)
                ->with('role')
                ->get();

            if ($workers->count() !== $workerIds->count() || $workers->contains(fn (User $worker): bool => ! $worker->hasRole('worker'))) {
                throw ValidationException::withMessages(['worker_ids' => ['One or more selected users are not workers.']]);
            }

            if ($workers->contains(fn (User $worker): bool => $worker->is_blocked || $worker->email_verified_at === null || ! $worker->is_verified)) {
                throw ValidationException::withMessages(['worker_ids' => ['One or more selected workers are not verified.']]);
            }

            $verifiedProfileCount = User::query()
                ->whereKey($workerIds)
                ->whereHas('workerProfile', fn ($query) => $query->where('is_verified', true))
                ->count();

            if ($verifiedProfileCount !== $workerIds->count()) {
                throw ValidationException::withMessages(['worker_ids' => ['One or more selected workers do not have an approved worker profile.']]);
            }

            $availableWorkerIds = $workers
                ->filter(fn (User $worker): bool => $this->availability->isAvailable($worker, $data['booking_date'], $data['start_time'], $durationMinutes))
                ->pluck('id');

            if ($availableWorkerIds->count() !== $workerIds->count()) {
                throw ValidationException::withMessages(['start_time' => ['One or more selected workers are not available at this time.']]);
            }

            $workerServices = WorkerService::query()
                ->whereIn('worker_id', $availableWorkerIds)
                ->where('service_id', $data['service_id'])
                ->where('is_active', true)
                ->where('approval_status', WorkerService::StatusApproved)
                ->whereHas('service', fn ($query) => $query->where('is_active', true))
                ->get()
                ->keyBy('worker_id');

            if ($workerServices->count() !== $workerIds->count()) {
                throw ValidationException::withMessages(['service_id' => ['One or more selected workers do not offer this service.']]);
            }

            $selectedWorkerId = $workerIds->count() === 1 ? (int) $availableWorkerIds->first() : null;
            $pricing = $selectedWorkerId ? $workerServices->get($selectedWorkerId) : $workerServices->sortBy('price')->first();

            $booking = Booking::create([
                'customer_id' => $customer->id,
                'worker_id' => $selectedWorkerId,
                'service_id' => $data['service_id'],
                'booking_date' => $data['booking_date'],
                'booking_time' => $data['start_time'],
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'] ?? CarbonImmutable::parse($data['booking_date'].' '.$data['start_time'])->addMinutes($durationMinutes)->format('H:i'),
                'address' => $data['address'],
                'notes' => $data['issue_description'],
                'issue_description' => $data['issue_description'],
                'total_amount' => $pricing ? $this->totalAmount($pricing, $durationMinutes) : 0,
                'status' => $selectedWorkerId ? Booking::STATUS_PENDING : Booking::STATUS_REQUESTED,
            ]);

            $this->workflow->record(
                booking: $booking,
                fromStatus: null,
                toStatus: $booking->status,
                event: $selectedWorkerId ? 'booking_created' : 'booking_request_created',
                actor: $customer,
            );

            $workerServices->keys()->each(function (int $workerId) use ($booking, $selectedWorkerId): void {
                $booking->bookingRequests()->create([
                    'worker_id' => $workerId,
                    'status' => $selectedWorkerId === $workerId ? BookingRequest::STATUS_SELECTED : BookingRequest::STATUS_PENDING,
                    'responded_at' => $selectedWorkerId === $workerId ? now() : null,
                ]);
            });

            $booking = $booking->refresh()->load(['customer.role', 'worker.role', 'service', 'bookingRequests.worker.role', 'activities.actor.role', 'review.customer.role', 'workerReview.worker.role']);

            $booking->bookingRequests->each(function (BookingRequest $bookingRequest) use ($booking): void {
                $bookingRequest->worker?->notify(new BookingWorkflowNotification(
                    booking: $booking,
                    event: 'booking_received',
                    title: 'New booking request',
                    message: sprintf('%s requested %s.', $booking->customer?->name ?? 'A customer', $booking->service?->name ?? 'a service'),
                ));
            });

            event(new BookingCreated($booking));

            return $booking;
        });
    }

    public function customerBookings(User $customer, ?string $status, int $perPage = 10): LengthAwarePaginator
    {
        return $customer->customerBookings()
            ->with(['worker.role', 'service', 'bookingRequests.worker.role', 'activities.actor.role', 'review.customer.role', 'workerReview.worker.role'])
            ->when($status, fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate($perPage);
    }

    public function workerBookings(User $worker, ?string $status, int $perPage = 10): LengthAwarePaginator
    {
        return $worker->workerBookings()
            ->with(['customer.role', 'service', 'activities.actor.role', 'review.customer.role', 'workerReview.worker.role'])
            ->when($status, fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate($perPage);
    }

    public function workerRequests(User $worker, ?string $status, int $perPage = 10): LengthAwarePaginator
    {
        return BookingRequest::query()
            ->with(['booking.customer.role', 'booking.service', 'worker.role'])
            ->where('worker_id', $worker->id)
            ->when($status, fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate($perPage);
    }

    public function respondToRequest(BookingRequest $bookingRequest, User $worker, string $status): BookingRequest
    {
        abort_if($bookingRequest->worker_id !== $worker->id, 404);

        if ($bookingRequest->status !== BookingRequest::STATUS_PENDING) {
            throw ValidationException::withMessages(['status' => ['This request has already been answered.']]);
        }

        if ($bookingRequest->booking()->where('status', '!=', Booking::STATUS_REQUESTED)->exists()) {
            throw ValidationException::withMessages(['status' => ['This booking request is no longer open.']]);
        }

        $bookingRequest->update([
            'status' => $status,
            'responded_at' => now(),
        ]);

        $this->workflow->record(
            booking: $bookingRequest->booking,
            fromStatus: $bookingRequest->booking->status,
            toStatus: $bookingRequest->booking->status,
            event: $status === BookingRequest::STATUS_ACCEPTED ? 'booking_request_accepted' : 'booking_request_rejected',
            actor: $worker,
        );

        $bookingRequest = $bookingRequest->refresh()->load(['booking.customer.role', 'booking.service', 'worker.role']);

        $bookingRequest->booking->customer?->notify(new BookingWorkflowNotification(
            booking: $bookingRequest->booking,
            event: $status === BookingRequest::STATUS_ACCEPTED ? 'booking_accepted' : 'booking_rejected',
            title: $status === BookingRequest::STATUS_ACCEPTED ? 'Worker accepted your request' : 'Worker rejected your request',
            message: sprintf('%s %s your %s request.', $worker->name, $status === BookingRequest::STATUS_ACCEPTED ? 'accepted' : 'rejected', $bookingRequest->booking->service?->name ?? 'booking'),
        ));

        return $bookingRequest;
    }

    public function selectFinalWorker(Booking $booking, User $customer, int $bookingRequestId): Booking
    {
        abort_if($booking->customer_id !== $customer->id, 404);

        return DB::transaction(function () use ($booking, $bookingRequestId): Booking {
            if ($booking->status !== Booking::STATUS_REQUESTED) {
                throw ValidationException::withMessages(['booking_request_id' => ['This booking is not waiting for worker selection.']]);
            }

            $bookingRequest = $booking->bookingRequests()
                ->whereKey($bookingRequestId)
                ->where('status', BookingRequest::STATUS_ACCEPTED)
                ->firstOrFail();

            $booking->update([
                'worker_id' => $bookingRequest->worker_id,
            ]);

            $booking = $this->workflow->transition(
                booking: $booking,
                nextStatus: Booking::STATUS_PENDING,
                actor: $customer,
                context: ['event' => 'worker_selected'],
            );

            $bookingRequest->update([
                'status' => BookingRequest::STATUS_SELECTED,
                'responded_at' => now(),
            ]);

            $booking->bookingRequests()
                ->whereKeyNot($bookingRequest->id)
                ->whereIn('status', [BookingRequest::STATUS_PENDING, BookingRequest::STATUS_ACCEPTED])
                ->update([
                    'status' => BookingRequest::STATUS_CANCELLED,
                    'responded_at' => now(),
                ]);

            $booking = $booking->refresh()->load(['customer.role', 'worker.role', 'service', 'bookingRequests.worker.role', 'activities.actor.role', 'review.customer.role', 'workerReview.worker.role']);
            event(new BookingStatusChanged($booking, Booking::STATUS_REQUESTED, $booking->status));

            return $booking;
        });
    }

    public function updateStatus(Booking $booking, string $status, ?string $reason = null, ?User $actor = null): Booking
    {
        $oldStatus = $booking->status;

        $booking = $this->workflow
            ->transition($booking, $status, $actor, $reason)
            ->load(['customer.role', 'worker.role', 'service', 'activities.actor.role', 'review.customer.role', 'workerReview.worker.role']);

        $this->notifyBookingStatusChanged($booking, $status, $actor);

        event(new BookingStatusChanged($booking, $oldStatus, $status));

        return $booking;
    }

    public function cancelByCustomer(Booking $booking, User $customer, ?string $reason = null): Booking
    {
        abort_if($booking->customer_id !== $customer->id, 404);

        $this->workflow->assertCustomerCanCancel($booking);

        return $this->updateStatus($booking, Booking::STATUS_CANCELLED, $reason, $customer);
    }

    private function durationMinutes(array $data): int
    {
        if (! empty($data['end_time'])) {
            return CarbonImmutable::parse($data['booking_date'].' '.$data['start_time'])
                ->diffInMinutes(CarbonImmutable::parse($data['booking_date'].' '.$data['end_time']));
        }

        return 60;
    }

    private function totalAmount(WorkerService $workerService, int $durationMinutes): float
    {
        if ($workerService->pricing_type === WorkerService::PricingHourly) {
            $hours = max($workerService->minimum_hours ?: 1, (int) ceil($durationMinutes / 60));

            return (float) $workerService->price * $hours;
        }

        return (float) $workerService->price;
    }

    private function notifyBookingStatusChanged(Booking $booking, string $status, ?User $actor): void
    {
        if ($status === Booking::STATUS_CANCELLED && $actor?->id === $booking->customer_id) {
            $booking->worker?->notify(new BookingWorkflowNotification(
                booking: $booking,
                event: 'booking_cancelled',
                title: 'Booking cancelled',
                message: sprintf('%s cancelled a booking.', $booking->customer?->name ?? 'Customer'),
            ));

            return;
        }

        $messages = [
            Booking::STATUS_ACCEPTED => ['booking_accepted', 'Booking accepted', 'Your booking has been accepted.'],
            Booking::STATUS_REJECTED => ['booking_rejected', 'Booking rejected', 'Your booking has been rejected.'],
            Booking::STATUS_IN_PROGRESS => ['work_started', 'Work started', 'Your worker has started the job.'],
            Booking::STATUS_COMPLETED => ['work_completed', 'Work completed', 'Your booking has been completed.'],
            Booking::STATUS_CANCELLED => ['booking_cancelled', 'Booking cancelled', 'Your booking has been cancelled.'],
        ];

        if (! isset($messages[$status])) {
            return;
        }

        [$event, $title, $message] = $messages[$status];

        $booking->customer?->notify(new BookingWorkflowNotification(
            booking: $booking,
            event: $event,
            title: $title,
            message: $message,
        ));
    }
}
