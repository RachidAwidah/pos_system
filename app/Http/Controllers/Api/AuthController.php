<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->validated('email'))->first();

        if (! $user || ! Hash::check($request->validated('password'), $user->password_hash)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken($request->validated('device_name') ?? 'pos-terminal')->plainTextToken;
        $user->load('roles.permissions');
        auth()->guard()->setUser($user);
        AuditLogService::log('login', User::class, $user->id);

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user),
            'token_type' => 'Bearer',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('roles.permissions');

        return response()->json(['user' => new UserResource($user)]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        AuditLogService::log('logout', User::class, $user->id);
        $user->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    public function logoutAll(Request $request): JsonResponse
    {
        $user = $request->user();

        DB::transaction(function () use ($user): void {
            $user->tokens()->delete();
            AuditLogService::log('logout_all', User::class, $user->id);
        });

        return response()->json(['message' => 'Logged out from all devices successfully.']);
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->update([
            'password_hash' => Hash::make($request->validated('new_password')),
            'must_change_password' => false,
        ]);
        $user->tokens()->delete();
        AuditLogService::log('password_change', User::class, $user->id);

        return response()->json(['message' => 'Password changed successfully.']);
    }
}
