<?php

namespace App\Models;

use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['customer_id', 'worker_id', 'service_id', 'booking_date', 'booking_time', 'start_time', 'end_time', 'address', 'notes', 'issue_description', 'total_amount', 'status', 'cancelled_by', 'cancelled_reason', 'rejection_reason'])]
class Booking extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_REQUESTED = 'requested';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const ActiveStatuses = [
        self::STATUS_PENDING,
        self::STATUS_REQUESTED,
        self::STATUS_ACCEPTED,
        self::STATUS_IN_PROGRESS,
    ];

    /** @use HasFactory<BookingFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

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
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * @return HasMany<BookingRequest, $this>
     */
    public function bookingRequests(): HasMany
    {
        return $this->hasMany(BookingRequest::class);
    }

    /**
     * @return HasMany<BookingActivity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(BookingActivity::class)->oldest();
    }

    /**
     * @return HasOne<Review, $this>
     */
    public function review(): HasOne
    {
        return $this->hasOne(Review::class)->where('type', Review::TypeCustomerToWorker);
    }

    /**
     * @return HasOne<Review, $this>
     */
    public function workerReview(): HasOne
    {
        return $this->hasOne(Review::class)->where('type', Review::TypeWorkerToCustomer);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'booking_date' => 'date',
            'total_amount' => 'decimal:2',
        ];
    }
}
