<x-layouts.app title="المستخدمون" subtitle="إدارة حسابات الفريق والأدوار المسندة">
    @if ($errors->has('user') || $errors->has('role_ids'))
        <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-700">
            {{ $errors->first('user') ?: $errors->first('role_ids') }}
        </div>
    @endif

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-4 border-b border-slate-100 p-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="font-black">فريق العمل</h2>
                <p class="mt-1 text-sm text-slate-500">{{ $users->total() }} مستخدم</p>
            </div>
            @if (auth()->user()->hasPermission('users.create'))
                <a href="{{ route('users.create') }}" class="rounded-xl bg-brand-500 px-4 py-2.5 text-center text-sm font-bold text-white shadow-lg shadow-brand-500/20 hover:bg-brand-600">إنشاء مستخدم</a>
            @endif
        </div>

        <div class="divide-y divide-slate-100">
            @forelse ($users as $user)
                <div class="flex flex-col gap-4 p-5 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex items-center gap-3">
                        <span class="grid size-12 shrink-0 place-items-center rounded-full bg-slate-950 text-lg font-black text-white">{{ str($user->full_name)->substr(0, 1) }}</span>
                        <div>
                            <h3 class="font-black">{{ $user->full_name }}</h3>
                            <p class="mt-1 text-sm text-slate-500">{{ $user->email }}@if ($user->phone) · {{ $user->phone }}@endif</p>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        @foreach ($user->roles as $role)
                            <span class="rounded-full bg-brand-50 px-3 py-1 text-xs font-bold text-brand-700">{{ $role->role_name }}</span>
                        @endforeach
                        @if ($user->must_change_password)
                            <span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-bold text-amber-700">تغيير كلمة المرور مطلوب</span>
                        @endif
                        @if (auth()->user()->hasPermission('users.edit'))
                            <a href="{{ route('users.edit', $user) }}" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-bold text-slate-700 hover:border-brand-500 hover:text-brand-700">تعديل</a>
                        @endif
                        @if (auth()->user()->hasPermission('users.delete') && ! auth()->user()->is($user))
                            <form method="POST" action="{{ route('users.destroy', $user) }}" onsubmit="return confirm('هل تريد حذف هذا المستخدم؟')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-bold text-red-600 hover:bg-red-50">حذف</button>
                            </form>
                        @endif
                    </div>
                </div>
            @empty
                <div class="p-10 text-center text-sm text-slate-500">لا يوجد مستخدمون.</div>
            @endforelse
        </div>

        <div class="border-t border-slate-100 p-4">{{ $users->links() }}</div>
    </div>
</x-layouts.app>
