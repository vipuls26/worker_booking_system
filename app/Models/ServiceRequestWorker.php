<?php

namespace App\Models;

use Database\Factories\ServiceRequestWorkerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['service_request_id', 'worker_id', 'worker_service_id', 'pricing_type', 'quoted_price', 'minimum_hours', 'status', 'response_reason', 'responded_at'])]
class ServiceRequestWorker extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_SELECTED = 'selected';

    public const STATUS_NOT_SELECTED = 'not_selected';

    /** @use HasFactory<ServiceRequestWorkerFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<ServiceRequest, $this>
     */
    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function worker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'worker_id');
    }

    /**
     * @return BelongsTo<WorkerService, $this>
     */
    public function workerService(): BelongsTo
    {
        return $this->belongsTo(WorkerService::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quoted_price' => 'decimal:2',
            'responded_at' => 'datetime',
        ];
    }
}
