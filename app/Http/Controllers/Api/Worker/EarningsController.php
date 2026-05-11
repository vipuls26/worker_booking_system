<?php

namespace App\Http\Controllers\Api\Worker;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Http\Resources\WorkerPayoutResource;
use App\Services\Payment\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EarningsController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    public function __invoke(Request $request): JsonResponse
    {
        // Worker earnings combine settled payments, pending payout, and recent payout history.
        $summary = $this->payments->workerSummary($request->user());

        return response()->json([
            'success' => true,
            'message' => 'Earnings summary retrieved',
            'data' => [
                ...$summary,
                'recent_payments' => PaymentResource::collection($summary['recent_payments']),
                'recent_payouts' => WorkerPayoutResource::collection($summary['recent_payouts']),
            ],
        ]);
    }
}
