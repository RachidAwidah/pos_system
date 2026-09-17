<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class AccessManagementGuard
{
    public function lockAdministratorRole(): void
    {
        Role::query()->where('role_name', 'Admin')->lockForUpdate()->firstOrFail();
    }

    private function actor(): User
    {
        $actor = auth()->user();
        if (! $actor instanceof User) {
            throw new AuthorizationException('يجب تسجيل الدخول لإدارة الحسابات والصلاحيات.');
        }

        return $actor->load('roles.permissions');
    }

    public function ensureUserCanBeManaged(User $user): void
    {
        $actor = $this->actor();
        if ($actor->hasRole('Admin')) {
            return;
        }

        $user->load('roles.permissions');
        if ($user->hasRole('Admin')) {
            throw new AuthorizationException('إدارة حساب مدير النظام متاحة لمدير النظام فقط.');
        }
        $this->ensurePermissionsCanBeGranted($user->permissions()->pluck('id')->all());
    }

    /** @param array<int, string> $roleIds */
    public function ensureRolesCanBeGranted(array $roleIds): void
    {
        if ($this->actor()->hasRole('Admin')) {
            return;
        }

        foreach (Role::query()->with('permissions')->whereKey($roleIds)->get() as $role) {
            if ($role->role_name === 'Admin') {
                throw new AuthorizationException('منح دور مدير النظام متاح لمدير النظام فقط.');
            }
            $this->ensurePermissionsCanBeGranted($role->permissions->pluck('id')->all());
        }
    }

    public function ensureRoleCanBeManaged(Role $role): void
    {
        if ($this->actor()->hasRole('Admin')) {
            return;
        }
        if ($role->is_system || $role->role_name === 'Admin') {
            throw new AuthorizationException('تعديل الأدوار الأساسية متاح لمدير النظام فقط.');
        }
        $this->ensurePermissionsCanBeGranted($role->permissions()->pluck('permissions.id')->all());
    }

    public function ensureRoleNameCanBeUsed(string $name): void
    {
        if (strcasecmp(trim($name), 'Admin') === 0 && ! $this->actor()->hasRole('Admin')) {
            throw new AuthorizationException('اسم دور مدير النظام محجوز.');
        }
    }

    /** @param array<int, string> $permissionIds */
    public function ensurePermissionsCanBeGranted(array $permissionIds): void
    {
        $actor = $this->actor();
        if (! $actor->hasRole('Admin') && array_diff($permissionIds, $actor->permissions()->pluck('id')->all()) !== []) {
            throw new AuthorizationException('لا يمكنك منح أو إدارة صلاحيات غير ممنوحة لحسابك.');
        }
    }
}
