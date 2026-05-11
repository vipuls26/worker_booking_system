<?php

namespace App\Http\Controllers\Api\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Account\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Services\Account\ProfileService;
use Illuminate\Http\JsonResponse;

class ProfileController extends Controller
{
    public function __construct(private readonly ProfileService $profiles) {}

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        // Account profile updates may reset verification when business-critical contact details change.
        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => [
                'user' => new UserResource($this->profiles->update($request->user(), $request->validated())),
            ],
        ]);
    }
}
