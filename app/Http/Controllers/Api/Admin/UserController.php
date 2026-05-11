<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\IndexUsersRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Admin\UserManagementService;
use App\Support\Api\PaginationMeta;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function __construct(private readonly UserManagementService $users) {}

    public function index(IndexUsersRequest $request): JsonResponse
    {
        // Admin user lists exclude protected admin accounts inside the service layer.
        $users = $this->users->paginate($request);

        return response()->json([
            'success' => true,
            'message' => 'Users retrieved',
            'data' => [
                'users' => UserResource::collection($users),
                'meta' => PaginationMeta::fromPaginator($users),
            ],
        ]);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'User retrieved',
            'data' => ['user' => new UserResource($user->load(['role', 'customerProfile']))],
        ]);
    }

    public function block(User $user): JsonResponse
    {
        try {
            // Blocking removes a user's platform access and may reset worker booking eligibility.
            $user = $this->users->block($user);
        } catch (ValidationException $exception) {
            return $this->validationError('Unable to block user', $exception);
        }

        return response()->json([
            'success' => true,
            'message' => 'User blocked',
            'data' => ['user' => new UserResource($user)],
        ]);
    }

    public function unblock(User $user): JsonResponse
    {
        try {
            // Manual unblocking restores account access outside the appeal workflow.
            $user = $this->users->unblock($user);
        } catch (ValidationException $exception) {
            return $this->validationError('Unable to unblock user', $exception);
        }

        return response()->json([
            'success' => true,
            'message' => 'User unblocked',
            'data' => ['user' => new UserResource($user)],
        ]);
    }

    public function verify(User $user): JsonResponse
    {
        try {
            // Admin verification enables approved users to access protected platform features.
            $user = $this->users->verify($user);
        } catch (ValidationException $exception) {
            return $this->validationError('Unable to verify user', $exception);
        }

        return response()->json([
            'success' => true,
            'message' => 'User verified',
            'data' => ['user' => new UserResource($user)],
        ]);
    }

    public function destroy(User $user): JsonResponse
    {
        try {
            // User deletion is guarded so admins cannot delete protected operator accounts.
            $this->users->delete($user, request()->user());
        } catch (ValidationException $exception) {
            return $this->validationError('Unable to delete user', $exception);
        }

        return response()->json([
            'success' => true,
            'message' => 'User deleted',
            'data' => [],
        ]);
    }

    private function validationError(string $message, ValidationException $exception): JsonResponse
    {
        // User management business rule failures return field-level errors for the admin UI.
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $exception->errors(),
        ], 422);
    }
}
