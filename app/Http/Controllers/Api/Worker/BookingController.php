<?php

namespace App\Http\Controllers\Api\Worker;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Worker\IndexWorkerBookingsRequest;
use App\Http\Requests\Api\Worker\RespondBookingRequestRequest;
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
        $bookings = $this->bookings->workerBookings(
            $request->user(),
            $request->string('status')->toString() ?: null,
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
        $bookingRequests = $this->bookings->workerRequests(
            $request->user(),
            $request->string('status')->toString() ?: null,
            $request->integer('per_page', 10),
        );

        return response()->json([
            'success' => true,
            'message' => 'Booking requests retrieved',
            'data' => [
                'booking_requests' => ServiceRequestWorkerResource::collection($bookingRequests),
                'meta' => PaginationMeta::fromPaginator($bookingRequests),
            ],
        ]);
    }

    public function showRequest(ServiceRequestWorker $bookingRequest): JsonResponse
    {
        $this->ensureRequestOwnedByWorker($bookingRequest);

        return response()->json([
            'success' => true,
            'message' => 'Booking request retrieved',
            'data' => [
                'booking_request' => new ServiceRequestWorkerResource(
                    $bookingRequest->load(['serviceRequest.customer.role', 'serviceRequest.service', 'serviceRequest.booking', 'worker.role']),
                ),
            ],
        ]);
    }

    public function respond(RespondBookingRequestRequest $request, ServiceRequestWorker $bookingRequest): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Booking request updated',
            'data' => [
                'booking_request' => new ServiceRequestWorkerResource(
                    $this->bookings->respondToRequest($bookingRequest, $request->user(), $request->string('status')->toString()),
                ),
            ],
        ]);
    }

    public function updateStatus(UpdateBookingStatusRequest $request, Booking $booking): JsonResponse
    {
        Gate::authorize('updateStatus', $booking);

        $reason = $request->string('rejection_reason')->toString() ?: null;

        return response()->json([
            'success' => true,
            'message' => 'Booking status updated',
            'data' => [
                'booking' => new BookingResource($this->bookings->updateStatus($booking, $request->string('status')->toString(), $reason, $request->user())),
            ],
        ]);
    }

    private function ensureRequestOwnedByWorker(ServiceRequestWorker $bookingRequest): void
    {
        abort_if($bookingRequest->worker_id !== request()->user()?->id, 404);
    }
}
