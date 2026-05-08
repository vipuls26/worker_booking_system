<?php

namespace App\Models;

use Database\Factories\ServiceRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['customer_id', 'service_id', 'selected_worker_id', 'booking_id', 'requested_date', 'start_time', 'end_time', 'address', 'description', 'estimated_amount', 'status'])]
class ServiceRequest extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_WORKER_SELECTED = 'worker_selected';

    public const STATUS_CANCELLED = 'cancelled';

    /** @use HasFactory<ServiceRequestFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
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
    public function selectedWorker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'selected_worker_id');
    }

    /**
     * @return BelongsTo<Booking, $this>
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * @return HasMany<ServiceRequestWorker, $this>
     */
    public function workers(): HasMany
    {
        return $this->hasMany(ServiceRequestWorker::class);
    }

    /**
     * @return HasMany<Dispute, $this>
     */
    public function disputes(): HasMany
    {
        return $this->hasMany(Dispute::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'requested_date' => 'date',
            'estimated_amount' => 'decimal:2',
        ];
    }
}
