<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\UserManagementService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class UserController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = User::query()->with('roles.permissions')->orderBy('full_name');

        if ($request->boolean('all')) {
            return UserResource::collection($query->get());
        }

        return UserResource::collection($query->paginate(15));
    }

    public function store(StoreUserRequest $request, UserManagementService $users): UserResource
    {
        return new UserResource($users->create($request->validated()));
    }

    public function show(User $user): UserResource
    {
        $user->load('roles.permissions');
        AuditLogService::viewed(User::class, $user->id);

        return new UserResource($user);
    }

    public function update(UpdateUserRequest $request, User $user, UserManagementService $users): UserResource
    {
        return new UserResource($users->update($user, $request->validated()));
    }

    public function destroy(User $user, UserManagementService $users): Response
    {
        $users->delete($user, request()->user());

        return response()->noContent();
    }
}
