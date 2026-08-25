<x-layouts.app title="شاشة البيع" subtitle="ابحث بالاسم أو امسح الباركود لإضافة المنتجات">
    <div data-pos-root data-products='@json($products)' class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
        <section>
            <div class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:flex-row">
                <label class="relative flex-1">
                    <span class="sr-only">البحث</span>
                    <input data-pos-search type="search" placeholder="اسم المنتج، SKU أو الباركود..." autocomplete="off" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 outline-none focus:border-brand-500 focus:bg-white focus:ring-4 focus:ring-brand-500/10">
                </label>
                <select data-pos-category class="rounded-xl border border-slate-200 bg-white px-4 py-3 font-bold text-slate-700 outline-none focus:border-brand-500">
                    <option value="">كل الفئات</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->category_name }}</option>
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
                    <select class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none focus:border-brand-500">
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                        @endforeach
                    </select>
                </label>

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
                <button type="button" disabled class="mt-5 w-full cursor-not-allowed rounded-xl bg-slate-300 px-5 py-3.5 font-black text-white" title="سيتم ربطه بخدمة البيع الآمنة في المرحلة التالية">إتمام الدفع</button>
                <p class="mt-2 text-center text-xs text-amber-700">الحساب والسلة يعملان؛ حفظ الفاتورة سيُربط بخدمة البيع Transactional.</p>
            </footer>
        </aside>
    </div>
</x-layouts.app>
