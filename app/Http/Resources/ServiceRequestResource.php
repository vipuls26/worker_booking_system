<?php

namespace App\Http\Resources;

use App\Models\ServiceRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ServiceRequest
 */
class ServiceRequestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $booking = $this->relationLoaded('booking') ? $this->booking : null;

        return [
            'id' => $this->id,
            'customer' => new UserResource($this->whenLoaded('customer')),
            'worker' => new UserResource($this->whenLoaded('selectedWorker')),
            'selected_worker' => new UserResource($this->whenLoaded('selectedWorker')),
            'selected_worker_id' => $this->selected_worker_id,
            'booking_id' => $this->booking_id,
            'booking' => new BookingResource($this->whenLoaded('booking')),
            'service' => new ServiceResource($this->whenLoaded('service')),
            'booking_date' => $this->requested_date?->toDateString(),
            'requested_date' => $this->requested_date?->toDateString(),
            'booking_time' => $this->start_time,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'address' => $this->address,
            'notes' => $this->description,
            'issue_description' => $this->description,
            'total_amount' => $booking?->quoted_amount ?? $this->estimated_amount,
            'estimated_amount' => $this->estimated_amount,
            'status' => $this->status,
            'requests' => ServiceRequestWorkerResource::collection($this->whenLoaded('workers')),
            'timeline' => BookingActivityResource::collection($booking && $booking->relationLoaded('activities') ? $booking->activities : collect()),
            'review' => $booking && $booking->relationLoaded('review') ? new ReviewResource($booking->review) : null,
            'worker_review' => $booking && $booking->relationLoaded('workerReview') ? new ReviewResource($booking->workerReview) : null,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
