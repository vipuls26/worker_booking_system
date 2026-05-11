<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Booking\CancelOwnBookingRequest;
use App\Http\Requests\Api\Customer\IndexCustomerBookingsRequest;
use App\Http\Requests\Api\Customer\PayBookingRequest;
use App\Http\Requests\Api\Customer\SelectBookingWorkerRequest;
use App\Http\Requests\Api\Customer\StoreBookingRequest;
use App\Http\Resources\PaymentResource;
use App\Http\Resources\ServiceRequestResource;
use App\Models\ServiceRequest;
use App\Services\Booking\BookingService;
use App\Services\Payment\PaymentService;
use App\Support\Api\PaginationMeta;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class BookingController extends Controller
{
    public function __construct(
        private readonly BookingService $bookings,
        private readonly PaymentService $payments,
    ) {}

    public function index(IndexCustomerBookingsRequest $request): JsonResponse
    {
        // Customer booking history is scoped to the signed-in customer by the service layer.
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
        // Customers must pass service request policy checks before workers are contacted.
        Gate::authorize('create', ServiceRequest::class);

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
        // Booking details are visible only to parties authorized by the service request policy.
        Gate::authorize('view', $booking);

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
        // Customer selection turns an accepted worker response into the final booking.
        Gate::authorize('selectWorker', $booking);

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
        // Customers can cancel only open service requests before final worker selection.
        Gate::authorize('cancel', $booking);

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

    public function pay(PayBookingRequest $request, ServiceRequest $booking): JsonResponse
    {
        // Payment requires access to the service request and a finalized booking behind it.
        Gate::authorize('view', $booking);

        $payment = $this->payments->payForServiceRequest($booking->load('booking'), $request->user(), $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Payment successful',
            'data' => [
                'payment' => new PaymentResource($payment),
                'booking' => new ServiceRequestResource($booking->refresh()->load($this->bookings->serviceRequestRelations())),
            ],
        ]);
    }
}
