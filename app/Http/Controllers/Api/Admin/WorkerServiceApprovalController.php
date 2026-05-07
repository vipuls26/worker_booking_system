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
