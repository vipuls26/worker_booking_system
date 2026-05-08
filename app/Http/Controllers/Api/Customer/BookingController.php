<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Booking\CancelOwnBookingRequest;
use App\Http\Requests\Api\Customer\IndexCustomerBookingsRequest;
use App\Http\Requests\Api\Customer\SelectBookingWorkerRequest;
use App\Http\Requests\Api\Customer\StoreBookingRequest;
use App\Http\Resources\ServiceRequestResource;
use App\Models\ServiceRequest;
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
                'bookings' => ServiceRequestResource::collection($bookings),
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
                'booking' => new ServiceRequestResource($this->bookings->create($request->user(), $request->validated())),
            ],
        ], 201);
    }

    public function show(ServiceRequest $booking): JsonResponse
    {
        $this->ensureOwnedByCustomer($booking);

        return response()->json([
            'success' => true,
            'message' => 'Booking retrieved',
            'data' => [
                'booking' => new ServiceRequestResource($booking->load($this->bookings->serviceRequestRelations())),
            ],
        ]);
    }

    public function selectWorker(SelectBookingWorkerRequest $request, ServiceRequest $booking): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Worker selected successfully',
            'data' => [
                'booking' => new ServiceRequestResource(
                    $this->bookings->selectFinalWorker($booking, $request->user(), $request->integer('booking_request_id')),
                ),
            ],
        ]);
    }

    public function cancel(CancelOwnBookingRequest $request, ServiceRequest $booking): JsonResponse
    {
        $this->ensureOwnedByCustomer($booking);

        return response()->json([
            'success' => true,
            'message' => 'Booking cancelled',
            'data' => [
                'booking' => new ServiceRequestResource(
                    $this->bookings->cancelServiceRequest($booking, $request->user(), $request->string('cancelled_reason')->toString() ?: null),
                ),
            ],
        ]);
    }

    private function ensureOwnedByCustomer(ServiceRequest $booking): void
    {
        abort_if($booking->customer_id !== request()->user()?->id, 404);
    }
}
