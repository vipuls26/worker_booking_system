<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\CommissionSetting;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

class CommissionSettingService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Return the stored global commission setting, creating the default row when needed.
     *
     * The business needs one durable source of truth so new bookings can lock the current rate.
     */
    public function current(): CommissionSetting
    {
        // The first request after deployment creates the default setting without changing old bookings.
        return CommissionSetting::query()->firstOrCreate(
            ['name' => CommissionSetting::GlobalSettingName],
            ['commission_rate' => Booking::DefaultCommissionRate],
        );
    }

    /**
     * Return the active global commission rate as a percentage.
     *
     * Payment code reads this only for audit context, never to recalculate old bookings.
     */
    public function currentRate(): float
    {
        return (float) $this->current()->commission_rate;
    }

    /**
     * Calculate the commission split for a new booking quote.
     *
     * @return array{rate: float, platform_commission: float, worker_earning: float}
     */
    public function splitForAmount(float $totalAmount): array
    {
        $rate = $this->currentRate();
        $platformCommission = round($totalAmount * ($rate / 100), 2);

        return [
            'rate' => $rate,
            'platform_commission' => $platformCommission,
            'worker_earning' => round($totalAmount - $platformCommission, 2),
        ];
    }

    /**
     * Update the active global commission rate and audit the admin decision.
     *
     * Existing bookings keep their quoted commission columns, so no historical money is changed.
     */
    public function updateRate(float $commissionRate, User $admin): CommissionSetting
    {
        return DB::transaction(function () use ($commissionRate, $admin): CommissionSetting {
            $this->current();

            // Lock the singleton setting so concurrent admin edits produce a clear audit trail.
            $commissionSetting = CommissionSetting::query()
                ->where('name', CommissionSetting::GlobalSettingName)
                ->lockForUpdate()
                ->firstOrFail();

            $previousRate = (float) $commissionSetting->commission_rate;

            $commissionSetting->update([
                'commission_rate' => $commissionRate,
                'updated_by' => $admin->id,
            ]);

            $this->audit->record('admin.commission_rate_updated', $admin, $commissionSetting, [
                'previous_commission_rate' => $previousRate,
                'new_commission_rate' => $commissionRate,
            ]);

            return $commissionSetting->refresh()->load('updater.role');
        });
    }
}
