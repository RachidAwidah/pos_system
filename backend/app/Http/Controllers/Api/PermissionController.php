<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PermissionResource;
use App\Models\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class PermissionController extends Controller
{
    public function index(): JsonResponse
    {
        $permissions = Permission::query()
            ->orderBy('permission_key')
            ->get()
            ->groupBy(fn (Permission $permission) => Str::before($permission->permission_key, '.'))
            ->map(fn ($group) => PermissionResource::collection($group)->resolve());

        return response()->json(['data' => $permissions]);
    }
}
