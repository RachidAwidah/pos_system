<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Services\RoleManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(): View
    {
        return view('roles.index', [
            'roles' => Role::query()
                ->with('permissions:id,permission_key')
                ->withCount('users')
                ->orderBy('role_name')
                ->get(),
        ]);
    }

    public function create(): View
    {
        return view('roles.create', ['permissionGroups' => $this->permissionGroups()]);
    }

    public function store(StoreRoleRequest $request, RoleManagementService $roles): RedirectResponse
    {
        $roles->create($request->validated());

        return redirect()->route('roles.index')->with('status', 'تم إنشاء الدور وصلاحياته بنجاح.');
    }

    public function edit(Role $role): View
    {
        return view('roles.edit', [
            'role' => $role->load('permissions:id,permission_key'),
            'permissionGroups' => $this->permissionGroups(),
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role, RoleManagementService $roles): RedirectResponse
    {
        $roles->update($role, $request->validated());

        return redirect()->route('roles.index')->with('status', 'تم تحديث الدور وصلاحياته بنجاح.');
    }

    public function destroy(Role $role, RoleManagementService $roles): RedirectResponse
    {
        $roles->delete($role);

        return redirect()->route('roles.index')->with('status', 'تم حذف الدور بنجاح.');
    }

    /** @return Collection<string, \Illuminate\Database\Eloquent\Collection<int, Permission>> */
    private function permissionGroups(): Collection
    {
        return Permission::query()
            ->orderBy('permission_key')
            ->get()
            ->groupBy(fn (Permission $permission): string => Str::before($permission->permission_key, '.'));
    }
}
