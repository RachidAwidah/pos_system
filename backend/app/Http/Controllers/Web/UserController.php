<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\UserManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        return view('users.index', [
            'users' => User::query()->with('roles:id,role_name')->orderBy('full_name')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('users.create', [
            'roles' => Role::query()->withCount('permissions')->orderBy('role_name')->get(),
        ]);
    }

    public function store(StoreUserRequest $request, UserManagementService $users): RedirectResponse
    {
        $users->create($request->validated());

        return redirect()->route('users.index')->with('status', 'تم إنشاء المستخدم وتعيين دوره بنجاح.');
    }

    public function edit(User $user): View
    {
        return view('users.edit', [
            'user' => $user->load('roles:id,role_name'),
            'roles' => Role::query()->withCount('permissions')->orderBy('role_name')->get(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user, UserManagementService $users): RedirectResponse
    {
        $users->update($user, $request->validated());

        return redirect()->route('users.index')->with('status', 'تم تحديث المستخدم بنجاح.');
    }

    public function destroy(Request $request, User $user, UserManagementService $users): RedirectResponse
    {
        $users->delete($user, $request->user());

        return redirect()->route('users.index')->with('status', 'تم حذف المستخدم بنجاح.');
    }
}
