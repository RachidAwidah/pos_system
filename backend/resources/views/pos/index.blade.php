<x-layouts.app title="شاشة البيع" subtitle="ابحث بالاسم أو امسح الباركود لإضافة المنتجات">
    @if (session('status'))
        <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-800">{{ session('status') }}</div>
    @endif

    @if (! $openShift)
        <section class="mx-auto max-w-xl rounded-2xl border border-amber-200 bg-white p-6 shadow-sm">
            <div class="rounded-xl bg-amber-50 p-4 text-amber-900">
                <h2 class="font-black">يجب فتح وردية قبل بدء البيع</h2>
                <p class="mt-1 text-sm">اختر الصندوق وسجّل المبلغ النقدي الموجود عند البداية.</p>
            </div>
            <form method="POST" action="{{ route('pos.shifts.store') }}" class="mt-5 grid gap-4">
                @csrf
                <label class="grid gap-2 text-sm font-bold text-slate-700">
                    الصندوق
                    <select name="register_id" required class="rounded-xl border border-slate-200 bg-white px-3 py-3 outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10">
                        @foreach ($registers as $register)
                            <option value="{{ $register->id }}">{{ $register->name }} ({{ $register->code }})</option>
                        @endforeach
                    </select>
                </label>
                <label class="grid gap-2 text-sm font-bold text-slate-700">
                    رصيد الافتتاح
                    <input name="opening_cash" type="number" min="0" step="0.01" value="0.00" required class="rounded-xl border border-slate-200 bg-white px-3 py-3 outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10">
                </label>
                <button class="rounded-xl bg-brand-600 px-5 py-3 font-black text-white hover:bg-brand-700">فتح الوردية</button>
            </form>
        </section>
    @else
    <div data-pos-root data-products='@json($products)' data-checkout-url="{{ route('pos.checkout') }}" data-csrf-token="{{ csrf_token() }}" data-shift-id="{{ $openShift->id }}" class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
        <section>
            <div class="mb-4 flex flex-col gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 sm:flex-row sm:items-center sm:justify-between">
                <div><p class="font-black text-emerald-900">وردية مفتوحة — {{ $openShift->register->name }}</p><p class="text-sm text-emerald-700">افتتحت {{ $openShift->opened_at->format('H:i') }} برصيد {{ number_format((float) $openShift->opening_cash, 2) }}</p></div>
                <form method="POST" action="{{ route('pos.shifts.close', $openShift) }}" class="flex gap-2">
                    @csrf
                    <input name="closing_cash" type="number" min="0" step="0.01" required placeholder="النقد عند الإغلاق" class="min-w-0 rounded-xl border border-emerald-200 bg-white px-3 py-2 text-sm outline-none focus:border-emerald-500">
                    <button class="shrink-0 rounded-xl border border-emerald-300 px-3 py-2 text-sm font-bold text-emerald-800 hover:bg-emerald-100">إغلاق الوردية</button>
                </form>
            </div>
            <div class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:flex-row">
                <label class="relative flex-1">
                    <span class="sr-only">البحث</span>
                    <input data-pos-search type="search" placeholder="اسم المنتج، SKU أو الباركود..." autocomplete="off" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 outline-none focus:border-brand-500 focus:bg-white focus:ring-4 focus:ring-brand-500/10">
                </label>
                <select data-pos-category class="rounded-xl border border-slate-200 bg-white px-4 py-3 font-bold text-slate-700 outline-none focus:border-brand-500">
                    <option value="">كل الفئات</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}">{{ str_repeat('— ', $category->depth) }}{{ $category->category_name }}</option>
                    @endforeach
                </select>
            </div>

            <div data-product-grid class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4"></div>
        </section>

        <aside class="h-fit rounded-2xl border border-slate-200 bg-white shadow-sm xl:sticky xl:top-24">
            <header class="flex items-center justify-between border-b border-slate-100 p-5">
                <div><h2 class="text-lg font-black">سلة البيع</h2><p class="mt-1 text-sm text-slate-500"><span data-cart-count>0</span> عناصر</p></div>
                <button data-cart-clear type="button" class="text-sm font-bold text-red-600 hover:text-red-700">تفريغ السلة</button>
            </header>

            <div class="p-5">
                <label class="flex flex-col gap-2">
                    <span class="text-xs font-bold text-slate-500">العميل</span>
                    <select data-pos-customer class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none focus:border-brand-500">
                        <option value="">عميل نقدي</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}">{{ $customer->name }} — رصيد {{ number_format((float) $customer->balance, 2) }}</option>
                        @endforeach
                    </select>
                </label>

                <div class="mt-4 grid grid-cols-2 gap-3">
                    <label class="grid gap-2 text-xs font-bold text-slate-500">نوع الخصم
                        <select data-pos-discount-type class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none focus:border-brand-500">
                            <option value="none">بدون خصم</option><option value="fixed">قيمة ثابتة</option><option value="percentage">نسبة مئوية</option>
                        </select>
                    </label>
                    <label class="grid gap-2 text-xs font-bold text-slate-500">قيمة الخصم
                        <input data-pos-discount-value type="number" min="0" step="0.01" value="0" class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-brand-500">
                    </label>
                </div>

                <div data-empty-cart class="py-12 text-center">
                    <span class="mx-auto grid size-16 place-items-center rounded-full bg-slate-100 text-3xl text-slate-400">▣</span>
                    <p class="mt-4 font-bold text-slate-700">السلة فارغة</p>
                    <p class="mt-1 text-sm text-slate-500">اختر منتجاً لبدء البيع.</p>
                </div>
                <div data-cart-lines class="mt-5 flex max-h-[42vh] flex-col gap-3 overflow-y-auto"></div>
            </div>

            <footer class="border-t border-slate-100 p-5">
                <dl class="flex flex-col gap-3 text-sm">
                    <div class="flex justify-between gap-3 text-slate-600"><dt>المجموع الفرعي</dt><dd data-cart-subtotal class="font-bold">0.00 $</dd></div>
                    <div class="flex justify-between gap-3 text-slate-600"><dt>الضريبة</dt><dd data-cart-tax class="font-bold">0.00 $</dd></div>
                    <div class="flex justify-between gap-3 border-t border-dashed border-slate-200 pt-3 text-lg"><dt class="font-black">الإجمالي</dt><dd data-cart-total class="font-black text-brand-700">0.00 $</dd></div>
                </dl>
                <div class="mt-4 grid grid-cols-2 gap-3">
                    <label class="grid gap-2 text-xs font-bold text-slate-500">طريقة الدفع
                        <select data-pos-payment-method class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 outline-none focus:border-brand-500">
                            @foreach ($paymentMethods as $method)
                                <option value="{{ $method->id }}" data-category="{{ $method->category }}">{{ $method->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="grid gap-2 text-xs font-bold text-slate-500">المبلغ المستلم
                        <input data-pos-payment-amount type="number" min="0" step="0.01" class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-brand-500">
                    </label>
                </div>
                <div data-pos-message class="mt-4 hidden rounded-xl px-3 py-2 text-sm font-bold"></div>
                <button data-pos-checkout type="button" disabled class="mt-4 w-full rounded-xl bg-brand-600 px-5 py-3.5 font-black text-white hover:bg-brand-700 disabled:cursor-not-allowed disabled:bg-slate-300">إتمام الدفع</button>
                <p class="mt-2 text-center text-xs text-slate-500">يُعاد احتساب السعر والخصم والضريبة والمخزون على السيرفر قبل حفظ الفاتورة.</p>
            </footer>
        </aside>
    </div>
    @endif
</x-layouts.app>
