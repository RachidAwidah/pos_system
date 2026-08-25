<x-layouts.app title="تعديل الدور" subtitle="تحديث الدور والصلاحيات المرتبطة به">
    <form method="POST" action="{{ route('roles.update', $role) }}" class="mx-auto max-w-5xl rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
        @csrf
        @method('PUT')
        <x-roles.form :permission-groups="$permissionGroups" :role="$role" />

        <div class="mt-8 flex flex-col-reverse gap-3 border-t border-slate-100 pt-6 sm:flex-row sm:justify-end">
            <a href="{{ route('roles.index') }}" class="rounded-xl border border-slate-300 px-5 py-3 text-center text-sm font-bold text-slate-700 hover:bg-slate-50">إلغاء</a>
            <button type="submit" class="rounded-xl bg-brand-500 px-5 py-3 text-sm font-black text-white shadow-lg shadow-brand-500/20 hover:bg-brand-600">حفظ الصلاحيات</button>
        </div>
    </form>
</x-layouts.app>
