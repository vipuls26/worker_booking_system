<?php

namespace App\Http\Resources;

use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Review
 */
class ReviewResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'booking_id' => $this->booking_id,
            'customer_id' => $this->customer_id,
            'worker_id' => $this->worker_id,
            'type' => $this->type,
            'rating' => $this->rating,
            'review' => $this->review,
            'customer' => new UserResource($this->whenLoaded('customer')),
            'worker' => new UserResource($this->whenLoaded('worker')),
            'booking' => new BookingResource($this->whenLoaded('booking')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
