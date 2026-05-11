<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\ReviewUnblockRequestRequest;
use App\Http\Resources\UnblockRequestResource;
use App\Models\UnblockRequest;
use App\Services\Admin\UnblockRequestManagementService;
use App\Support\Api\PaginationMeta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UnblockRequestController extends Controller
{
    public function __construct(private readonly UnblockRequestManagementService $unblockRequests) {}

    public function index(Request $request): JsonResponse
    {
        // Admin unblock queues show pending and reviewed appeals with reviewer context.
        $unblockRequests = $this->unblockRequests->paginate($request);

        return response()->json([
            'success' => true,
            'message' => 'Unblock requests retrieved',
            'data' => [
                'unblock_requests' => UnblockRequestResource::collection($unblockRequests),
                'meta' => PaginationMeta::fromPaginator($unblockRequests),
            ],
        ]);
    }

    public function approve(ReviewUnblockRequestRequest $request, UnblockRequest $unblockRequest): JsonResponse
    {
        try {
            // Approval restores the blocked user's access through the account review workflow.
            $unblockRequest = $this->unblockRequests->approve($unblockRequest, $request->user(), $request->string('admin_note')->toString() ?: null);
        } catch (ValidationException $exception) {
            return $this->validationError($exception);
        }

        return response()->json([
            'success' => true,
            'message' => 'User unblocked',
            'data' => [
                'unblock_request' => new UnblockRequestResource($unblockRequest),
            ],
        ]);
    }

    public function reject(ReviewUnblockRequestRequest $request, UnblockRequest $unblockRequest): JsonResponse
    {
        try {
            // Rejection records the admin decision while keeping the account blocked.
            $unblockRequest = $this->unblockRequests->reject($unblockRequest, $request->user(), $request->string('admin_note')->toString() ?: null);
        } catch (ValidationException $exception) {
            return $this->validationError($exception);
        }

        return response()->json([
            'success' => true,
            'message' => 'Unblock request rejected',
            'data' => [
                'unblock_request' => new UnblockRequestResource($unblockRequest),
            ],
        ]);
    }

    private function validationError(ValidationException $exception): JsonResponse
    {
        // Reviewed or invalid unblock requests return validation details without changing response contracts.
        return response()->json([
            'success' => false,
            'message' => 'Unable to review unblock request',
            'errors' => $exception->errors(),
        ], 422);
    }
}
