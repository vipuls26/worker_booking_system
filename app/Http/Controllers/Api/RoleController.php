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
        return response()->json([
            'success' => true,
            'message' => 'Roles retrieved',
            'data' => [
                'roles' => RoleResource::collection(Role::query()->orderBy('id')->get()),
            ],
        ]);
    }
}
