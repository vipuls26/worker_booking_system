<?php

namespace App\Http\Resources;

use App\Models\WorkerProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WorkerProfile
 */
class WorkerProfileResource extends JsonResource
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
            'user_id' => $this->user_id,
            'profile_photo' => $this->profile_photo,
            'profile_photo_url' => $this->profile_photo ? '/storage/'.ltrim($this->profile_photo, '/') : null,
            'bio' => $this->bio,
            'experience_years' => $this->experience_years,
            'address' => $this->address,
            'city' => $this->city,
            'skills' => $this->skills ?? [],
            'is_verified' => $this->is_verified,
            'user' => new UserResource($this->whenLoaded('user')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
