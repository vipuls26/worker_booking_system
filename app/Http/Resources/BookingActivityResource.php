<?php

namespace App\Http\Resources;

use App\Models\BookingActivity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BookingActivity
 */
class BookingActivityResource extends JsonResource
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
            'from_status' => $this->from_status,
            'to_status' => $this->to_status,
            'event' => $this->event,
            'note' => $this->note,
            'actor' => new UserResource($this->whenLoaded('actor')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
