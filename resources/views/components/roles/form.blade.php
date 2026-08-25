@props(['permissionGroups', 'role' => null])

@php
    $selectedPermissionIds = collect(old('permission_ids', $role?->permissions->pluck('id')->all() ?? []));
@endphp

<label class="flex flex-col gap-2">
    <span class="text-sm font-bold text-slate-700">اسم الدور</span>
    <input type="text" name="name" value="{{ old('name', $role?->role_name) }}" required @disabled($role?->is_system) autofocus class="rounded-xl border border-slate-300 px-4 py-3 outline-none disabled:bg-slate-100 disabled:text-slate-500 focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10">
    @if ($role?->is_system)
        <input type="hidden" name="name" value="{{ $role->role_name }}">
        <span class="text-xs text-slate-500">اسم الدور الأساسي ثابت، ويمكنك تعديل صلاحياته.</span>
    @endif
    @error('name') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
</label>

<fieldset class="mt-8">
    <legend class="text-base font-black text-slate-950">الصلاحيات</legend>
    <p class="mt-1 text-sm text-slate-500">حدد العمليات التي يسمح لهذا الدور تنفيذها.</p>

    <div class="mt-5 grid gap-5 lg:grid-cols-2">
        @foreach ($permissionGroups as $group => $permissions)
            <section class="rounded-xl border border-slate-200 p-4">
                <h3 class="font-black text-slate-900">{{ $group }}</h3>
                <div class="mt-3 grid gap-2 sm:grid-cols-2">
                    @foreach ($permissions as $permission)
                        <label class="flex cursor-pointer items-center gap-2 rounded-lg p-2 text-sm hover:bg-slate-50">
                            <input type="checkbox" name="permission_ids[]" value="{{ $permission->id }}" @checked($selectedPermissionIds->contains($permission->id)) class="size-4 rounded border-slate-300 text-brand-500 focus:ring-brand-500">
                            <span class="font-medium text-slate-700">{{ $permission->permission_key }}</span>
                        </label>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
    @error('permission_ids') <span class="mt-2 block text-sm text-red-600">{{ $message }}</span> @enderror
    @error('permission_ids.*') <span class="mt-2 block text-sm text-red-600">{{ $message }}</span> @enderror
</fieldset>
