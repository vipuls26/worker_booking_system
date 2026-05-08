<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ReviewIndexRequest;
use App\Http\Requests\Api\StoreReviewRequest;
use App\Http\Resources\ReviewResource;
use App\Models\Booking;
use App\Models\Review;
use App\Models\User;
use App\Services\ReviewService;
use App\Support\Api\PaginationMeta;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ReviewController extends Controller
{
    public function __construct(private readonly ReviewService $reviews) {}

    public function store(StoreReviewRequest $request, Booking $booking): JsonResponse
    {
        Gate::authorize('createForWorker', [Review::class, $booking]);

        return response()->json([
            'success' => true,
            'message' => 'Review submitted successfully',
            'data' => [
                'review' => new ReviewResource($this->reviews->createForBooking($booking, $request->user(), $request->validated())),
            ],
        ], 201);
    }

    public function storeForCustomer(StoreReviewRequest $request, Booking $booking): JsonResponse
    {
        Gate::authorize('createForCustomer', [Review::class, $booking]);

        return response()->json([
            'success' => true,
            'message' => 'Customer feedback submitted successfully',
            'data' => [
                'review' => new ReviewResource($this->reviews->createForCustomerByWorker($booking, $request->user(), $request->validated())),
            ],
        ], 201);
    }

    public function workerReviews(ReviewIndexRequest $request, User $worker): JsonResponse
    {
        $reviews = $this->reviews->workerReviews($worker, $request);

        return response()->json([
            'success' => true,
            'message' => 'Reviews retrieved',
            'data' => [
                'summary' => $this->reviews->summaryForWorker($worker),
                'reviews' => ReviewResource::collection($reviews),
                'meta' => PaginationMeta::fromPaginator($reviews),
            ],
        ]);
    }

    public function myWorkerReviews(ReviewIndexRequest $request): JsonResponse
    {
        $reviews = $this->reviews->workerReviews($request->user(), $request);

        return response()->json([
            'success' => true,
            'message' => 'Worker reviews retrieved',
            'data' => [
                'summary' => $this->reviews->summaryForWorker($request->user()),
                'reviews' => ReviewResource::collection($reviews),
                'meta' => PaginationMeta::fromPaginator($reviews),
            ],
        ]);
    }
}
