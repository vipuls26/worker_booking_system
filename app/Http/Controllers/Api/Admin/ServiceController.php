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
        $service = $this->service->update($service, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Service updated',
            'data' => ['service' => new ServiceResource($service)],
        ]);
    }

    public function destroy(Service $service): JsonResponse
    {
        $this->service->delete($service);

        return response()->json([
            'success' => true,
            'message' => 'Service deleted',
            'data' => [],
        ]);
    }

    public function toggleStatus(Service $service): JsonResponse
    {
        $service = $this->service->toggleStatus($service);

        return response()->json([
            'success' => true,
            'message' => $service->is_active ? 'Service activated' : 'Service deactivated',
            'data' => ['service' => new ServiceResource($service)],
        ]);
    }
}
