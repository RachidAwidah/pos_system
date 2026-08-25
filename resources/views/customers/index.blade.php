<x-layouts.app title="العملاء" subtitle="بيانات العملاء والأرصدة ونقاط الولاء">
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @forelse ($customers as $customer)
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <span class="grid size-12 place-items-center rounded-2xl bg-blue-50 text-lg font-black text-blue-700">{{ str($customer->name)->substr(0, 1) }}</span>
                        <div>
                            <h2 class="font-black">{{ $customer->name }}</h2>
                            <p class="mt-1 text-sm text-slate-500">{{ $customer->company_name ?? 'عميل' }}</p>
                        </div>
                    </div>
                    <span class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-bold text-blue-700">عميل</span>
                </div>
                <dl class="mt-5 grid gap-3 border-t border-slate-100 pt-4 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">الهاتف</dt><dd class="font-bold">{{ $customer->phone ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">البريد</dt><dd class="font-bold">{{ $customer->email ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">الرصيد</dt><dd class="font-black {{ (float) $customer->balance > 0 ? 'text-red-600' : 'text-emerald-600' }}">{{ number_format((float) $customer->balance, 2) }} $</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-slate-500">نقاط الولاء</dt><dd class="font-black text-amber-600">{{ number_format($customer->loyalty_points) }}</dd></div>
                </dl>
            </article>
        @empty
            <div class="col-span-full rounded-2xl border border-dashed border-slate-300 bg-white p-14 text-center text-slate-500">لا يوجد عملاء.</div>
        @endforelse
    </div>
    <div class="mt-5">{{ $customers->links() }}</div>
</x-layouts.app>
