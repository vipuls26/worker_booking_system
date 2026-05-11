<?php

namespace App\Http\Controllers\Api\Worker;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Worker\UpdateWorkerProfileRequest;
use App\Http\Resources\WorkerProfileResource;
use App\Services\Worker\WorkerProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __construct(private readonly WorkerProfileService $profiles) {}

    public function show(Request $request): JsonResponse
    {
        // Workers always receive a profile record so onboarding screens can be edited immediately.
        $profile = $this->profiles->getOrCreate($request->user());

        return response()->json([
            'success' => true,
            'message' => 'Worker profile retrieved',
            'data' => [
                'profile' => new WorkerProfileResource($profile),
            ],
        ]);
    }

    public function update(UpdateWorkerProfileRequest $request): JsonResponse
    {
        // Profile updates manage marketplace-facing worker details.
        $profile = $this->profiles->update($request->user(), $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Worker profile updated',
            'data' => [
                'profile' => new WorkerProfileResource($profile),
            ],
        ]);
    }
}
