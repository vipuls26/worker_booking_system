<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\UpdateCommissionSettingRequest;
use App\Services\CommissionSettingService;
use Illuminate\Http\JsonResponse;

class CommissionSettingController extends Controller
{
    public function __construct(private readonly CommissionSettingService $commissionSettings) {}

    /**
     * Show the global commission setting for admin review before future booking quotes are created.
     */
    public function show(): JsonResponse
    {
        // Admin settings screens need the stored rate without touching any booking quote history.
        $commissionSetting = $this->commissionSettings->current()->load('updater.role');

        return response()->json([
            'success' => true,
            'message' => 'Commission setting retrieved',
            'data' => [
                'commission_setting' => [
                    'id' => $commissionSetting->id,
                    'commission_rate' => $commissionSetting->commission_rate,
                    'updated_by' => $commissionSetting->updater?->name,
                    'updated_at' => $commissionSetting->updated_at?->toISOString(),
                ],
            ],
        ]);
    }

    /**
     * Save a new global commission rate so only future bookings lock the changed percentage.
     */
    public function update(UpdateCommissionSettingRequest $request): JsonResponse
    {
        // New bookings use this rate after save; existing bookings keep their locked quote values.
        $commissionSetting = $this->commissionSettings->updateRate(
            (float) $request->validated('commission_rate'),
            $request->user(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Commission rate updated',
            'data' => [
                'commission_setting' => [
                    'id' => $commissionSetting->id,
                    'commission_rate' => $commissionSetting->commission_rate,
                    'updated_by' => $commissionSetting->updater?->name,
                    'updated_at' => $commissionSetting->updated_at?->toISOString(),
                ],
            ],
        ]);
    }
}
