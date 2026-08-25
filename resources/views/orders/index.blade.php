<x-layouts.app title="الفواتير" subtitle="سجل المبيعات والمدفوعات">
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 p-5"><h2 class="font-black">سجل الفواتير</h2><p class="mt-1 text-sm text-slate-500">{{ $orders->total() }} فاتورة</p></div>
        <div class="overflow-x-auto"><table class="w-full min-w-[760px] text-sm"><thead class="bg-slate-50 text-right text-slate-500"><tr><th class="px-5 py-3">رقم الفاتورة</th><th class="px-5 py-3">التاريخ</th><th class="px-5 py-3">العميل</th><th class="px-5 py-3">الكاشير</th><th class="px-5 py-3">الإجمالي</th><th class="px-5 py-3">الدفع</th></tr></thead><tbody class="divide-y divide-slate-100">
            @forelse ($orders as $order)
                <tr class="hover:bg-slate-50"><td class="px-5 py-4 font-black">{{ $order->invoice_number }}</td><td class="px-5 py-4 text-slate-500">{{ $order->order_date->format('Y-m-d H:i') }}</td><td class="px-5 py-4">{{ $order->contact?->name ?? 'عميل نقدي' }}</td><td class="px-5 py-4">{{ $order->user->full_name }}</td><td class="px-5 py-4 font-black text-brand-700">{{ number_format((float) $order->final_amount, 2) }} $</td><td class="px-5 py-4"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold">{{ $order->payment_status }}</span></td></tr>
            @empty
                <tr><td colspan="6" class="px-5 py-16 text-center"><span class="block text-4xl text-slate-300">▤</span><p class="mt-3 font-bold text-slate-600">لا توجد فواتير بعد</p><a href="{{ route('pos.index') }}" class="mt-3 inline-block text-sm font-bold text-brand-600">افتح شاشة البيع</a></td></tr>
            @endforelse
        </tbody></table></div><div class="border-t border-slate-100 p-4">{{ $orders->links() }}</div>
    </div>
</x-layouts.app>
