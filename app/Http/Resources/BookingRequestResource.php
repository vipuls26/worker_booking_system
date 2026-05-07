<?php

namespace App\Http\Resources;

use App\Models\BookingRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BookingRequest
 */
class BookingRequestResource extends JsonResource
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
            'worker_id' => $this->worker_id,
            'status' => $this->status,
            'responded_at' => $this->responded_at?->toISOString(),
            'worker' => new UserResource($this->whenLoaded('worker')),
            'booking' => new BookingResource($this->whenLoaded('booking')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
