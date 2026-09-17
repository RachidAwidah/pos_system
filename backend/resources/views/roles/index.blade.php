<x-layouts.app title="الأدوار والصلاحيات" subtitle="تحكم بما يستطيع كل دور الوصول إليه">
    @if ($errors->has('role'))
        <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-700">{{ $errors->first('role') }}</div>
    @endif

    <div class="mb-6 flex justify-end">
        @if (auth()->user()->hasPermission('roles.create'))
            <a href="{{ route('roles.create') }}" class="rounded-xl bg-brand-500 px-4 py-2.5 text-sm font-bold text-white shadow-lg shadow-brand-500/20 hover:bg-brand-600">إنشاء دور مخصص</a>
        @endif
    </div>

    <div class="grid gap-5 xl:grid-cols-2">
        @foreach ($roles as $role)
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-lg font-black">{{ $role->role_name }}</h2>
                            @if ($role->is_system)
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-600">دور أساسي</span>
                            @endif
                        </div>
                        <p class="mt-1 text-sm text-slate-500">{{ $role->users_count }} مستخدم · {{ $role->permissions->count() }} صلاحية</p>
                    </div>
                    <div class="flex gap-2">
                        @if (auth()->user()->hasPermission('roles.edit'))
                            <a href="{{ route('roles.edit', $role) }}" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-bold text-slate-700 hover:border-brand-500 hover:text-brand-700">تعديل الصلاحيات</a>
                        @endif
                        @if (! $role->is_system && auth()->user()->hasPermission('roles.delete'))
                            <form method="POST" action="{{ route('roles.destroy', $role) }}" onsubmit="return confirm('هل تريد حذف هذا الدور؟')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-bold text-red-600 hover:bg-red-50">حذف</button>
                            </form>
                        @endif
                    </div>
                </div>

                <div class="mt-5 flex flex-wrap gap-2">
                    @forelse ($role->permissions as $permission)
                        <span class="rounded-lg bg-brand-50 px-2.5 py-1.5 text-xs font-bold text-brand-700">{{ $permission->permission_key }}</span>
                    @empty
                        <span class="text-sm text-slate-500">لا توجد صلاحيات لهذا الدور.</span>
                    @endforelse
                </div>
            </article>
        @endforeach
    </div>
</x-layouts.app>
