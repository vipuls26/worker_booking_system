<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Booking
 */
class BookingResource extends JsonResource
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
            'customer' => new UserResource($this->whenLoaded('customer')),
            'worker' => new UserResource($this->whenLoaded('worker')),
            'service' => new ServiceResource($this->whenLoaded('service')),
            'booking_date' => $this->booking_date?->toDateString(),
            'booking_time' => $this->booking_time,
            'address' => $this->address,
            'notes' => $this->notes,
            'total_amount' => $this->total_amount,
            'status' => $this->status,
            'cancelled_by' => $this->cancelled_by,
            'cancelled_by_user' => new UserResource($this->whenLoaded('cancelledBy')),
            'cancelled_reason' => $this->cancelled_reason,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
