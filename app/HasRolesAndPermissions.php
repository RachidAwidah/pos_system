<?php

namespace App;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

/** @mixin User */
trait HasRolesAndPermissions
{
    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_role');
    }

    /**
     * @return Collection<int, Permission>
     */
    public function permissions(): Collection
    {
        return $this->assignedRoles()
            ->flatMap(function (Role $role): Collection {
                /** @var Collection<int, Permission> $permissions */
                $permissions = $role->getRelation('permissions');

                return $permissions;
            })
            ->unique('id')
            ->values();
    }

    public function hasRole(Role|string $role): bool
    {
        $assignedRoles = $this->assignedRoles();

        if ($role instanceof Role) {
            return $assignedRoles->contains(fn (Role $assignedRole): bool => $assignedRole->is($role));
        }

        return $assignedRoles->contains(fn (Role $assignedRole): bool => $assignedRole->getKey() === $role
            || $assignedRole->getAttribute('role_name') === $role);
    }

    public function hasPermission(Permission|string $permission): bool
    {
        if ($this->hasRole('Admin')) {
            return true;
        }

        if ($permission instanceof Permission) {
            return $this->permissions()->contains(fn (Permission $assignedPermission): bool => $assignedPermission->is($permission));
        }

        return $this->permissions()->contains(fn (Permission $assignedPermission): bool => $assignedPermission->getKey() === $permission
            || $assignedPermission->getAttribute('permission_key') === $permission);
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

    /**
     * @return Collection<int, Role>
     */
    private function assignedRoles(): Collection
    {
        $this->loadMissing('roles.permissions');

        /** @var Collection<int, Role> $roles */
        $roles = $this->getRelation('roles');

        return $roles;
    }
}
