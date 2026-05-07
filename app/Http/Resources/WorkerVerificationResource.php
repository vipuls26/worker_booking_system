<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin \App\Models\WorkerVerification
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
            'id_proof_url' => $this->id_proof ? Storage::url($this->id_proof) : null,
            'experience_years' => $this->experience_years,
            'mobile_verified' => $this->mobile_verified,
            'status' => $this->status,
            'rejection_reason' => $this->rejection_reason,
            'verified_by' => $this->verified_by,
            'verifier' => new UserResource($this->whenLoaded('verifier')),
            'verified_at' => $this->verified_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
