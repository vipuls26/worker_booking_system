<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

class AuditLogger
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function record(string $action, ?User $actor = null, ?Model $subject = null, array $metadata = []): AuditLog
    {
        return AuditLog::create([
            'actor_id' => $actor?->id,
            'actor_role' => $actor?->role?->slug,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'metadata' => $metadata,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }
}
