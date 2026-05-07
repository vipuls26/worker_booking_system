<?php

namespace App\Http\Controllers\Api\Worker;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Worker\SubmitWorkerVerificationRequest;
use App\Http\Resources\WorkerVerificationResource;
use App\Services\Worker\WorkerVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VerificationController extends Controller
{
    public function __construct(private readonly WorkerVerificationService $verifications) {}

    public function show(Request $request): JsonResponse
    {
        $verification = $this->verifications->get($request->user());

        return response()->json([
            'success' => true,
            'message' => 'Worker verification retrieved',
            'data' => [
                'verification' => $verification ? new WorkerVerificationResource($verification) : null,
            ],
        ]);
    }

    public function store(SubmitWorkerVerificationRequest $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Worker verification submitted for review',
            'data' => [
                'verification' => new WorkerVerificationResource($this->verifications->submit($request->user(), $request->validated())),
            ],
        ]);
    }
}
