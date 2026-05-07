<?php

namespace App\Models;

use Database\Factories\WorkerServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['worker_id', 'service_id', 'pricing_type', 'price', 'minimum_hours', 'description', 'is_active', 'approval_status', 'rejection_reason', 'reviewed_by', 'reviewed_at'])]
class WorkerService extends Model
{
    public const PricingFixed = 'fixed';

    public const PricingHourly = 'hourly';

    public const StatusPending = 'pending';

    public const StatusApproved = 'approved';

    public const StatusRejected = 'rejected';

    /** @use HasFactory<WorkerServiceFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function worker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'worker_id');
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'minimum_hours' => 'integer',
            'is_active' => 'boolean',
            'reviewed_at' => 'datetime',
        ];
    }
}
