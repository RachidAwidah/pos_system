<?php

namespace App;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

trait HasRolesAndPermissions
{
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_role');
    }

    public function permissions(): Collection
    {
        return $this->roles()
            ->with('permissions')
            ->get()
            ->flatMap->permissions
            ->unique('id')
            ->values();
    }

    public function hasRole(Role|string $role): bool
    {
        if ($role instanceof Role) {
            return $this->roles()->whereKey($role->getKey())->exists();
        }

        return $this->roles()
            ->where(fn ($query) => $query->where('roles.id', $role)->orWhere('role_name', $role))
            ->exists();
    }

    public function hasPermission(Permission|string $permission): bool
    {
        if ($this->hasRole('Admin')) {
            return true;
        }

        if ($permission instanceof Permission) {
            return $this->roles()
                ->whereHas('permissions', fn ($query) => $query->whereKey($permission->getKey()))
                ->exists();
        }

        return $this->roles()
            ->whereHas('permissions', fn ($query) => $query
                ->where(fn ($permissionQuery) => $permissionQuery
                    ->where('permissions.id', $permission)
                    ->orWhere('permission_key', $permission)))
            ->exists();
    }

    public function assignRole(Role|string $role): void
    {
        $roleModel = $role instanceof Role
            ? $role
            : Role::query()->where('id', $role)->orWhere('role_name', $role)->firstOrFail();

        $this->roles()->syncWithoutDetaching([$roleModel->getKey()]);
    }

    public function givePermissionTo(Role|string $role, Permission|string $permission): void
    {
        $roleModel = $role instanceof Role
            ? $role
            : Role::query()->where('id', $role)->orWhere('role_name', $role)->firstOrFail();
        $permissionModel = $permission instanceof Permission
            ? $permission
            : Permission::query()->where('id', $permission)->orWhere('permission_key', $permission)->firstOrFail();

        $roleModel->permissions()->syncWithoutDetaching([$permissionModel->getKey()]);
    }
}
