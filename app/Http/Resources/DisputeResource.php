<?php

namespace App\Http\Resources;

use App\Models\Dispute;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * @mixin Dispute
 */
class DisputeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'booking_id' => $this->booking_id,
            'service_request_id' => $this->service_request_id,
            'category' => $this->category,
            'category_label' => Str::of($this->category)->replace('_', ' ')->headline()->toString(),
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'resolution_note' => $this->resolution_note,
            'resolved_at' => $this->resolved_at?->toISOString(),
            'booking' => new BookingResource($this->whenLoaded('booking')),
            'service_request' => new ServiceRequestResource($this->whenLoaded('serviceRequest')),
            'opened_by' => new UserResource($this->whenLoaded('openedBy')),
            'against_user' => new UserResource($this->whenLoaded('againstUser')),
            'assigned_admin' => new UserResource($this->whenLoaded('assignedAdmin')),
            'resolved_by' => new UserResource($this->whenLoaded('resolvedBy')),
            'timeline' => DisputeStatusHistoryResource::collection($this->whenLoaded('statusHistory')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
