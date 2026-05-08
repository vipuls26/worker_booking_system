<?php

namespace App\Http\Resources;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * @mixin AuditLog
 */
class AuditLogResource extends JsonResource
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
            'actor' => new UserResource($this->whenLoaded('actor')),
            'actor_id' => $this->actor_id,
            'actor_role' => $this->actor_role,
            'action' => $this->action,
            'action_label' => Str::of($this->action)->replace(['.', '_'], ' ')->headline()->toString(),
            'subject_type' => $this->subject_type,
            'subject_label' => $this->subject_type ? class_basename($this->subject_type) : 'System',
            'subject_id' => $this->subject_id,
            'metadata' => $this->metadata ?? [],
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
