<?php

namespace App\Http\Controllers\Api\Worker;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Worker\IndexWorkerBookingsRequest;
use App\Http\Requests\Api\Worker\RespondBookingRequestRequest;
use App\Http\Requests\Api\Worker\StartBookingRequest;
use App\Http\Requests\Api\Worker\UpdateBookingStatusRequest;
use App\Http\Resources\BookingResource;
use App\Http\Resources\ServiceRequestWorkerResource;
use App\Models\Booking;
use App\Models\ServiceRequestWorker;
use App\Services\Booking\BookingService;
use App\Support\Api\PaginationMeta;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class BookingController extends Controller
{
    public function __construct(private readonly BookingService $bookings) {}

    public function index(IndexWorkerBookingsRequest $request): JsonResponse
    {
        // Worker booking history is scoped to the authenticated provider.
        $bookings = $this->bookings->workerBookings(
            $request->user(),
            $request->string('status')->toString() ?: null,
            $request->string('search')->trim()->toString() ?: null,
            $request->integer('per_page', 10),
        );

        return response()->json([
            'success' => true,
            'message' => 'Worker bookings retrieved',
            'data' => [
                'bookings' => BookingResource::collection($bookings),
                'meta' => PaginationMeta::fromPaginator($bookings),
            ],
        ]);
    }

    public function show(Booking $booking): JsonResponse
    {
        // Workers can view only bookings assigned to them unless policy grants broader access.
        Gate::authorize('view', $booking);

        return response()->json([
            'success' => true,
            'message' => 'Worker booking retrieved',
            'data' => [
                'booking' => new BookingResource($booking->load(['customer.role', 'selectedWorker.role', 'service', 'activities.actor.role', 'review.customer.role', 'workerReview.worker.role'])),
            ],
        ]);
    }

    public function requests(IndexWorkerBookingsRequest $request): JsonResponse
    {
        // Booking requests are invitations the worker can accept or reject.
        $bookingRequests = $this->bookings->workerRequests(
            $request->user(),
            $request->string('status')->toString() ?: null,
            $request->string('search')->trim()->toString() ?: null,
            $request->integer('per_page', 10),
        );

        return response()->json([
            'success' => true,
            'message' => 'Booking requests retrieved',
            'data' => [
                'worker_requests' => ServiceRequestWorkerResource::collection($bookingRequests),
                'meta' => PaginationMeta::fromPaginator($bookingRequests),
            ],
        ]);
    }

    public function showRequest(ServiceRequestWorker $bookingRequest): JsonResponse
    {
        // Request details are hidden from other workers even when they know the ID.
        $this->ensureRequestOwnedByWorker($bookingRequest);

        return response()->json([
            'success' => true,
            'message' => 'Booking request retrieved',
            'data' => [
                'worker_request' => new ServiceRequestWorkerResource(
                    $bookingRequest->load(['serviceRequest.customer.role', 'serviceRequest.service', 'serviceRequest.booking', 'worker.role']),
                ),
            ],
        ]);
    }

    public function respond(RespondBookingRequestRequest $request, ServiceRequestWorker $bookingRequest): JsonResponse
    {
        // Worker responses update the request and may auto-confirm one-worker bookings.
        return response()->json([
            'success' => true,
            'message' => 'Booking request updated',
            'data' => [
                'worker_request' => new ServiceRequestWorkerResource(
                    $this->bookings->respondToRequest(
                        serviceRequestWorker: $bookingRequest,
                        worker: $request->user(),
                        status: $request->string('status')->toString(),
                        reason: $request->string('response_reason')->toString() ?: null,
                    ),
                ),
            ],
        ]);
    }

    public function updateStatus(UpdateBookingStatusRequest $request, Booking $booking): JsonResponse
    {
        // Workers can move only their own bookings through allowed workflow statuses.
        Gate::authorize('updateStatus', $booking);

        $status = $request->string('status')->toString();
        // Cancellation and rejection use different reason fields for clearer customer messaging.
        $reasonField = $status === Booking::STATUS_CANCELLED ? 'cancelled_reason' : 'rejection_reason';
        $reason = $request->string($reasonField)->toString() ?: null;

        return response()->json([
            'success' => true,
            'message' => 'Booking status updated',
            'data' => [
                'booking' => new BookingResource($this->bookings->updateStatus($booking, $status, $reason, $request->user())),
            ],
        ]);
    }

    public function start(StartBookingRequest $request, Booking $booking): JsonResponse
    {
        // Only the assigned worker can start the booking.
        Gate::authorize('updateStatus', $booking);

        // The shared booking service checks schedule time and workflow rules.
        $startedBooking = $this->bookings->updateStatus(
            booking: $booking,
            status: Booking::STATUS_IN_PROGRESS,
            actor: $request->user(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Booking started successfully',
            'data' => [
                'booking' => new BookingResource($startedBooking),
            ],
        ]);
    }

    private function ensureRequestOwnedByWorker(ServiceRequestWorker $bookingRequest): void
    {
        // Unknown and unauthorized request IDs both return 404 to avoid leaking worker invitations.
        abort_if($bookingRequest->worker_id !== request()->user()?->id, 404);
    }
}
