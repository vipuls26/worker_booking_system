<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use Illuminate\Http\JsonResponse;

class RoleController extends Controller
{
    public function index(): JsonResponse
    {
        // Registration exposes only customer and worker roles, never admin roles.
        return response()->json([
            'success' => true,
            'message' => 'Roles retrieved',
            'data' => [
                'roles' => RoleResource::collection(
                    Role::query()
                        ->whereIn('slug', ['customer', 'worker'])
                        ->orderBy('id')
                        ->get()
                ),
            ],
        ]);
    }
}
