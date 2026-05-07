<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\CancelBookingRequest;
use App\Http\Requests\Api\Admin\IndexBookingsRequest;
use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Services\Admin\BookingManagementService;
use App\Support\Api\PaginationMeta;
use Illuminate\Http\JsonResponse;

class BookingController extends Controller
{
    public function __construct(private readonly BookingManagementService $bookings) {}

    public function index(IndexBookingsRequest $request): JsonResponse
    {
        $bookings = $this->bookings->paginate($request);

        return response()->json([
            'success' => true,
            'message' => 'Bookings retrieved',
            'data' => [
                'bookings' => BookingResource::collection($bookings),
                'meta' => PaginationMeta::fromPaginator($bookings),
            ],
        ]);
    }

    public function show(Booking $booking): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Booking retrieved',
            'data' => ['booking' => new BookingResource($booking->load(['customer.role', 'worker.role', 'service', 'cancelledBy.role', 'activities.actor.role', 'review.customer.role', 'workerReview.worker.role']))],
        ]);
    }

    public function cancel(CancelBookingRequest $request, Booking $booking): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Booking cancelled',
            'data' => [
                'booking' => new BookingResource(
                    $this->bookings->cancel($booking, $request->user(), $request->string('cancelled_reason')->toString()),
                ),
            ],
        ]);
    }
}
