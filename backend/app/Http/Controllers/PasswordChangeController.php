<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChangePasswordRequest;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class PasswordChangeController extends Controller
{
    public function edit(): View
    {
        return view('auth.change-password');
    }

    public function update(ChangePasswordRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->update([
            'password_hash' => Hash::make($request->validated('new_password')),
            'must_change_password' => false,
        ]);
        $user->tokens()->delete();
        AuditLogService::log('password_change', User::class, $user->id);

        return redirect()->route('dashboard')->with('status', 'تم تغيير كلمة المرور بنجاح.');
    }
}
