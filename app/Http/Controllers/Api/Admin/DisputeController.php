<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\IndexDisputesRequest;
use App\Http\Requests\Api\Admin\ResolveDisputeRequest;
use App\Http\Resources\DisputeResource;
use App\Models\Dispute;
use App\Services\Dispute\DisputeService;
use App\Support\Api\PaginationMeta;
use Illuminate\Http\JsonResponse;

class DisputeController extends Controller
{
    public function __construct(private readonly DisputeService $disputes) {}

    public function index(IndexDisputesRequest $request): JsonResponse
    {
        // Admin dispute lists are filtered for triage and include pagination metadata for dashboards.
        $disputes = $this->disputes->paginateForAdmin($request->validated(), $request->integer('per_page', 15));

        return response()->json([
            'success' => true,
            'message' => 'Disputes retrieved',
            'data' => [
                'disputes' => DisputeResource::collection($disputes),
                'meta' => PaginationMeta::fromPaginator($disputes),
            ],
        ]);
    }

    public function show(Dispute $dispute): JsonResponse
    {
        // Admin dispute details load all resolution context for decision making.
        return response()->json([
            'success' => true,
            'message' => 'Dispute retrieved',
            'data' => [
                'dispute' => new DisputeResource($dispute->load($this->disputes->relations())),
            ],
        ]);
    }

    public function update(ResolveDisputeRequest $request, Dispute $dispute): JsonResponse
    {
        // Dispute updates capture the admin decision and optional resolution note.
        return response()->json([
            'success' => true,
            'message' => 'Dispute updated',
            'data' => [
                'dispute' => new DisputeResource(
                    $this->disputes->updateStatus(
                        dispute: $dispute,
                        admin: $request->user(),
                        status: $request->string('status')->toString(),
                        resolutionNote: $request->string('resolution_note')->toString() ?: null,
                    ),
                ),
            ],
        ]);
    }
}
