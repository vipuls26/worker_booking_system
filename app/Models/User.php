<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['role_id', 'name', 'email', 'phone', 'password', 'is_blocked', 'is_verified'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function hasRole(string $role): bool
    {
        return $this->role?->slug === $role;
    }

    /**
     * @return HasOne<WorkerVerification, $this>
     */
    public function workerVerification(): HasOne
    {
        return $this->hasOne(WorkerVerification::class);
    }

    /**
     * @return HasMany<UnblockRequest, $this>
     */
    public function unblockRequests(): HasMany
    {
        return $this->hasMany(UnblockRequest::class);
    }

    /**
     * @return HasOne<WorkerProfile, $this>
     */
    public function workerProfile(): HasOne
    {
        return $this->hasOne(WorkerProfile::class);
    }

    /**
     * @return HasOne<CustomerProfile, $this>
     */
    public function customerProfile(): HasOne
    {
        return $this->hasOne(CustomerProfile::class);
    }

    /**
     * @return HasMany<Booking, $this>
     */
    public function customerBookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'customer_id');
    }

    /**
     * @return HasMany<Booking, $this>
     */
    public function workerBookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'worker_id');
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function customerPayments(): HasMany
    {
        return $this->hasMany(Payment::class, 'customer_id');
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function workerPayments(): HasMany
    {
        return $this->hasMany(Payment::class, 'worker_id');
    }

    /**
     * @return HasMany<WorkerPayout, $this>
     */
    public function workerPayouts(): HasMany
    {
        return $this->hasMany(WorkerPayout::class, 'worker_id');
    }

    /**
     * @return HasMany<Dispute, $this>
     */
    public function openedDisputes(): HasMany
    {
        return $this->hasMany(Dispute::class, 'opened_by');
    }

    /**
     * @return HasMany<Dispute, $this>
     */
    public function assignedDisputes(): HasMany
    {
        return $this->hasMany(Dispute::class, 'assigned_admin_id');
    }

    /**
     * @return HasMany<WorkerService, $this>
     */
    public function workerServices(): HasMany
    {
        return $this->hasMany(WorkerService::class, 'worker_id');
    }

    /**
     * @return HasMany<WorkerSchedule, $this>
     */
    public function workerSchedules(): HasMany
    {
        return $this->hasMany(WorkerSchedule::class, 'worker_id');
    }

    /**
     * @return HasMany<Review, $this>
     */
    public function reviewsWritten(): HasMany
    {
        return $this->hasMany(Review::class, 'customer_id');
    }

    /**
     * @return HasMany<Review, $this>
     */
    public function workerReviews(): HasMany
    {
        return $this->hasMany(Review::class, 'worker_id')->where('type', Review::TypeCustomerToWorker);
    }

    /**
     * @return BelongsToMany<Service, $this>
     */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'worker_services', 'worker_id', 'service_id')
            ->as('pricing')
            ->withPivot(['id', 'pricing_type', 'price', 'minimum_hours', 'description', 'is_active', 'approval_status'])
            ->withTimestamps();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_blocked' => 'boolean',
            'is_verified' => 'boolean',
        ];
    }
}
