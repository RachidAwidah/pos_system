<x-layouts.app title="العملاء والموردون" subtitle="جهات الاتصال والأرصدة المالية">
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @forelse ($contacts as $contact)
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-3"><span class="grid size-12 place-items-center rounded-2xl bg-brand-50 text-lg font-black text-brand-700">{{ str($contact->name)->substr(0, 1) }}</span><div><h2 class="font-black">{{ $contact->name }}</h2><p class="mt-1 text-sm text-slate-500">{{ $contact->company_name ?? ($contact->type === 'customer' ? 'عميل' : 'مورد') }}</p></div></div>
                    <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $contact->type === 'customer' ? 'bg-blue-50 text-blue-700' : 'bg-violet-50 text-violet-700' }}">{{ $contact->type === 'customer' ? 'عميل' : 'مورد' }}</span>
                </div>
                <dl class="mt-5 grid gap-3 border-t border-slate-100 pt-4 text-sm"><div class="flex justify-between gap-3"><dt class="text-slate-500">الهاتف</dt><dd class="font-bold">{{ $contact->phone ?? '—' }}</dd></div><div class="flex justify-between gap-3"><dt class="text-slate-500">البريد</dt><dd class="font-bold">{{ $contact->email ?? '—' }}</dd></div><div class="flex justify-between gap-3"><dt class="text-slate-500">الرصيد</dt><dd class="font-black {{ (float) $contact->balance > 0 ? 'text-red-600' : 'text-emerald-600' }}">{{ number_format((float) $contact->balance, 2) }} $</dd></div></dl>
            </article>
        @empty
            <div class="col-span-full rounded-2xl border border-dashed border-slate-300 bg-white p-14 text-center text-slate-500">لا توجد جهات اتصال.</div>
        @endforelse
    </div>
    <div class="mt-5">{{ $contacts->links() }}</div>
</x-layouts.app>
