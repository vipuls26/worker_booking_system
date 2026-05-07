<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Booking\CancelOwnBookingRequest;
use App\Http\Requests\Api\Customer\IndexCustomerBookingsRequest;
use App\Http\Requests\Api\Customer\SelectBookingWorkerRequest;
use App\Http\Requests\Api\Customer\StoreBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Services\Booking\BookingService;
use App\Support\Api\PaginationMeta;
use Illuminate\Http\JsonResponse;

class BookingController extends Controller
{
    public function __construct(private readonly BookingService $bookings) {}

    public function index(IndexCustomerBookingsRequest $request): JsonResponse
    {
        $bookings = $this->bookings->customerBookings(
            $request->user(),
            $request->string('status')->toString() ?: null,
            $request->integer('per_page', 10),
        );

        return response()->json([
            'success' => true,
            'message' => 'Bookings retrieved',
            'data' => [
                'bookings' => BookingResource::collection($bookings),
                'meta' => PaginationMeta::fromPaginator($bookings),
            ],
        ]);
    }

    public function store(StoreBookingRequest $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Booking request sent',
            'data' => [
                'booking' => new BookingResource($this->bookings->create($request->user(), $request->validated())),
            ],
        ], 201);
    }

    public function show(Booking $booking): JsonResponse
    {
        $this->ensureOwnedByCustomer($booking);

        return response()->json([
            'success' => true,
            'message' => 'Booking retrieved',
            'data' => [
                'booking' => new BookingResource($booking->load(['worker.role', 'service', 'bookingRequests.worker.role', 'activities.actor.role', 'review.customer.role', 'workerReview.worker.role'])),
            ],
        ]);
    }

    public function selectWorker(SelectBookingWorkerRequest $request, Booking $booking): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Worker selected successfully',
            'data' => [
                'booking' => new BookingResource(
                    $this->bookings->selectFinalWorker($booking, $request->user(), $request->integer('booking_request_id')),
                ),
            ],
        ]);
    }

    public function cancel(CancelOwnBookingRequest $request, Booking $booking): JsonResponse
    {
        $this->ensureOwnedByCustomer($booking);

        return response()->json([
            'success' => true,
            'message' => 'Booking cancelled',
            'data' => [
                'booking' => new BookingResource(
                    $this->bookings->cancelByCustomer($booking, $request->user(), $request->string('cancelled_reason')->toString() ?: null),
                ),
            ],
        ]);
    }

    private function ensureOwnedByCustomer(Booking $booking): void
    {
        abort_if($booking->customer_id !== request()->user()?->id, 404);
    }
}
