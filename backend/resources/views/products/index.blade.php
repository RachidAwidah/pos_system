<x-layouts.app title="إدارة المنتجات" subtitle="إدارة الكتالوج والأسعار والمخزون من شاشة واحدة">
    <div class="grid gap-6 xl:grid-cols-[280px_minmax(0,1fr)]">
        <aside class="h-fit rounded-2xl bg-slate-900 p-4 text-white shadow-xl xl:sticky xl:top-24">
            <div class="flex items-center justify-between gap-3 border-b border-white/10 pb-4">
                <div>
                    <h2 class="font-black">شجرة التصنيفات</h2>
                    <p class="mt-1 text-xs text-slate-400">اختر قسماً لعرض منتجات كل فروعه</p>
                </div>
                <a href="{{ route('categories.index') }}" class="rounded-lg bg-white/10 px-2.5 py-1.5 text-xs font-bold hover:bg-white/20">إدارة</a>
            </div>

            <nav class="mt-4 max-h-[65vh] overflow-y-auto" aria-label="تصفية المنتجات حسب التصنيف">
                <a href="{{ route('products.index', collect($filters)->except(['category_id', 'page'])->all()) }}" class="flex items-center justify-between gap-3 rounded-lg px-3 py-2 text-sm font-bold {{ $selectedCategory === null ? 'bg-brand-500 text-white' : 'text-slate-200 hover:bg-white/10' }}">
                    <span>كل المنتجات</span>
                    <span class="rounded bg-black/20 px-2 py-0.5 text-xs">{{ $products->total() }}</span>
                </a>

                <ul class="mt-2 grid gap-0.5">
                    @foreach ($categoryTree as $category)
                        <x-products.category-tree-filter
                            :category="$category"
                            :filters="$filters"
                            :selected-category-id="$selectedCategory?->id"
                            :selected-path-ids="$selectedCategory?->path_ids ?? []"
                        />
                    @endforeach
                </ul>
            </nav>
        </aside>

        <section class="min-w-0">
            <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-black text-slate-950">{{ $selectedCategory?->path ?? 'كل المنتجات' }}</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ $products->total() }} منتج مطابق</p>
                </div>
                @if (auth()->user()->hasPermission('products.create'))
                    <a href="{{ route('products.create') }}" class="rounded-xl bg-brand-600 px-5 py-3 text-center text-sm font-black text-white shadow-lg shadow-brand-500/20 hover:bg-brand-700">إضافة منتج</a>
                @endif
            </div>

            <form method="GET" action="{{ route('products.index') }}" class="mb-5 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm lg:grid-cols-[minmax(220px,1fr)_180px_180px_auto]">
                @if ($selectedCategory)
                    <input type="hidden" name="category_id" value="{{ $selectedCategory->id }}">
                @endif
                <label class="grid gap-1.5 text-xs font-bold text-slate-500">
                    البحث
                    <input name="search" type="search" value="{{ $filters['search'] ?? '' }}" placeholder="الاسم، SKU أو الباركود" class="rounded-xl border border-slate-200 px-3 py-2.5 text-sm text-slate-900 outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10">
                </label>
                <label class="grid gap-1.5 text-xs font-bold text-slate-500">
                    نوع المنتج
                    <select name="type" class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 outline-none focus:border-brand-500">
                        <option value="">كل الأنواع</option>
                        @foreach ($productTypes as $productType)
                            <option value="{{ $productType->value }}" @selected(($filters['type'] ?? '') === $productType->value)>{{ match ($productType->value) { 'stock' => 'منتج مخزني', 'non_stock' => 'غير مخزني', 'service' => 'خدمة' } }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="grid gap-1.5 text-xs font-bold text-slate-500">
                    المستودع
                    <select name="warehouse_id" class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 outline-none focus:border-brand-500">
                        @foreach ($warehouses as $warehouseOption)
                            <option value="{{ $warehouseOption->id }}" @selected($warehouse->is($warehouseOption))>{{ $warehouseOption->name }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="flex items-end gap-2">
                    <label class="flex min-h-11 flex-1 cursor-pointer items-center justify-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50">
                        <input name="low_stock" type="checkbox" value="1" @checked($filters['low_stock'] ?? false) class="size-4 rounded border-slate-300 text-brand-600">
                        منخفض المخزون
                    </label>
                    <button class="min-h-11 rounded-xl bg-slate-900 px-4 py-2 text-sm font-black text-white hover:bg-slate-800">تطبيق</button>
                </div>
            </form>

            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[920px] text-sm">
                        <thead class="bg-slate-50 text-right text-xs text-slate-500">
                            <tr>
                                <th class="px-5 py-3">المنتج</th>
                                <th class="px-5 py-3">SKU / الباركود</th>
                                <th class="px-5 py-3">التصنيف</th>
                                <th class="px-5 py-3">النوع</th>
                                @if (auth()->user()->hasPermission('reports.view_financial'))
                                    <th class="px-5 py-3">التكلفة</th>
                                @endif
                                <th class="px-5 py-3">سعر البيع</th>
                                <th class="px-5 py-3">المخزون</th>
                                <th class="px-5 py-3"><span class="sr-only">الإجراءات</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($products as $product)
                                @php($isLowStock = $product->type->tracksInventory() && (float) $product->quantity <= (float) $product->reorder_level)
                                <tr class="hover:bg-slate-50">
                                    <td class="px-5 py-4">
                                        <div class="flex items-center gap-3">
                                            <img src="{{ $product->image_url }}" alt="{{ $product->product_name }}" class="size-12 rounded-xl bg-slate-100 object-cover">
                                            <div class="min-w-0">
                                                <p class="truncate font-black text-slate-900">{{ $product->product_name }}</p>
                                                <p class="mt-1 max-w-52 truncate text-xs text-slate-500">{{ $product->description ?: 'بدون وصف' }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-5 py-4 text-slate-600"><span class="block font-bold">{{ $product->sku }}</span><span class="mt-1 block text-xs">{{ $product->barcode ?? '—' }}</span></td>
                                    <td class="px-5 py-4 font-bold text-slate-700">{{ $product->category->category_name }}</td>
                                    <td class="px-5 py-4"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-600">{{ match ($product->type->value) { 'stock' => 'مخزني', 'non_stock' => 'غير مخزني', 'service' => 'خدمة' } }}</span></td>
                                    @if (auth()->user()->hasPermission('reports.view_financial'))
                                        <td class="px-5 py-4">{{ number_format((float) $product->cost_price, 2) }}</td>
                                    @endif
                                    <td class="px-5 py-4 font-black text-brand-700">{{ number_format((float) $product->price, 2) }}</td>
                                    <td class="px-5 py-4">
                                        <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $isLowStock ? 'bg-red-50 text-red-700' : 'bg-emerald-50 text-emerald-700' }}">
                                            {{ $product->type->tracksInventory() ? number_format((float) $product->quantity, $product->unit->decimal_places) : 'لا يتتبع' }} {{ $product->unit->symbol }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-4">
                                        <div class="flex justify-end gap-2">
                                            @if (auth()->user()->hasPermission('products.edit'))
                                                <a href="{{ route('products.edit', $product) }}" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-bold text-slate-700 hover:border-brand-500 hover:text-brand-700">تعديل</a>
                                            @endif
                                            @if (auth()->user()->hasPermission('products.delete'))
                                                <form method="POST" action="{{ route('products.destroy', $product) }}" onsubmit="return confirm('هل تريد أرشفة هذا المنتج؟ سيبقى محفوظاً في الفواتير السابقة.')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-bold text-red-600 hover:bg-red-50">أرشفة</button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="px-5 py-16 text-center text-slate-500">لا توجد منتجات مطابقة للفلاتر المحددة.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-slate-100 p-4">{{ $products->links() }}</div>
            </div>
        </section>
    </div>
</x-layouts.app>
