<x-layouts.app title="الموردون" subtitle="بيانات الموردين والأرصدة المستحقة">
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @forelse ($suppliers as $supplier)
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <span class="grid size-12 place-items-center rounded-2xl bg-violet-50 text-lg font-black text-violet-700">{{ str($supplier->name)->substr(0, 1) }}</span>
                        <div>
                            <h2 class="font-black">{{ $supplier->name }}</h2>
                            <p class="mt-1 text-sm text-slate-500">{{ $supplier->company_name ?? 'مورد' }}</p>
                        </div>
                    </div>
                    <span class="rounded-full bg-violet-50 px-2.5 py-1 text-xs font-bold text-violet-700">مورد</span>
                </div>
                <dl class="mt-5 grid gap-3 border-t border-slate-100 pt-4 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">الهاتف</dt><dd class="font-bold">{{ $supplier->phone ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">البريد</dt><dd class="font-bold">{{ $supplier->email ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">الرصيد المستحق</dt><dd class="font-black {{ (float) $supplier->balance > 0 ? 'text-red-600' : 'text-emerald-600' }}">{{ number_format((float) $supplier->balance, 2) }} $</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">حد الاستحقاق</dt><dd class="font-black">{{ number_format((float) $supplier->payable_limit, 2) }} $</dd></div>
                </dl>
            </article>
        @empty
            <div class="col-span-full rounded-2xl border border-dashed border-slate-300 bg-white p-14 text-center text-slate-500">لا يوجد موردون.</div>
        @endforelse
    </div>
    <div class="mt-5">{{ $suppliers->links() }}</div>
</x-layouts.app>
