<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function admin(Request $request): JsonResponse
    {
        return $this->dashboardResponse($request, 'Admin dashboard');
    }

    public function worker(Request $request): JsonResponse
    {
        return $this->dashboardResponse($request, 'Worker dashboard');
    }

    public function customer(Request $request): JsonResponse
    {
        return $this->dashboardResponse($request, 'Customer dashboard');
    }

    private function dashboardResponse(Request $request, string $message): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'user' => [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'role' => $request->user()->role?->slug,
                ],
            ],
        ]);
    }
}
