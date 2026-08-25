<x-layouts.app title="لوحة التحكم" subtitle="نظرة سريعة على أداء المتجر اليوم">
    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['label' => 'مبيعات اليوم', 'value' => number_format((float) $salesToday, 2).' $', 'icon' => '↗', 'tone' => 'bg-emerald-50 text-emerald-700'],
            ['label' => 'فواتير اليوم', 'value' => number_format($ordersToday), 'icon' => '▤', 'tone' => 'bg-blue-50 text-blue-700'],
            ['label' => 'إجمالي المنتجات', 'value' => number_format($productsCount), 'icon' => '◇', 'tone' => 'bg-violet-50 text-violet-700'],
            ['label' => 'العملاء', 'value' => number_format($customersCount), 'icon' => '◎', 'tone' => 'bg-amber-50 text-amber-700'],
        ] as $metric)
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-bold text-slate-500">{{ $metric['label'] }}</p>
                        <p class="mt-3 text-3xl font-black text-slate-950">{{ $metric['value'] }}</p>
                    </div>
                    <span class="grid size-11 place-items-center rounded-xl text-xl font-black {{ $metric['tone'] }}">{{ $metric['icon'] }}</span>
                </div>
            </article>
        @endforeach
    </section>

    <section class="mt-6 grid gap-6 xl:grid-cols-5">
        <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm xl:col-span-3">
            <header class="flex items-center justify-between border-b border-slate-100 p-5">
                <div><h2 class="font-black">أحدث الفواتير</h2><p class="mt-1 text-sm text-slate-500">آخر عمليات البيع المسجلة</p></div>
                <a href="{{ route('orders.index') }}" class="text-sm font-bold text-brand-600">عرض الكل</a>
            </header>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-right text-slate-500"><tr><th class="px-5 py-3">الفاتورة</th><th class="px-5 py-3">العميل</th><th class="px-5 py-3">الإجمالي</th><th class="px-5 py-3">الحالة</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($recentOrders as $order)
                            <tr><td class="px-5 py-4 font-bold">{{ $order->invoice_number }}</td><td class="px-5 py-4 text-slate-600">{{ $order->contact?->name ?? 'عميل نقدي' }}</td><td class="px-5 py-4 font-bold">{{ number_format((float) $order->final_amount, 2) }} $</td><td class="px-5 py-4"><span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700">{{ $order->status }}</span></td></tr>
                        @empty
                            <tr><td colspan="4" class="px-5 py-12 text-center text-slate-500">لا توجد فواتير مسجلة بعد.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </article>

        <article class="rounded-2xl border border-slate-200 bg-white shadow-sm xl:col-span-2">
            <header class="border-b border-slate-100 p-5"><h2 class="font-black">تنبيه المخزون</h2><p class="mt-1 text-sm text-slate-500">منتجات وصلت إلى حد إعادة الطلب</p></header>
            <div class="flex flex-col gap-3 p-5">
                @forelse ($lowStockProducts as $product)
                    <div class="flex items-center justify-between gap-3 rounded-xl bg-red-50 p-3">
                        <div><p class="font-bold text-slate-900">{{ $product->product_name }}</p><p class="text-xs text-slate-500">{{ $product->category->category_name }}</p></div>
                        <span class="rounded-lg bg-white px-2.5 py-1 text-sm font-black text-red-600">{{ $product->quantity }}</span>
                    </div>
                @empty
                    <div class="py-10 text-center text-sm text-slate-500">المخزون بحالة جيدة.</div>
                @endforelse
            </div>
        </article>
    </section>
</x-layouts.app>
