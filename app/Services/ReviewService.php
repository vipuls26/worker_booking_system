<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Review;
use App\Models\User;
use App\Notifications\ReviewReceivedNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviewService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function createForBooking(Booking $booking, User $customer, array $data): Review
    {
        abort_if($booking->customer_id !== $customer->id, 404);

        // Reviews are allowed only after the service outcome is complete.
        if ($booking->status !== Booking::STATUS_COMPLETED) {
            throw ValidationException::withMessages([
                'booking_id' => ['Only completed bookings can be reviewed.'],
            ]);
        }

        // Customer reviews require a final worker so ratings attach to the correct provider.
        if (! $booking->worker_id) {
            throw ValidationException::withMessages([
                'booking_id' => ['This booking does not have a final worker.'],
            ]);
        }

        // Customers can review a booking once to protect worker ratings from duplicates.
        if ($booking->review()->exists()) {
            throw ValidationException::withMessages([
                'booking_id' => ['This booking has already been reviewed.'],
            ]);
        }

        return DB::transaction(function () use ($booking, $customer, $data): Review {
            $review = $booking->review()->create([
                'customer_id' => $customer->id,
                'worker_id' => $booking->worker_id,
                'type' => Review::TypeCustomerToWorker,
                'rating' => $data['rating'],
                'review' => $data['review'] ?? null,
            ]);

            $review = $review->load(['customer.role', 'worker.role', 'booking.service']);
            $review->worker?->notify(new ReviewReceivedNotification($review));

            return $review;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createForCustomerByWorker(Booking $booking, User $worker, array $data): Review
    {
        abort_if($booking->worker_id !== $worker->id, 404);

        // Workers can review customers only after the job has actually finished.
        if ($booking->status !== Booking::STATUS_COMPLETED) {
            throw ValidationException::withMessages([
                'booking_id' => ['Only completed bookings can be reviewed.'],
            ]);
        }

        // Workers get one customer review per booking to keep reputation data fair.
        if ($booking->workerReview()->exists()) {
            throw ValidationException::withMessages([
                'booking_id' => ['You have already reviewed this customer for this booking.'],
            ]);
        }

        return DB::transaction(function () use ($booking, $worker, $data): Review {
            $review = $booking->workerReview()->create([
                'customer_id' => $booking->customer_id,
                'worker_id' => $worker->id,
                'type' => Review::TypeWorkerToCustomer,
                'rating' => $data['rating'],
                'review' => $data['review'] ?? null,
            ]);

            $review = $review->load(['customer.role', 'worker.role', 'booking.service']);
            $review->customer?->notify(new ReviewReceivedNotification($review));

            return $review;
        });
    }

    public function workerReviews(User $worker, Request $request): LengthAwarePaginator
    {
        abort_unless($worker->hasRole('worker'), 404);

        // Worker profile pages show only customer-to-worker reviews for that worker.
        return Review::query()
            ->with(['customer.role', 'booking.service'])
            ->where('worker_id', $worker->id)
            ->where('type', Review::TypeCustomerToWorker)
            ->when($request->filled('rating'), fn ($query) => $query->where('rating', $request->integer('rating')))
            ->when(
                $request->string('sort')->toString() === 'rating_high',
                fn ($query) => $query->orderByDesc('rating'),
            )
            ->when(
                $request->string('sort')->toString() === 'rating_low',
                fn ($query) => $query->orderBy('rating'),
            )
            ->latest()
            ->paginate($request->integer('per_page', 10));
    }

    /**
     * @return array{average: float, count: int}
     */
    public function summaryForWorker(User $worker): array
    {
        // Rating summaries are calculated from public customer feedback only.
        $summary = Review::query()
            ->where('worker_id', $worker->id)
            ->where('type', Review::TypeCustomerToWorker)
            ->selectRaw('COALESCE(AVG(rating), 0) as average_rating, COUNT(*) as reviews_count')
            ->first();

        return [
            'average' => round((float) ($summary?->average_rating ?? 0), 2),
            'count' => (int) ($summary?->reviews_count ?? 0),
        ];
    }
}
