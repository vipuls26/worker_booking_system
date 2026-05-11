<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Customer\WorkerSearchRequest;
use App\Http\Resources\ServiceResource;
use App\Http\Resources\WorkerSearchResource;
use App\Models\Service;
use App\Models\User;
use App\Services\Customer\WorkerSearchService;
use App\Support\Api\PaginationMeta;
use Illuminate\Http\JsonResponse;

class WorkerSearchController extends Controller
{
    public function __construct(private readonly WorkerSearchService $workers) {}

    public function index(WorkerSearchRequest $request): JsonResponse
    {
        // Customer worker search returns only marketplace-ready workers.
        $workers = $this->workers->paginate($request);

        return response()->json([
            'success' => true,
            'message' => 'Workers retrieved',
            'data' => [
                'workers' => WorkerSearchResource::collection($workers),
                'meta' => PaginationMeta::fromPaginator($workers),
            ],
        ]);
    }

    public function show(User $worker, WorkerSearchRequest $request): JsonResponse
    {
        // Worker detail pages include optional availability for the customer's selected date.
        $worker = $this->workers->findWorker($worker);

        return response()->json([
            'success' => true,
            'message' => 'Worker retrieved',
            'data' => [
                'worker' => new WorkerSearchResource($worker),
                'availability' => $this->workers
                    ->availabilityForDetails(
                        worker: $worker,
                        date: $request->validated('available_date'),
                        slotMinutes: $request->integer('slot_minutes', 60),
                        serviceId: $request->integer('service_id') ?: null,
                    )
                    ->values(),
            ],
        ]);
    }

    public function options(): JsonResponse
    {
        // Search options expose only active service categories customers can book.
        return response()->json([
            'success' => true,
            'message' => 'Worker search options retrieved',
            'data' => [
                'services' => ServiceResource::collection(
                    Service::query()
                        ->select(['id', 'name', 'slug', 'icon'])
                        ->where('is_active', true)
                        ->orderBy('name')
                        ->get()
                ),
            ],
        ]);
    }
}
