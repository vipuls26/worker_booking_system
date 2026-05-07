<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\DashboardAnalyticsService;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardAnalyticsService $dashboard) {}

    public function __invoke(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Admin dashboard retrieved',
            'data' => $this->dashboard->summary(),
        ]);
    }
}
