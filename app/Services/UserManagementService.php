<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserManagementService
{
    public function __construct(private AccessManagementGuard $access) {}

    /** @param array<string, mixed> $data */
    public function create(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $this->access->lockAdministratorRole();
            $this->access->ensureRolesCanBeGranted($data['role_ids']);
            $user = User::query()->create([
                'full_name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password_hash' => Hash::make($data['password']),
                'must_change_password' => (bool) ($data['must_change_password'] ?? false),
            ]);

            $user->roles()->sync($data['role_ids']);
            AuditLogService::created(User::class, $user->id, $this->auditValues($user));

            return $user->load('roles.permissions');
        });
    }

    /** @param array<string, mixed> $data */
    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
            $this->access->lockAdministratorRole();
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            $this->access->ensureUserCanBeManaged($user);
            $oldValues = $this->auditValues($user);
            $attributes = [];

            foreach (['email', 'phone', 'must_change_password'] as $field) {
                if (array_key_exists($field, $data)) {
                    $attributes[$field] = $data[$field];
                }
            }

            if (array_key_exists('name', $data)) {
                $attributes['full_name'] = $data['name'];
            }

            if (array_key_exists('password', $data)) {
                $attributes['password_hash'] = Hash::make($data['password']);
                $oldValues['credentials_changed'] = false;
                $user->tokens()->delete();
            }

            if (array_key_exists('role_ids', $data)) {
                $this->access->ensureRolesCanBeGranted($data['role_ids']);
                $this->guardLastAdministratorRole($user, $data['role_ids']);
            }

            $user->update($attributes);

            if (array_key_exists('role_ids', $data)) {
                $user->roles()->sync($data['role_ids']);
                $user->unsetRelation('roles');
            }

            $newValues = $this->auditValues($user);

            if (array_key_exists('password', $data)) {
                $newValues['credentials_changed'] = true;
            }

            AuditLogService::updated(User::class, $user->id, $oldValues, $newValues);

            return $user->refresh()->load('roles.permissions');
        });
    }

    public function delete(User $user, User $actor): void
    {
        if ($user->is($actor)) {
            throw ValidationException::withMessages(['user' => ['لا يمكنك حذف حسابك الحالي.']]);
        }

        DB::transaction(function () use ($user): void {
            $this->access->lockAdministratorRole();
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            $this->access->ensureUserCanBeManaged($user);
            if ($user->hasRole('Admin') && $this->administratorCount() === 1) {
                throw ValidationException::withMessages(['user' => ['لا يمكن حذف آخر مدير للنظام.']]);
            }
            AuditLogService::deleted(User::class, $user->id, $this->auditValues($user));
            $user->tokens()->delete();
            $user->delete();
        });
    }

    /** @param array<int, string> $roleIds */
    private function guardLastAdministratorRole(User $user, array $roleIds): void
    {
        if (! $user->hasRole('Admin') || $this->administratorCount() !== 1) {
            return;
        }

        $adminRoleId = Role::query()->where('role_name', 'Admin')->value('id');

        if (! in_array($adminRoleId, $roleIds, true)) {
            throw ValidationException::withMessages([
                'role_ids' => ['لا يمكن إزالة دور Admin من آخر مدير للنظام.'],
            ]);
        }
    }

    private function administratorCount(): int
    {
        return User::query()
            ->whereHas('roles', fn ($query) => $query->where('role_name', 'Admin'))
            ->count();
    }

    /** @return array{full_name: string, email: string, phone: ?string, must_change_password: bool, role_ids: array<int, string>} */
    private function auditValues(User $user): array
    {
        $user->loadMissing('roles');

        return [
            ...$user->only(['full_name', 'email', 'phone', 'must_change_password']),
            'role_ids' => $user->roles->pluck('id')->sort()->values()->all(),
        ];
    }
}
