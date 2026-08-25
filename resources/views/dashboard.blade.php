<x-layouts.app title="لوحة التحكم" subtitle="ملخص أداء المتجر للشهر الحالي مع مؤشرات المبيعات والربحية">
    @php
        $summary = $overview['summary'];
        $maxTrend = max(1, collect($overview['trend'])->max(fn ($point) => max((float) $point['net_sales'], (float) $point['gross_profit'])) ?? 1);
    @endphp

    <section class="mb-6 flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-sm font-bold text-slate-500">الفترة الحالية</p>
            <p class="mt-1 text-lg font-black text-slate-950">{{ $overview['period']['from'] }} — {{ $overview['period']['to'] }}</p>
        </div>
        @if (auth()->user()->hasPermission('reports.view_sales'))
            <a href="{{ route('reports.index') }}" class="rounded-xl bg-brand-500 px-5 py-3 text-center text-sm font-black text-white shadow-lg shadow-brand-500/20 hover:bg-brand-600">فتح التقارير التفصيلية</a>
        @endif
    </section>

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
        @foreach ([
            ['label' => 'صافي المبيعات', 'value' => number_format((float) $summary['net_sales'], 2).' $', 'hint' => 'بعد الخصومات والمرتجعات', 'tone' => 'bg-emerald-50 text-emerald-700'],
            ['label' => 'الربح الإجمالي', 'value' => number_format((float) $summary['gross_profit'], 2).' $', 'hint' => 'بعد تكلفة البضاعة', 'tone' => 'bg-blue-50 text-blue-700'],
            ['label' => 'هامش الربح', 'value' => $summary['gross_margin_percentage'].'%', 'hint' => 'من صافي المبيعات', 'tone' => 'bg-violet-50 text-violet-700'],
            ['label' => 'الفواتير', 'value' => number_format($summary['orders_count']), 'hint' => 'فاتورة مكتملة', 'tone' => 'bg-amber-50 text-amber-700'],
            ['label' => 'متوسط الفاتورة', 'value' => number_format((float) $summary['average_order_value'], 2).' $', 'hint' => 'صافي الإجمالي ÷ الفواتير', 'tone' => 'bg-cyan-50 text-cyan-700'],
            ['label' => 'المرتجعات', 'value' => number_format((float) $summary['refunds'], 2).' $', 'hint' => number_format($summary['returns_count']).' عملية', 'tone' => 'bg-rose-50 text-rose-700'],
        ] as $metric)
            <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <span class="inline-flex rounded-lg px-2.5 py-1 text-xs font-black {{ $metric['tone'] }}">{{ $metric['label'] }}</span>
                <p class="mt-4 text-2xl font-black text-slate-950">{{ $metric['value'] }}</p>
                <p class="mt-2 text-xs text-slate-500">{{ $metric['hint'] }}</p>
            </article>
        @endforeach
    </section>

    <section class="mt-6 grid gap-6 xl:grid-cols-5">
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm xl:col-span-3">
            <header class="flex items-center justify-between gap-4">
                <div><h2 class="font-black">اتجاه المبيعات والأرباح</h2><p class="mt-1 text-sm text-slate-500">الأخضر للمبيعات، والأزرق للربح الإجمالي</p></div>
                <span class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-600">يومي</span>
            </header>
            <div class="mt-6 flex h-64 items-end gap-2 overflow-x-auto border-b border-slate-200 pb-2">
                @forelse ($overview['trend'] as $point)
                    <div class="group flex min-w-8 flex-1 items-end justify-center gap-0.5" title="{{ $point['date'] }} | مبيعات {{ $point['net_sales'] }} | ربح {{ $point['gross_profit'] }}">
                        <span class="w-2 rounded-t bg-emerald-400" style="height: {{ max(3, ((float) $point['net_sales'] / $maxTrend) * 220) }}px"></span>
                        <span class="w-2 rounded-t bg-blue-500" style="height: {{ max(3, ((float) $point['gross_profit'] / $maxTrend) * 220) }}px"></span>
                    </div>
                @empty
                    <p class="m-auto text-sm text-slate-500">لا توجد حركة ضمن الفترة.</p>
                @endforelse
            </div>
        </article>

        <article class="rounded-2xl border border-slate-200 bg-white shadow-sm xl:col-span-2">
            <header class="border-b border-slate-100 p-5"><h2 class="font-black">طرق الدفع</h2><p class="mt-1 text-sm text-slate-500">صافي المبالغ حسب طريقة التحصيل</p></header>
            <div class="flex flex-col gap-3 p-5">
                @forelse ($overview['payment_methods'] as $method)
                    <div class="flex items-center justify-between gap-4 rounded-xl bg-slate-50 p-3">
                        <div><p class="font-bold text-slate-900">{{ $method['name'] }}</p><p class="text-xs text-slate-500">{{ $method['code'] }}</p></div>
                        <span class="font-black text-brand-700">{{ number_format((float) $method['net_amount'], 2) }} $</span>
                    </div>
                @empty
                    <p class="py-10 text-center text-sm text-slate-500">لا توجد مدفوعات ضمن الفترة.</p>
                @endforelse
            </div>
        </article>
    </section>

    <section class="mt-6 grid gap-6 xl:grid-cols-2">
        <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <header class="border-b border-slate-100 p-5"><h2 class="font-black">المنتجات الأكثر مبيعاً</h2><p class="mt-1 text-sm text-slate-500">مرتبة حسب صافي الكمية بعد المرتجعات</p></header>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[560px] text-sm"><thead class="bg-slate-50 text-right text-slate-500"><tr><th class="px-5 py-3">المنتج</th><th class="px-5 py-3">الكمية</th><th class="px-5 py-3">المبيعات</th><th class="px-5 py-3">الربح</th></tr></thead><tbody class="divide-y divide-slate-100">
                @forelse ($overview['top_products'] as $product)
                    <tr><td class="px-5 py-4"><p class="font-bold">{{ $product['product_name'] }}</p><p class="text-xs text-slate-500">{{ $product['sku'] }}</p></td><td class="px-5 py-4 font-bold">{{ $product['net_quantity'] }}</td><td class="px-5 py-4">{{ $product['net_sales'] }} $</td><td class="px-5 py-4 font-black text-emerald-700">{{ $product['gross_profit'] }} $</td></tr>
                @empty
                    <tr><td colspan="4" class="px-5 py-12 text-center text-slate-500">لا توجد مبيعات.</td></tr>
                @endforelse
                </tbody></table>
            </div>
        </article>

        <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <header class="border-b border-slate-100 p-5"><h2 class="font-black">أفضل العملاء</h2><p class="mt-1 text-sm text-slate-500">حسب صافي المشتريات خلال الشهر</p></header>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[560px] text-sm"><thead class="bg-slate-50 text-right text-slate-500"><tr><th class="px-5 py-3">العميل</th><th class="px-5 py-3">الفواتير</th><th class="px-5 py-3">المشتريات</th><th class="px-5 py-3">الربح</th></tr></thead><tbody class="divide-y divide-slate-100">
                @forelse ($overview['top_customers'] as $customer)
                    <tr><td class="px-5 py-4"><p class="font-bold">{{ $customer['name'] }}</p><p class="text-xs text-slate-500">{{ $customer['phone'] }}</p></td><td class="px-5 py-4">{{ $customer['orders_count'] }}</td><td class="px-5 py-4 font-bold">{{ $customer['net_sales'] }} $</td><td class="px-5 py-4 font-black text-emerald-700">{{ $customer['gross_profit'] }} $</td></tr>
                @empty
                    <tr><td colspan="4" class="px-5 py-12 text-center text-slate-500">لا توجد مبيعات مرتبطة بعملاء.</td></tr>
                @endforelse
                </tbody></table>
            </div>
        </article>
    </section>

    <section class="mt-6 grid gap-6 xl:grid-cols-5">
        <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm xl:col-span-3">
            <header class="flex items-center justify-between border-b border-slate-100 p-5"><div><h2 class="font-black">أحدث الفواتير</h2><p class="mt-1 text-sm text-slate-500">آخر عمليات البيع المسجلة</p></div><a href="{{ route('orders.index') }}" class="text-sm font-bold text-brand-600">عرض الكل</a></header>
            <div class="overflow-x-auto"><table class="w-full text-sm"><thead class="bg-slate-50 text-right text-slate-500"><tr><th class="px-5 py-3">الفاتورة</th><th class="px-5 py-3">العميل</th><th class="px-5 py-3">الإجمالي</th><th class="px-5 py-3">الحالة</th></tr></thead><tbody class="divide-y divide-slate-100">
            @forelse ($recentOrders as $order)
                <tr><td class="px-5 py-4 font-bold">{{ $order->invoice_number }}</td><td class="px-5 py-4 text-slate-600">{{ $order->customer?->name ?? 'عميل نقدي' }}</td><td class="px-5 py-4 font-bold">{{ number_format((float) $order->final_amount, 2) }} $</td><td class="px-5 py-4"><span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700">{{ $order->status->value }}</span></td></tr>
            @empty
                <tr><td colspan="4" class="px-5 py-12 text-center text-slate-500">لا توجد فواتير مسجلة بعد.</td></tr>
            @endforelse
            </tbody></table></div>
        </article>

        <article class="rounded-2xl border border-slate-200 bg-white shadow-sm xl:col-span-2">
            <header class="border-b border-slate-100 p-5"><h2 class="font-black">تنبيه المخزون</h2><p class="mt-1 text-sm text-slate-500">منتجات وصلت إلى حد إعادة الطلب</p></header>
            <div class="flex flex-col gap-3 p-5">
                @forelse ($lowStockProducts as $product)
                    <div class="flex items-center justify-between gap-3 rounded-xl bg-red-50 p-3"><div><p class="font-bold text-slate-900">{{ $product->product_name }}</p><p class="text-xs text-slate-500">{{ $product->category->category_name }}</p></div><span class="rounded-lg bg-white px-2.5 py-1 text-sm font-black text-red-600">{{ $product->quantity }}</span></div>
                @empty
                    <div class="py-10 text-center text-sm text-slate-500">المخزون بحالة جيدة.</div>
                @endforelse
            </div>
        </article>
    </section>
</x-layouts.app>
