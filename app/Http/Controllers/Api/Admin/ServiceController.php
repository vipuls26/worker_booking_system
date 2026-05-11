<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\IndexServicesRequest;
use App\Http\Requests\Api\Admin\StoreServiceRequest;
use App\Http\Requests\Api\Admin\UpdateServiceRequest;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use App\Services\Admin\ServiceManagementService;
use App\Support\Api\PaginationMeta;
use Illuminate\Http\JsonResponse;

class ServiceController extends Controller
{
    public function __construct(private readonly ServiceManagementService $service) {}

    public function index(IndexServicesRequest $request): JsonResponse
    {
        // Admin service lists support managing active and inactive marketplace categories.
        $services = $this->service->paginate($request);

        return response()->json([
            'success' => true,
            'message' => 'Services retrieved',
            'data' => [
                'services' => ServiceResource::collection($services),
                'meta' => PaginationMeta::fromPaginator($services),
            ],
        ]);
    }

    public function store(StoreServiceRequest $request): JsonResponse
    {
        // Newly created service categories become available according to the submitted active flag.
        $service = $this->service->create($request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Service created',
            'data' => ['service' => new ServiceResource($service)],
        ], 201);
    }

    public function show(Service $service): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Service retrieved',
            'data' => ['service' => new ServiceResource($service->load('creator'))],
        ]);
    }

    public function update(UpdateServiceRequest $request, Service $service): JsonResponse
    {
        // Updating a service may regenerate its slug when the business-facing name changes.
        $service = $this->service->update($service, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Service updated',
            'data' => ['service' => new ServiceResource($service)],
        ]);
    }

    public function destroy(Service $service): JsonResponse
    {
        // Deleting a service removes it from marketplace management without returning stale payload data.
        $this->service->delete($service);

        return response()->json([
            'success' => true,
            'message' => 'Service deleted',
            'data' => [],
        ]);
    }

    public function toggleStatus(Service $service): JsonResponse
    {
        // Service status toggles let admins quickly pause or resume customer booking categories.
        $service = $this->service->toggleStatus($service);

        return response()->json([
            'success' => true,
            'message' => $service->is_active ? 'Service activated' : 'Service deactivated',
            'data' => ['service' => new ServiceResource($service)],
        ]);
    }
}
