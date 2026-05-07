<?php

namespace App\Http\Controllers\Api\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Account\StoreUnblockRequestRequest;
use App\Http\Resources\UnblockRequestResource;
use App\Services\Account\UnblockRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UnblockRequestController extends Controller
{
    public function __construct(private readonly UnblockRequestService $unblockRequests) {}

    public function show(Request $request): JsonResponse
    {
        $unblockRequest = $this->unblockRequests->latestFor($request->user());

        return response()->json([
            'success' => true,
            'message' => 'Unblock request retrieved',
            'data' => [
                'unblock_request' => $unblockRequest ? new UnblockRequestResource($unblockRequest) : null,
            ],
        ]);
    }

    public function store(StoreUnblockRequestRequest $request): JsonResponse
    {
        try {
            $unblockRequest = $this->unblockRequests->submit($request->user(), $request->validated());
        } catch (ValidationException $exception) {
            return response()->json([
                'success' => false,
                'message' => 'Unable to submit unblock request',
                'errors' => $exception->errors(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Unblock request submitted',
            'data' => [
                'unblock_request' => new UnblockRequestResource($unblockRequest),
            ],
        ], 201);
    }
}
