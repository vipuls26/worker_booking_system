<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreDisputeRequest;
use App\Http\Resources\DisputeResource;
use App\Models\Dispute;
use App\Services\Dispute\DisputeService;
use App\Support\Api\PaginationMeta;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class DisputeController extends Controller
{
    public function __construct(private readonly DisputeService $disputes) {}

    public function index(): JsonResponse
    {
        // Dispute lists are available only to users allowed to participate in disputes.
        Gate::authorize('viewAny', Dispute::class);

        // User dispute history is limited to disputes where the user is involved.
        $disputes = $this->disputes->paginateForUser(request()->user());

        return response()->json([
            'success' => true,
            'message' => 'Disputes retrieved',
            'data' => [
                'disputes' => DisputeResource::collection($disputes),
                'meta' => PaginationMeta::fromPaginator($disputes),
            ],
        ]);
    }

    public function store(StoreDisputeRequest $request): JsonResponse
    {
        // Opening a dispute delegates booking-party checks to the dispute service and policy.
        $dispute = $this->disputes->create($request->user(), $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Dispute opened successfully',
            'data' => [
                'dispute' => new DisputeResource($dispute),
            ],
        ], 201);
    }

    public function show(Dispute $dispute): JsonResponse
    {
        // Dispute details are visible only to involved parties or admins.
        Gate::authorize('view', $dispute);

        return response()->json([
            'success' => true,
            'message' => 'Dispute retrieved',
            'data' => [
                'dispute' => new DisputeResource($dispute->load($this->disputes->relations())),
            ],
        ]);
    }
}
