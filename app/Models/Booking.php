<?php

namespace App\Models;

use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['service_request_id', 'customer_id', 'worker_id', 'selected_worker_id', 'service_id', 'booking_date', 'booking_time', 'start_time', 'end_time', 'address', 'notes', 'issue_description', 'quoted_amount', 'quoted_commission_rate', 'quoted_platform_commission', 'quoted_worker_earning', 'status', 'payment_status', 'paid_at', 'cancelled_by', 'cancelled_reason', 'rejection_reason'])]
class Booking extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_REQUESTED = 'requested';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const PAYMENT_UNPAID = 'unpaid';

    public const PAYMENT_PAID = 'paid';

    public const PAYMENT_REFUND_REVIEW = 'refund_review';

    public const PAYMENT_REFUNDED = 'refunded';

    public const DefaultCommissionRate = 10.00;

    public const ActiveStatuses = [
        self::STATUS_PENDING,
        self::STATUS_REQUESTED,
        self::STATUS_ACCEPTED,
        self::STATUS_CONFIRMED,
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
     * @return BelongsTo<User, $this>
     */
    public function selectedWorker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'selected_worker_id');
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

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
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return HasOne<Payment, $this>
     */
    public function latestPayment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
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
            'booking_date' => 'date',
            'quoted_amount' => 'decimal:2',
            'quoted_commission_rate' => 'decimal:2',
            'quoted_platform_commission' => 'decimal:2',
            'quoted_worker_earning' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function totalAmount(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->quoted_amount);
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function commissionRate(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->quoted_commission_rate);
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function platformCommission(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->quoted_platform_commission);
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function workerEarning(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->quoted_worker_earning);
    }
}
