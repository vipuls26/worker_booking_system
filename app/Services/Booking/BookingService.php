<?php

namespace App\Services\Booking;

use App\Events\BookingCreated;
use App\Events\BookingStatusChanged;
use App\Models\Booking;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestWorker;
use App\Models\User;
use App\Models\WorkerService;
use App\Notifications\ServiceRequestWorkflowNotification;
use App\Services\Audit\AuditLogger;
use App\Services\CommissionSettingService;
use App\Services\Worker\WorkerScheduleService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingService
{
    public function __construct(
        private readonly BookingWorkflowService $workflow,
        private readonly WorkerMatchingService $workerMatching,
        private readonly AvailabilityService $availability,
        private readonly WorkerScheduleService $workerSchedules,
        private readonly AuditLogger $audit,
        private readonly CommissionSettingService $commissionSettings,
        private readonly BookAgainService $bookAgain,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $customer, array $data): ServiceRequest
    {
        return DB::transaction(function () use ($customer, $data): ServiceRequest {
            // Partially blocked customers may log in, but they cannot open new booking work.
            if (! $customer->canCreateBookings()) {
                throw ValidationException::withMessages([
                    'account' => ['Your account is temporarily restricted from creating new bookings.'],
                ]);
            }

            $address = $data['address'] ?? $customer->customerProfile?->address;

            // Customers must have a usable service address before workers can quote or travel.
            if (! $address) {
                throw ValidationException::withMessages([
                    'address' => ['Add a service address or save a default address in your profile.'],
                ]);
            }

            $sourceBooking = $this->bookAgain->assertSourceCanCreate($customer, $data);
            $durationMinutes = $this->durationMinutes($data);
            $this->ensureDirectWorkerCanReceiveRequestedSlot($data);

            $workerServices = $this->workerMatching->matchingWorkerServices([
                ...$data,
                'duration_minutes' => $durationMinutes,
            ]);

            // Booking requests should only be opened when at least one verified worker can serve the slot.
            if ($workerServices->isEmpty()) {
                throw ValidationException::withMessages([
                    'service_id' => ['No verified workers are available for this service at the selected date and time. Please choose another time or service.'],
                ]);
            }

            $pricing = $workerServices->sortBy('price')->first();

            $serviceRequest = ServiceRequest::create([
                'customer_id' => $customer->id,
                'selected_worker_id' => null,
                'booking_id' => null,
                'recreated_from_booking_id' => $sourceBooking?->id,
                'service_id' => $data['service_id'],
                'requested_date' => $data['booking_date'],
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'] ?? CarbonImmutable::parse($data['booking_date'].' '.$data['start_time'])->addMinutes($durationMinutes)->format('H:i'),
                'address' => $address,
                'description' => $data['issue_description'],
                'estimated_amount' => $pricing ? $this->totalAmount($pricing, $durationMinutes) : 0,
                'status' => ServiceRequest::STATUS_OPEN,
            ]);

            $workerServices->each(function (WorkerService $workerService) use ($serviceRequest): void {
                $durationMinutes = $this->durationMinutes([
                    'booking_date' => $serviceRequest->requested_date->toDateString(),
                    'start_time' => $serviceRequest->start_time,
                    'end_time' => $serviceRequest->end_time,
                ]);

                $serviceRequest->workers()->create([
                    'worker_id' => $workerService->worker_id,
                    'worker_service_id' => $workerService->id,
                    'pricing_type' => $workerService->pricing_type,
                    'quoted_price' => $this->totalAmount($workerService, $durationMinutes),
                    'minimum_hours' => $workerService->minimum_hours,
                    'status' => ServiceRequestWorker::STATUS_PENDING,
                    'responded_at' => null,
                ]);
            });

            $serviceRequest = $serviceRequest->refresh()->load($this->serviceRequestRelations());

            $this->audit->record('service_request.created', $customer, $serviceRequest, [
                'service_id' => $serviceRequest->service_id,
                'target_worker_id' => $data['worker_id'] ?? null,
                'matched_workers_count' => $serviceRequest->workers->count(),
                'requested_date' => $serviceRequest->requested_date?->toDateString(),
                'start_time' => $serviceRequest->start_time,
                'end_time' => $serviceRequest->end_time,
            ]);

            $this->notifyWorkersAboutServiceRequest($serviceRequest);

            return $serviceRequest;
        });
    }

    public function customerBookings(User $customer, ?string $status, int $perPage = 10): LengthAwarePaginator
    {
        // Customers should only see their own service requests, optionally narrowed by workflow status.
        return ServiceRequest::query()
            ->where('customer_id', $customer->id)
            ->with($this->serviceRequestRelations())
            ->when($status, fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate($perPage);
    }

    public function workerBookings(User $worker, ?string $status, int $perPage = 10): LengthAwarePaginator
    {
        // Workers need their assigned bookings with related customer, service, activity, and review context.
        return $worker->workerBookings()
            ->with(['customer.role', 'selectedWorker.role', 'service', 'activities.actor.role', 'review.customer.role', 'workerReview.worker.role'])
            ->when($status, fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate($perPage);
    }

    public function workerRequests(User $worker, ?string $status, int $perPage = 10): LengthAwarePaginator
    {
        // Workers respond from their own request queue, so never expose other workers' invitations.
        return ServiceRequestWorker::query()
            ->with(['serviceRequest.customer.role', 'serviceRequest.service', 'serviceRequest.booking', 'worker.role'])
            ->where('worker_id', $worker->id)
            ->when(
                $status,
                fn ($query) => $query->where('status', $status),
                fn ($query) => $query->actionableForWorker(),
            )
            ->latest()
            ->paginate($perPage);
    }

    public function respondToRequest(ServiceRequestWorker $serviceRequestWorker, User $worker, string $status, ?string $reason = null): ServiceRequestWorker
    {
        abort_if($serviceRequestWorker->worker_id !== $worker->id, 404);

        // Partially blocked workers cannot accept new work while the restriction is active.
        if ($status === ServiceRequestWorker::STATUS_ACCEPTED && ! $worker->canTakeNewWork()) {
            throw ValidationException::withMessages([
                'account' => ['Your account is temporarily restricted from accepting new booking requests.'],
            ]);
        }

        // A worker response is final for the pending invitation to avoid conflicting quotes.
        if ($serviceRequestWorker->status !== ServiceRequestWorker::STATUS_PENDING) {
            throw ValidationException::withMessages(['status' => ['This request has already been answered.']]);
        }

        // Closed service requests cannot accept new worker responses after customer action or cancellation.
        if ($serviceRequestWorker->serviceRequest()->where('status', '!=', ServiceRequest::STATUS_OPEN)->exists()) {
            throw ValidationException::withMessages(['status' => ['This booking request is no longer open.']]);
        }

        // Accepted responses reserve real availability, so re-check eligibility and schedule before saving.
        if ($status === ServiceRequestWorker::STATUS_ACCEPTED) {
            $this->ensureWorkerCanReceiveBooking($worker, 'status');

            $serviceRequest = $serviceRequestWorker->serviceRequest;
            $durationMinutes = $this->durationMinutes([
                'booking_date' => $serviceRequest->requested_date->toDateString(),
                'start_time' => $serviceRequest->start_time,
                'end_time' => $serviceRequest->end_time,
            ]);

            // Worker availability may have changed since the customer first opened the request.
            if (! $this->availability->isWorkerAvailable($worker, $serviceRequest->requested_date->toDateString(), $serviceRequest->start_time, $durationMinutes)) {
                throw ValidationException::withMessages(['status' => ['You are no longer available for this booking slot.']]);
            }
        }

        $serviceRequestWorker->update([
            'status' => $status,
            'response_reason' => $reason,
            'responded_at' => now(),
        ]);

        $serviceRequestWorker = $serviceRequestWorker->refresh()->load(['serviceRequest.customer.role', 'serviceRequest.service', 'worker.role']);

        $this->audit->record('service_request_worker.responded', $worker, $serviceRequestWorker, [
            'service_request_id' => $serviceRequestWorker->service_request_id,
            'status' => $serviceRequestWorker->status,
            'response_reason' => $serviceRequestWorker->response_reason,
            'service_id' => $serviceRequestWorker->serviceRequest?->service_id,
        ]);

        // A one-worker request can become a confirmed booking as soon as that worker accepts.
        if ($status === ServiceRequestWorker::STATUS_ACCEPTED && $serviceRequestWorker->serviceRequest->workers()->count() === 1) {
            return $this->selectSingleAcceptedWorkerRequest($serviceRequestWorker, $worker);
        }

        return $serviceRequestWorker;
    }

    public function selectFinalWorker(ServiceRequest $serviceRequest, User $customer, int $serviceRequestWorkerId): ServiceRequest
    {
        abort_if($serviceRequest->customer_id !== $customer->id, 404);

        return DB::transaction(function () use ($serviceRequest, $customer, $serviceRequestWorkerId): ServiceRequest {
            // Customers can only choose a final worker while the request is still open.
            if ($serviceRequest->status !== ServiceRequest::STATUS_OPEN) {
                throw ValidationException::withMessages(['booking_request_id' => ['This booking is not waiting for worker selection.']]);
            }

            // Only accepted worker responses are eligible for customer selection.
            $serviceRequestWorker = $serviceRequest->workers()
                ->with('worker')
                ->whereKey($serviceRequestWorkerId)
                ->where('status', ServiceRequestWorker::STATUS_ACCEPTED)
                ->firstOrFail();

            $this->finalizeWorkerSelection($serviceRequest, $serviceRequestWorker, $customer);

            return $serviceRequest->refresh()->load($this->serviceRequestRelations());
        });
    }

    public function updateStatus(Booking $booking, string $status, ?string $reason = null, ?User $actor = null): Booking
    {
        // Partially blocked workers may finish current work, but they cannot start new bookings.
        if ($actor?->hasRole('worker') && $status === Booking::STATUS_IN_PROGRESS && ! $actor->canTakeNewWork()) {
            throw ValidationException::withMessages([
                'account' => ['Your account is temporarily restricted from starting new bookings.'],
            ]);
        }

        // Starting work has extra business rules beyond the normal status flow.
        if ($status === Booking::STATUS_IN_PROGRESS) {
            $this->assertBookingCanStart($booking);
        }

        $oldStatus = $booking->status;

        $booking = $this->workflow
            ->transition($booking, $status, $actor, $reason)
            ->load(['customer.role', 'worker.role', 'selectedWorker.role', 'service', 'activities.actor.role', 'review.customer.role', 'workerReview.worker.role']);

        event(new BookingStatusChanged($booking, $oldStatus, $status, $actor, $reason));

        return $booking;
    }

    public function cancelByCustomer(Booking $booking, User $customer, ?string $reason = null): Booking
    {
        abort_if($booking->customer_id !== $customer->id, 404);

        $this->workflow->assertCustomerCanCancel($booking);

        return $this->updateStatus($booking, Booking::STATUS_CANCELLED, $reason, $customer);
    }

    public function cancelServiceRequest(ServiceRequest $serviceRequest, User $customer, ?string $reason = null): ServiceRequest
    {
        abort_if($serviceRequest->customer_id !== $customer->id, 404);

        // Service requests can be cancelled only before a worker has been selected.
        if ($serviceRequest->status !== ServiceRequest::STATUS_OPEN) {
            throw ValidationException::withMessages(['status' => ['Only open service requests can be cancelled before worker selection.']]);
        }

        $serviceRequest->update([
            'status' => ServiceRequest::STATUS_CANCELLED,
        ]);

        $serviceRequest->workers()
            ->whereIn('status', [
                ServiceRequestWorker::STATUS_PENDING,
                ServiceRequestWorker::STATUS_ACCEPTED,
                ServiceRequestWorker::STATUS_AWAITING_RESCHEDULE,
            ])
            ->update([
                'status' => ServiceRequestWorker::STATUS_NOT_SELECTED,
                'responded_at' => now(),
            ]);

        $this->audit->record('service_request.cancelled', $customer, $serviceRequest, [
            'reason' => $reason,
        ]);

        return $serviceRequest->refresh()->load($this->serviceRequestRelations());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function rescheduleServiceRequest(ServiceRequest $serviceRequest, User $customer, array $data): ServiceRequest
    {
        abort_if($serviceRequest->customer_id !== $customer->id, 404);

        return DB::transaction(function () use ($serviceRequest, $customer, $data): ServiceRequest {
            // Reload inside the transaction so stale customer actions do not overwrite newer changes.
            $lockedServiceRequest = ServiceRequest::query()
                ->with(['customer.role', 'service', 'workers.worker.role'])
                ->lockForUpdate()
                ->findOrFail($serviceRequest->id);

            // Only unresolved requests can move to a new slot.
            if ($lockedServiceRequest->status !== ServiceRequest::STATUS_OPEN || $lockedServiceRequest->booking_id !== null) {
                throw ValidationException::withMessages([
                    'status' => ['Only open booking requests can be rescheduled.'],
                ]);
            }

            // The reschedule action is reserved for requests the worker already pushed back.
            $awaitingRescheduleRequest = $lockedServiceRequest->workers()
                ->with('worker')
                ->where('status', ServiceRequestWorker::STATUS_AWAITING_RESCHEDULE)
                ->first();

            if (! $awaitingRescheduleRequest instanceof ServiceRequestWorker) {
                throw ValidationException::withMessages([
                    'status' => ['This booking request is not waiting for reschedule.'],
                ]);
            }

            $rescheduleData = [
                ...$data,
                'worker_id' => $awaitingRescheduleRequest->worker_id,
                'service_id' => $lockedServiceRequest->service_id,
            ];

            // The new slot must still fit the same worker's published schedule and service rules.
            $this->ensureDirectWorkerCanReceiveRequestedSlot($rescheduleData);

            $worker = $awaitingRescheduleRequest->worker;
            $durationMinutes = $this->durationMinutes($rescheduleData);

            // Prevent reopening the request on a time slot that is still unavailable.
            if (! $worker || ! $this->availability->isWorkerAvailable(
                worker: $worker,
                date: (string) $data['booking_date'],
                startTime: (string) $data['start_time'],
                durationMinutes: $durationMinutes,
                ignoreServiceRequestId: $lockedServiceRequest->id,
            )) {
                throw ValidationException::withMessages([
                    'start_time' => ['This worker is not available for the selected time slot.'],
                ]);
            }

            $lockedServiceRequest->update([
                'requested_date' => $data['booking_date'],
                'start_time' => $data['start_time'],
                'end_time' => $this->bookingEndTime($data),
            ]);

            $awaitingRescheduleRequest->update([
                'status' => ServiceRequestWorker::STATUS_PENDING,
                'response_reason' => null,
                'responded_at' => null,
            ]);

            $this->audit->record('service_request.rescheduled', $customer, $lockedServiceRequest, [
                'worker_id' => $awaitingRescheduleRequest->worker_id,
                'requested_date' => $lockedServiceRequest->requested_date?->toDateString(),
                'start_time' => $lockedServiceRequest->start_time,
                'end_time' => $lockedServiceRequest->end_time,
            ]);

            return $lockedServiceRequest->refresh()->load($this->serviceRequestRelations());
        });
    }

    /**
     * Make sure the worker starts the booking only when the scheduled time has arrived.
     */
    private function assertBookingCanStart(Booking $booking): void
    {
        // Cancelled bookings are already closed, so work cannot start.
        if ($booking->status === Booking::STATUS_CANCELLED) {
            throw ValidationException::withMessages([
                'status' => ['Cancelled bookings cannot be started.'],
            ]);
        }

        // Completed bookings are already finished, so they cannot be started again.
        if ($booking->status === Booking::STATUS_COMPLETED) {
            throw ValidationException::withMessages([
                'status' => ['Completed bookings cannot be started again.'],
            ]);
        }

        // The worker must wait until the scheduled booking date and start time.
        $scheduledStartDateTime = Carbon::parse(
            $booking->booking_date?->toDateString().' '.$booking->start_time
        );
        $currentDateTime = Carbon::now();

        if ($currentDateTime->lt($scheduledStartDateTime)) {
            throw ValidationException::withMessages([
                'status' => ['This booking can only be started on or after the scheduled start time.'],
            ]);
        }
    }

    private function selectSingleAcceptedWorkerRequest(ServiceRequestWorker $serviceRequestWorker, User $actor): ServiceRequestWorker
    {
        return DB::transaction(function () use ($serviceRequestWorker, $actor): ServiceRequestWorker {
            // Lock the accepted response so duplicate worker/customer actions cannot create two bookings.
            $lockedRequestWorker = ServiceRequestWorker::query()
                ->with(['worker', 'serviceRequest.customer'])
                ->lockForUpdate()
                ->findOrFail($serviceRequestWorker->id);

            // Lock the parent request before deciding whether it should auto-confirm.
            $serviceRequest = ServiceRequest::query()
                ->lockForUpdate()
                ->findOrFail($lockedRequestWorker->service_request_id);

            // Auto-selection is allowed only for still-open requests that invited a single accepted worker.
            if (
                $serviceRequest->status === ServiceRequest::STATUS_OPEN
                && $lockedRequestWorker->status === ServiceRequestWorker::STATUS_ACCEPTED
                && $serviceRequest->workers()->count() === 1
            ) {
                $this->finalizeWorkerSelection($serviceRequest, $lockedRequestWorker, $actor);
            }

            return $lockedRequestWorker->refresh()->load(['serviceRequest.customer.role', 'serviceRequest.service', 'serviceRequest.booking', 'worker.role']);
        });
    }

    private function notifyWorkersAboutServiceRequest(ServiceRequest $serviceRequest): void
    {
        $serviceRequest->workers->each(function (ServiceRequestWorker $serviceRequestWorker) use ($serviceRequest): void {
            $serviceRequestWorker->worker?->notify(new ServiceRequestWorkflowNotification(
                serviceRequest: $serviceRequest,
                event: 'service_request_received',
                title: 'New service request',
                message: sprintf(
                    '%s requested %s for %s at %s.',
                    $serviceRequest->customer?->name ?? 'A customer',
                    $serviceRequest->service?->name ?? 'a service',
                    $serviceRequest->requested_date?->format('M j, Y') ?? 'the selected date',
                    $serviceRequest->start_time,
                ),
            ));
        });
    }

    private function finalizeWorkerSelection(ServiceRequest $serviceRequest, ServiceRequestWorker $serviceRequestWorker, User $actor): Booking
    {
        $durationMinutes = $this->durationMinutes([
            'booking_date' => $serviceRequest->requested_date->toDateString(),
            'start_time' => $serviceRequest->start_time,
            'end_time' => $serviceRequest->end_time,
        ]);

        $this->ensureWorkerCanReceiveBooking($serviceRequestWorker->worker, 'booking_request_id');

        // A selected worker must still be eligible and free at the final confirmation moment.
        if (! $serviceRequestWorker->worker || ! $this->availability->isWorkerAvailable(
            worker: $serviceRequestWorker->worker,
            date: $serviceRequest->requested_date->toDateString(),
            startTime: $serviceRequest->start_time,
            durationMinutes: $durationMinutes,
            ignoreServiceRequestId: $serviceRequest->id,
        )) {
            throw ValidationException::withMessages(['booking_request_id' => ['This worker is no longer available for the selected slot.']]);
        }

        $totalAmount = (float) ($serviceRequestWorker->quoted_price ?: $serviceRequest->estimated_amount);
        $commission = $this->lockCommission($totalAmount);

        $booking = Booking::create([
            'service_request_id' => $serviceRequest->id,
            'customer_id' => $serviceRequest->customer_id,
            'worker_id' => $serviceRequestWorker->worker_id,
            'selected_worker_id' => $serviceRequestWorker->worker_id,
            'service_id' => $serviceRequest->service_id,
            'booking_date' => $serviceRequest->requested_date,
            'booking_time' => $serviceRequest->start_time,
            'start_time' => $serviceRequest->start_time,
            'end_time' => $serviceRequest->end_time,
            'address' => $serviceRequest->address,
            'notes' => $serviceRequest->description,
            'issue_description' => $serviceRequest->description,
            'quoted_amount' => $totalAmount,
            'quoted_commission_rate' => $commission['rate'],
            'quoted_platform_commission' => $commission['platform_commission'],
            'quoted_worker_earning' => $commission['worker_earning'],
            'status' => Booking::STATUS_CONFIRMED,
        ]);

        $serviceRequest->update([
            'selected_worker_id' => $serviceRequestWorker->worker_id,
            'booking_id' => $booking->id,
            'status' => ServiceRequest::STATUS_WORKER_SELECTED,
        ]);

        $serviceRequestWorker->update([
            'status' => ServiceRequestWorker::STATUS_SELECTED,
            'responded_at' => now(),
        ]);

        $serviceRequest->workers()
            ->whereKeyNot($serviceRequestWorker->id)
            ->whereIn('status', [ServiceRequestWorker::STATUS_PENDING, ServiceRequestWorker::STATUS_ACCEPTED])
            ->update([
                'status' => ServiceRequestWorker::STATUS_NOT_SELECTED,
                'responded_at' => now(),
            ]);

        $booking = $booking->refresh()->load($this->bookingRelations());
        $this->workflow->record($booking, null, $booking->status, 'worker_selected', $actor);

        // Repeat bookings need a visible timeline note once the new booking actually exists.
        if ($serviceRequest->recreated_from_booking_id) {
            $this->workflow->record(
                booking: $booking,
                fromStatus: null,
                toStatus: $booking->status,
                event: 'booking_recreated',
                actor: $actor,
                note: sprintf('Booking recreated from completed booking #%d', $serviceRequest->recreated_from_booking_id),
            );
        }

        event(new BookingCreated($booking));

        $this->audit->record('service_request.worker_selected', $actor, $serviceRequest, [
            'booking_id' => $booking->id,
            'selected_worker_id' => $serviceRequestWorker->worker_id,
            'quoted_amount' => $booking->quoted_amount,
            'quoted_commission_rate' => $booking->quoted_commission_rate,
            'quoted_platform_commission' => $booking->quoted_platform_commission,
            'quoted_worker_earning' => $booking->quoted_worker_earning,
        ]);

        $this->audit->record('booking.created', $actor, $booking, [
            'service_request_id' => $serviceRequest->id,
            'worker_id' => $booking->worker_id,
            'status' => $booking->status,
        ]);

        $this->moveOverlappingPendingRequestsToAwaitingReschedule($booking, $serviceRequestWorker->id);

        return $booking;
    }

    /**
     * Move conflicting pending requests out of the worker's action queue once the slot is taken.
     */
    private function moveOverlappingPendingRequestsToAwaitingReschedule(Booking $booking, int $selectedRequestId): void
    {
        $conflictMessage = 'Worker is no longer available for selected time slot.';

        // Find open pending requests for the same worker that overlap the newly created booking.
        $overlappingRequests = ServiceRequestWorker::query()
            ->with(['serviceRequest.customer', 'serviceRequest.service'])
            ->where('worker_id', $booking->worker_id)
            ->where('status', ServiceRequestWorker::STATUS_PENDING)
            ->whereKeyNot($selectedRequestId)
            ->whereHas('serviceRequest', function ($query) use ($booking): void {
                $query
                    ->where('status', ServiceRequest::STATUS_OPEN)
                    ->whereDate('requested_date', $booking->booking_date?->toDateString())
                    ->where('start_time', '<', $booking->end_time)
                    ->where('end_time', '>', $booking->start_time);
            })
            ->get();

        foreach ($overlappingRequests as $overlappingRequest) {
            $overlappingRequest->update([
                'status' => ServiceRequestWorker::STATUS_AWAITING_RESCHEDULE,
                'response_reason' => $conflictMessage,
                'responded_at' => now(),
            ]);

            $customer = $overlappingRequest->serviceRequest?->customer;

            if (! $customer) {
                continue;
            }

            $customer->notify(new ServiceRequestWorkflowNotification(
                serviceRequest: $overlappingRequest->serviceRequest->refresh()->loadMissing('service'),
                event: 'service_request_awaiting_reschedule',
                title: 'Reschedule needed',
                message: 'Your selected worker is no longer available for this time slot. Please reschedule or cancel this request.',
            ));
        }
    }

    private function ensureWorkerCanReceiveBooking(?User $worker, string $field): void
    {
        // Bookings should only be assigned to workers who completed all verification steps.
        if (
            ! $worker
            || ! $worker->hasVerifiedEmail()
            || ! $worker->is_verified
            || ! $worker->loadMissing('workerProfile')->workerProfile?->is_verified
        ) {
            throw ValidationException::withMessages([
                $field => ['This worker must complete email and platform verification before receiving bookings.'],
            ]);
        }
    }

    /**
     * Validate direct worker booking requests against the worker's schedule before matching starts.
     *
     * The request layer reports this first, and this service-level check protects other callers.
     *
     * @param  array<string, mixed>  $data
     */
    private function ensureDirectWorkerCanReceiveRequestedSlot(array $data): void
    {
        // Auto-matched bookings do not have one chosen worker yet, so matching filters each worker later.
        if (empty($data['worker_id'])) {
            return;
        }

        $worker = User::find((int) $data['worker_id']);

        // Invalid worker IDs are handled by request validation before this service is normally called.
        if (! $worker instanceof User) {
            return;
        }

        $this->ensureDirectWorkerDurationMatchesConfiguredService($data);

        $availabilityError = $this->workerSchedules->bookingAvailabilityError(
            worker: $worker,
            bookingDate: (string) $data['booking_date'],
            startTime: (string) $data['start_time'],
            endTime: $this->bookingEndTime($data),
        );

        // Direct worker booking should fail with the schedule-specific reason.
        if ($availabilityError !== null) {
            throw ValidationException::withMessages(['start_time' => [$availabilityError]]);
        }
    }

    /**
     * Enforce the configured hourly duration when a customer books one specific worker directly.
     *
     * @param  array<string, mixed>  $data
     */
    private function ensureDirectWorkerDurationMatchesConfiguredService(array $data): void
    {
        // Only direct worker bookings have one exact worker-service configuration to enforce.
        if (empty($data['worker_id']) || empty($data['service_id'])) {
            return;
        }

        // Retrieve the worker's approved active service so the booking follows that exact offer.
        $workerService = WorkerService::query()
            ->where('worker_id', (int) $data['worker_id'])
            ->where('service_id', (int) $data['service_id'])
            ->where('is_active', true)
            ->where('approval_status', WorkerService::StatusApproved)
            ->first();

        if (! $workerService instanceof WorkerService) {
            return;
        }

        // Fixed-price services do not use a worker-defined hourly duration.
        if ($workerService->pricing_type !== WorkerService::PricingHourly) {
            return;
        }

        $requiredDurationMinutes = (int) ($workerService->minimum_hours ?: 1) * 60;
        $requestedDurationMinutes = $this->durationMinutes($data);

        if ($requestedDurationMinutes !== $requiredDurationMinutes) {
            throw ValidationException::withMessages([
                'duration_minutes' => [sprintf(
                    'This worker requires exactly %d %s for this hourly service.',
                    (int) ($workerService->minimum_hours ?: 1),
                    (int) ($workerService->minimum_hours ?: 1) === 1 ? 'hour' : 'hours',
                )],
            ]);
        }
    }

    private function durationMinutes(array $data): int
    {
        // When an end time is supplied, derive the duration from the actual selected time range.
        if (! empty($data['end_time'])) {
            return CarbonImmutable::parse($data['booking_date'].' '.$data['start_time'])
                ->diffInMinutes(CarbonImmutable::parse($data['booking_date'].' '.$data['end_time']));
        }

        // Fallback to the explicit duration when the request did not send an end time.
        if (! empty($data['duration_minutes'])) {
            return (int) $data['duration_minutes'];
        }

        return 60;
    }

    /**
     * Resolve the booking end time from an explicit end time or the requested duration.
     *
     * @param  array<string, mixed>  $data
     */
    private function bookingEndTime(array $data): string
    {
        // Explicit end time wins because it represents the customer-selected booking window.
        if (! empty($data['end_time'])) {
            return (string) $data['end_time'];
        }

        return CarbonImmutable::parse($data['booking_date'].' '.$data['start_time'])
            ->addMinutes($this->durationMinutes($data))
            ->format('H:i');
    }

    private function shouldAutoSelectAcceptedWorker(ServiceRequestWorker $serviceRequestWorker): bool
    {
        // A service request can auto-select only when the accepted worker is its sole candidate.
        return $serviceRequestWorker->serviceRequest()
            ->where('status', ServiceRequest::STATUS_OPEN)
            ->whereHas('workers', fn ($query) => $query->whereKey($serviceRequestWorker->id))
            ->withCount('workers')
            ->first()?->workers_count === 1;
    }

    private function totalAmount(WorkerService $workerService, int $durationMinutes): float
    {
        // Hourly workers are paid for the larger of their minimum hours or the requested duration.
        if ($workerService->pricing_type === WorkerService::PricingHourly) {
            $hours = max($workerService->minimum_hours ?: 1, (int) ceil($durationMinutes / 60));

            return (float) $workerService->price * $hours;
        }

        return (float) $workerService->price;
    }

    /**
     * Lock the platform commission rate and split at booking creation time.
     *
     * Payments must use these quoted values later even when the global commission rate changes.
     *
     * @return array{rate: float, platform_commission: float, worker_earning: float}
     */
    private function lockCommission(float $totalAmount): array
    {
        return $this->commissionSettings->splitForAmount($totalAmount);
    }

    /**
     * @return array<int|string, mixed>
     */
    public function bookingRelations(): array
    {
        return [
            'customer.role',
            'worker.role',
            'selectedWorker.role',
            'service',
            'latestPayment',
            'bookingRequests.worker' => fn ($query) => $query
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
                ->withCount('workerReviews as reviews_count'),
            'activities.actor.role',
            'review.customer.role',
            'workerReview.worker.role',
            'disputes.openedBy.role',
            'disputes.againstUser.role',
            'disputes.statusHistory.actor.role',
        ];
    }

    /**
     * @return array<int|string, mixed>
     */
    public function serviceRequestRelations(): array
    {
        return [
            'customer.role',
            'selectedWorker.role',
            'service',
            'booking.customer.role',
            'booking.worker.role',
            'booking.selectedWorker.role',
            'booking.service',
            'booking.latestPayment',
            'booking.activities.actor.role',
            'booking.review.customer.role',
            'booking.workerReview.worker.role',
            'booking.disputes.openedBy.role',
            'booking.disputes.againstUser.role',
            'booking.disputes.statusHistory.actor.role',
            'recreatedFromBooking',
            'workers.worker' => fn ($query) => $query
                ->with(['role', 'workerProfile'])
                ->withAvg('workerReviews as rating_average', 'rating')
                ->withCount('workerReviews as reviews_count'),
            'workers.workerService.service',
        ];
    }
}
