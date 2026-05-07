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
        return response()->json([
            'success' => false,
            'message' => 'Unable to review unblock request',
            'errors' => $exception->errors(),
        ], 422);
    }
}
