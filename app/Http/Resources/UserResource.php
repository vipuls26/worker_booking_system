<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
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
            'role_id' => $this->role_id,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at?->toISOString(),
            'phone' => $this->phone,
            'address' => $this->relationLoaded('customerProfile') ? $this->customerProfile?->address : null,
            'account_status' => $this->account_status,
            'is_blocked' => $this->isFullyBlocked(),
            'is_restricted' => $this->isRestricted(),
            'is_partially_blocked' => $this->isPartiallyBlocked(),
            'is_fully_blocked' => $this->isFullyBlocked(),
            'is_admin_verified' => (bool) $this->is_verified,
            'is_verified' => $this->isPlatformVerified(),
            'verification_status' => $this->verificationStatus(),
            'active_worker_bookings_count' => (int) ($this->active_worker_bookings_count ?? 0),
            'role' => new RoleResource($this->whenLoaded('role')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }

    private function isPlatformVerified(): bool
    {
        if ($this->hasRole('admin')) {
            return true;
        }

        return (bool) $this->is_verified;
    }

    private function verificationStatus(): string
    {
        if ($this->email_verified_at === null) {
            return 'email_pending';
        }

        if (! $this->isPlatformVerified()) {
            return 'platform_pending';
        }

        if ($this->hasRole('worker') && $this->relationLoaded('workerProfile') && ! $this->workerProfile?->is_verified) {
            return 'worker_pending';
        }

        return 'verified';
    }
}
