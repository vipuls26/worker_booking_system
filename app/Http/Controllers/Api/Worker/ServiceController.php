<?php

namespace App\Http\Controllers\Api\Worker;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Worker\IndexWorkerServicesRequest;
use App\Http\Requests\Api\Worker\StoreWorkerServiceRequest;
use App\Http\Requests\Api\Worker\UpdateWorkerServiceRequest;
use App\Http\Resources\ServiceResource;
use App\Http\Resources\WorkerServiceResource;
use App\Models\WorkerService;
use App\Services\Worker\WorkerServiceManagementService;
use App\Support\Api\PaginationMeta;
use Illuminate\Http\JsonResponse;

class ServiceController extends Controller
{
    public function __construct(private readonly WorkerServiceManagementService $workerServices) {}

    public function index(IndexWorkerServicesRequest $request): JsonResponse
    {
        // Workers see their own offerings and approval status history.
        $workerServices = $this->workerServices->paginate($request->user(), $request);

        return response()->json([
            'success' => true,
            'message' => 'Worker services retrieved',
            'data' => [
                'worker_services' => WorkerServiceResource::collection($workerServices),
                'meta' => PaginationMeta::fromPaginator($workerServices),
            ],
        ]);
    }

    public function options(): JsonResponse
    {
        // Offering options include only active platform service categories.
        return response()->json([
            'success' => true,
            'message' => 'Service options retrieved',
            'data' => [
                'services' => ServiceResource::collection($this->workerServices->activeServiceOptions()),
            ],
        ]);
    }

    public function store(StoreWorkerServiceRequest $request): JsonResponse
    {
        // New worker offerings enter admin review before becoming bookable.
        $workerService = $this->workerServices->create($request->user(), $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Worker service submitted for approval',
            'data' => [
                'worker_service' => new WorkerServiceResource($workerService),
            ],
        ], 201);
    }

    public function show(WorkerService $workerService): JsonResponse
    {
        // Workers can inspect only offerings they own.
        $this->ensureOwnedByWorker($workerService);

        return response()->json([
            'success' => true,
            'message' => 'Worker service retrieved',
            'data' => [
                'worker_service' => new WorkerServiceResource($workerService->load(['service:id,name,slug,icon,is_active', 'reviewer:id,name'])),
            ],
        ]);
    }

    public function update(UpdateWorkerServiceRequest $request, WorkerService $workerService): JsonResponse
    {
        // Editing an offering returns it to approval review.
        $this->ensureOwnedByWorker($workerService);

        $workerService = $this->workerServices->update($workerService, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Worker service submitted for approval',
            'data' => [
                'worker_service' => new WorkerServiceResource($workerService),
            ],
        ]);
    }

    public function destroy(WorkerService $workerService): JsonResponse
    {
        // Removing an offering prevents future customer matching for that worker service.
        $this->ensureOwnedByWorker($workerService);

        $this->workerServices->delete($workerService);

        return response()->json([
            'success' => true,
            'message' => 'Worker service deleted',
            'data' => [],
        ]);
    }

    private function ensureOwnedByWorker(WorkerService $workerService): void
    {
        // Unknown and cross-worker offering IDs both return 404 to avoid exposing provider data.
        abort_if($workerService->worker_id !== request()->user()?->id, 404);
    }
}
