<?php

namespace App\Http\Resources;

use App\Models\DisputeStatusHistory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DisputeStatusHistory
 */
class DisputeStatusHistoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'actor' => new UserResource($this->whenLoaded('actor')),
            'from_status' => $this->from_status,
            'to_status' => $this->to_status,
            'note' => $this->note,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
