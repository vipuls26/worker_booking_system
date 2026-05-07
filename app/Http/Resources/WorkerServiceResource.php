<?php

namespace App\Http\Resources;

use App\Models\WorkerService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WorkerService
 */
class WorkerServiceResource extends JsonResource
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
            'worker_id' => $this->worker_id,
            'worker' => new UserResource($this->whenLoaded('worker')),
            'service_id' => $this->service_id,
            'pricing_type' => $this->pricing_type,
            'price' => $this->price,
            'minimum_hours' => $this->minimum_hours,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'approval_status' => $this->approval_status,
            'rejection_reason' => $this->rejection_reason,
            'reviewer' => new UserResource($this->whenLoaded('reviewer')),
            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'service' => new ServiceResource($this->whenLoaded('service')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
