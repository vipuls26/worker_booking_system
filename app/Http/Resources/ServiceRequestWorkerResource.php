<?php

namespace App\Http\Resources;

use App\Models\ServiceRequestWorker;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ServiceRequestWorker
 */
class ServiceRequestWorkerResource extends JsonResource
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
            'booking_id' => $this->service_request_id,
            'service_request_id' => $this->service_request_id,
            'worker_id' => $this->worker_id,
            'worker_service_id' => $this->worker_service_id,
            'pricing_type' => $this->pricing_type,
            'quoted_price' => $this->quoted_price,
            'minimum_hours' => $this->minimum_hours,
            'status' => $this->status,
            'response_reason' => $this->response_reason,
            'responded_at' => $this->responded_at?->toISOString(),
            'worker' => new WorkerSearchResource($this->whenLoaded('worker')),
            'worker_service' => new WorkerServiceResource($this->whenLoaded('workerService')),
            'booking' => new ServiceRequestResource($this->whenLoaded('serviceRequest')),
            'service_request' => new ServiceRequestResource($this->whenLoaded('serviceRequest')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
