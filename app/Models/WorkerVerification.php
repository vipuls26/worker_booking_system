<?php

namespace App\Models;

use Database\Factories\WorkerVerificationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'id_proof', 'certificates', 'experience_years', 'mobile_verified', 'status', 'rejection_reason', 'verified_by', 'verified_at'])]
class WorkerVerification extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_RESUBMISSION_REQUESTED = 'resubmission_requested';

    /** @use HasFactory<WorkerVerificationFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'experience_years' => 'integer',
            'mobile_verified' => 'boolean',
            'certificates' => 'array',
            'verified_at' => 'datetime',
        ];
    }
}
