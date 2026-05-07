<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class WorkerSearchResource extends JsonResource
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
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'rating_average' => (float) ($this->rating_average ?? 0),
            'reviews_count' => (int) ($this->reviews_count ?? 0),
            'min_service_price' => $this->min_service_price,
            'active_services_count' => (int) ($this->active_services_count ?? 0),
            'profile' => new WorkerProfileResource($this->whenLoaded('workerProfile')),
            'services' => WorkerServiceResource::collection($this->whenLoaded('workerServices')),
            'reviews' => ReviewResource::collection($this->whenLoaded('workerReviews')),
        ];
    }
}
