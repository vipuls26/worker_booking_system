<?php

namespace App\Http\Resources;

use App\Models\WorkerVerification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WorkerVerification
 */
class WorkerVerificationResource extends JsonResource
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
            'worker' => new UserResource($this->whenLoaded('user')),
            'id_proof' => $this->id_proof,
            'id_proof_url' => $this->id_proof ? '/storage/'.ltrim($this->id_proof, '/') : null,
            'certificates' => collect($this->certificates ?? [])
                ->map(fn (string $path): array => [
                    'path' => $path,
                    'url' => '/storage/'.ltrim($path, '/'),
                ])
                ->values(),
            'experience_years' => $this->experience_years,
            'mobile_verified' => $this->mobile_verified,
            'status' => $this->status,
            'rejection_reason' => $this->rejection_reason,
            'active_worker_bookings_count' => (int) ($this->user?->active_worker_bookings_count ?? 0),
            'verified_by' => $this->verified_by,
            'verifier' => new UserResource($this->whenLoaded('verifier')),
            'verified_at' => $this->verified_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
