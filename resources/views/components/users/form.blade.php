@props(['roles', 'user' => null, 'passwordRequired' => false])

@php
    $selectedRoleId = old('role_ids.0', $user?->roles->first()?->id);
    $mustChangePassword = (bool) old('must_change_password', $user?->must_change_password ?? false);
@endphp

<div class="grid gap-5 md:grid-cols-2">
    <label class="flex flex-col gap-2">
        <span class="text-sm font-bold text-slate-700">الاسم الكامل</span>
        <input type="text" name="name" value="{{ old('name', $user?->full_name) }}" required autofocus class="rounded-xl border border-slate-300 px-4 py-3 outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10">
        @error('name') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
    </label>

    <label class="flex flex-col gap-2">
        <span class="text-sm font-bold text-slate-700">البريد الإلكتروني</span>
        <input type="email" name="email" value="{{ old('email', $user?->email) }}" required autocomplete="email" class="rounded-xl border border-slate-300 px-4 py-3 outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10">
        @error('email') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
    </label>

    <label class="flex flex-col gap-2 md:col-span-2">
        <span class="text-sm font-bold text-slate-700">رقم الهاتف <span class="font-normal text-slate-400">(اختياري)</span></span>
        <input type="text" name="phone" value="{{ old('phone', $user?->phone) }}" class="rounded-xl border border-slate-300 px-4 py-3 outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10">
        @error('phone') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
    </label>

    <label class="flex flex-col gap-2">
        <span class="text-sm font-bold text-slate-700">كلمة المرور @unless ($passwordRequired)<span class="font-normal text-slate-400">(اتركها فارغة دون تغيير)</span>@endunless</span>
        <input type="password" name="password" @required($passwordRequired) autocomplete="new-password" class="rounded-xl border border-slate-300 px-4 py-3 outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10">
        @error('password') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
    </label>

    <label class="flex flex-col gap-2">
        <span class="text-sm font-bold text-slate-700">تأكيد كلمة المرور</span>
        <input type="password" name="password_confirmation" @required($passwordRequired) autocomplete="new-password" class="rounded-xl border border-slate-300 px-4 py-3 outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10">
    </label>
</div>

<fieldset class="mt-8">
    <legend class="text-base font-black text-slate-950">الدور</legend>
    <p class="mt-1 text-sm text-slate-500">اختر دور المستخدم، وسيحصل تلقائياً على الصلاحيات المحددة لهذا الدور.</p>
    <select name="role_ids[]" required class="mt-4 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10">
        <option value="">اختر الدور</option>
        @foreach ($roles as $role)
            <option value="{{ $role->id }}" @selected($selectedRoleId === $role->id)>{{ $role->role_name }} — {{ $role->permissions_count }} صلاحية</option>
        @endforeach
    </select>
    @error('role_ids') <span class="mt-2 block text-sm text-red-600">{{ $message }}</span> @enderror
    @error('role_ids.*') <span class="mt-2 block text-sm text-red-600">{{ $message }}</span> @enderror
</fieldset>

<label class="mt-8 flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4">
    <input type="hidden" name="must_change_password" value="0">
    <input type="checkbox" name="must_change_password" value="1" @checked($mustChangePassword) class="mt-1 size-4 rounded border-slate-300 text-brand-500 focus:ring-brand-500">
    <span>
        <span class="block font-black text-slate-900">إجبار تغيير كلمة المرور عند الدخول التالي</span>
        <span class="mt-1 block text-sm text-slate-500">اتركه غير محدد ليسجل المستخدم دخوله بشكل طبيعي.</span>
    </span>
</label>
