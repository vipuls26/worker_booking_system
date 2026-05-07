<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\IndexWorkerVerificationsRequest;
use App\Http\Requests\Api\Admin\RejectWorkerVerificationRequest;
use App\Http\Resources\WorkerVerificationResource;
use App\Models\WorkerVerification;
use App\Services\Admin\WorkerVerificationManagementService;
use App\Support\Api\PaginationMeta;
use Illuminate\Http\JsonResponse;

class WorkerVerificationController extends Controller
{
    public function __construct(private readonly WorkerVerificationManagementService $verifications) {}

    public function index(IndexWorkerVerificationsRequest $request): JsonResponse
    {
        $verifications = $this->verifications->paginate($request);

        return response()->json([
            'success' => true,
            'message' => 'Worker verifications retrieved',
            'data' => [
                'verifications' => WorkerVerificationResource::collection($verifications),
                'meta' => PaginationMeta::fromPaginator($verifications),
            ],
        ]);
    }

    public function show(WorkerVerification $workerVerification): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Worker verification retrieved',
            'data' => ['verification' => new WorkerVerificationResource($workerVerification->load(['user.role', 'verifier.role']))],
        ]);
    }

    public function approve(WorkerVerification $workerVerification): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Worker verification approved',
            'data' => ['verification' => new WorkerVerificationResource($this->verifications->approve($workerVerification, request()->user()))],
        ]);
    }

    public function reject(RejectWorkerVerificationRequest $request, WorkerVerification $workerVerification): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Worker verification rejected',
            'data' => [
                'verification' => new WorkerVerificationResource(
                    $this->verifications->reject($workerVerification, $request->user(), $request->string('rejection_reason')->toString()),
                ),
            ],
        ]);
    }
}
