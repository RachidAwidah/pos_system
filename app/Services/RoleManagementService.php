<?php

namespace App\Services;

use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RoleManagementService
{
    /** @param array<string, mixed> $data */
    public function create(array $data): Role
    {
        return DB::transaction(function () use ($data): Role {
            $role = Role::query()->create(['role_name' => $data['name']]);
            $role->permissions()->sync($data['permission_ids']);
            $role->load('permissions');
            AuditLogService::created(Role::class, $role->id, $this->auditValues($role));

            return $role;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(Role $role, array $data): Role
    {
        return DB::transaction(function () use ($role, $data): Role {
            $oldValues = $this->auditValues($role);

            if (array_key_exists('name', $data)) {
                if ($role->is_system && $data['name'] !== $role->role_name) {
                    throw ValidationException::withMessages(['name' => ['لا يمكن تغيير اسم دور أساسي.']]);
                }

                $role->update(['role_name' => $data['name']]);
            }

            if (array_key_exists('permission_ids', $data)) {
                $role->permissions()->sync($data['permission_ids']);
            }

            $role->refresh()->load('permissions');
            AuditLogService::updated(Role::class, $role->id, $oldValues, $this->auditValues($role));

            return $role;
        });
    }

    public function delete(Role $role): void
    {
        if ($role->is_system) {
            throw ValidationException::withMessages(['role' => ['لا يمكن حذف دور أساسي.']]);
        }

        if ($role->users()->exists()) {
            throw ValidationException::withMessages(['role' => ['لا يمكن حذف دور مرتبط بمستخدمين.']]);
        }

        DB::transaction(function () use ($role): void {
            AuditLogService::deleted(Role::class, $role->id, $this->auditValues($role));
            $role->delete();
        });
    }

    /** @return array{role_name: string, permission_ids: array<int, string>} */
    private function auditValues(Role $role): array
    {
        $role->loadMissing('permissions:id');

        return [
            'role_name' => $role->role_name,
            'permission_ids' => $role->permissions->pluck('id')->sort()->values()->all(),
        ];
    }
}
