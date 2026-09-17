<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use App\Services\AuditLogService;
use App\Services\RoleManagementService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class RoleController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return RoleResource::collection(Role::query()->with('permissions')->orderBy('role_name')->get());
    }

    public function store(StoreRoleRequest $request, RoleManagementService $roles): RoleResource
    {
        return new RoleResource($roles->create($request->validated()));
    }

    public function show(Role $role): RoleResource
    {
        $role->load('permissions');
        AuditLogService::viewed(Role::class, $role->id);

        return new RoleResource($role);
    }

    public function update(UpdateRoleRequest $request, Role $role, RoleManagementService $roles): RoleResource
    {
        return new RoleResource($roles->update($role, $request->validated()));
    }

    public function destroy(Role $role, RoleManagementService $roles): Response
    {
        $roles->delete($role);

        return response()->noContent();
    }
}
