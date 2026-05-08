<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Services\Payment\PaymentService;
use Illuminate\Http\JsonResponse;

class RevenueController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    public function __invoke(): JsonResponse
    {
        $summary = $this->payments->adminSummary();

        return response()->json([
            'success' => true,
            'message' => 'Revenue summary retrieved',
            'data' => [
                ...$summary,
                'recent_payments' => PaymentResource::collection($summary['recent_payments']),
            ],
        ]);
    }
}
