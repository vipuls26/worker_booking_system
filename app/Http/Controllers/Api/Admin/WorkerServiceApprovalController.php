<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\IndexWorkerServiceApprovalsRequest;
use App\Http\Requests\Api\Admin\RejectWorkerServiceApprovalRequest;
use App\Http\Resources\WorkerServiceResource;
use App\Models\WorkerService;
use App\Services\Admin\WorkerServiceApprovalService;
use App\Support\Api\PaginationMeta;
use Illuminate\Http\JsonResponse;

class WorkerServiceApprovalController extends Controller
{
    public function __construct(private readonly WorkerServiceApprovalService $workerServiceApprovals) {}

    public function index(IndexWorkerServiceApprovalsRequest $request): JsonResponse
    {
        // Admins review pending and processed worker service offerings from this queue.
        $workerServices = $this->workerServiceApprovals->paginate($request);

        return response()->json([
            'success' => true,
            'message' => 'Worker service requests retrieved',
            'data' => [
                'worker_services' => WorkerServiceResource::collection($workerServices),
                'meta' => PaginationMeta::fromPaginator($workerServices),
            ],
        ]);
    }

    public function approve(WorkerService $workerService): JsonResponse
    {
        // Approval publishes the worker's service for customer booking.
        $workerService = $this->workerServiceApprovals->approve($workerService, request()->user());

        return response()->json([
            'success' => true,
            'message' => 'Worker service approved',
            'data' => [
                'worker_service' => new WorkerServiceResource($workerService),
            ],
        ]);
    }

    public function reject(RejectWorkerServiceApprovalRequest $request, WorkerService $workerService): JsonResponse
    {
        // Rejection keeps the offering hidden and returns the admin reason to the worker.
        $workerService = $this->workerServiceApprovals->reject(
            $workerService,
            $request->user(),
            $request->string('rejection_reason')->toString(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Worker service rejected',
            'data' => [
                'worker_service' => new WorkerServiceResource($workerService),
            ],
        ]);
    }
}
