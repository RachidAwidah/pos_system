<div>
    <!-- An unexamined life is not worth living. - Socrates -->
</div>
@props([
    'categories',
    'units',
    'taxes',
    'warehouses',
    'defaultWarehouse',
    'productTypes',
    'product' => null,
    'balance' => null,
    'selectedWarehouse' => null,
])

@php
    $isEditing = $product !== null;
    $currentType = old('type', $product?->type->value ?? 'stock');
    $canEditPrice = ! $isEditing || auth()->user()->hasPermission('products.edit_price');
    $warehouseValue = old('warehouse_id', ($selectedWarehouse ?? $defaultWarehouse)->id);
@endphp

@if ($errors->any())
    <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-700">
        <p>{{ $errors->first() }}</p>
    </div>
@endif

<div data-product-form class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_300px]">
    <div class="grid gap-6">
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="mb-5">
                <h2 class="font-black text-slate-950">المعلومات الأساسية</h2>
                <p class="mt-1 text-sm text-slate-500">بيانات ظهور المنتج داخل شاشة البيع والفواتير.</p>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <label class="grid gap-2 text-sm font-bold text-slate-700 md:col-span-2">
                    اسم المنتج
                    <input name="product_name" value="{{ old('product_name', $product?->product_name) }}" required maxlength="255" autofocus class="rounded-xl border border-slate-200 px-3 py-3 outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10">
                </label>
                <label class="grid gap-2 text-sm font-bold text-slate-700">
                    نوع المنتج
                    <select name="type" data-product-type required class="rounded-xl border border-slate-200 bg-white px-3 py-3 outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10">
                        @foreach ($productTypes as $productType)
                            <option value="{{ $productType->value }}" @selected($currentType === $productType->value)>{{ match ($productType->value) { 'stock' => 'منتج مخزني', 'non_stock' => 'منتج غير مخزني', 'service' => 'خدمة' } }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="grid gap-2 text-sm font-bold text-slate-700">
                    التصنيف
                    <select name="category_id" required class="rounded-xl border border-slate-200 bg-white px-3 py-3 outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10">
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected(old('category_id', $product?->category_id) === $category->id)>{{ $category->path }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="grid gap-2 text-sm font-bold text-slate-700">
                    وحدة القياس
                    <select name="unit_id" required class="rounded-xl border border-slate-200 bg-white px-3 py-3 outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10">
                        @foreach ($units as $unit)
                            <option value="{{ $unit->id }}" @selected(old('unit_id', $product?->unit_id) === $unit->id)>{{ $unit->name }} ({{ $unit->symbol }})</option>
                        @endforeach
                    </select>
                </label>
                <label class="grid gap-2 text-sm font-bold text-slate-700">
                    الضريبة
                    <select name="tax_id" class="rounded-xl border border-slate-200 bg-white px-3 py-3 outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10">
                        <option value="">بدون ضريبة</option>
                        @foreach ($taxes as $tax)
                            <option value="{{ $tax->id }}" @selected(old('tax_id', $product?->tax_id) === $tax->id)>{{ $tax->tax_name }} — {{ number_format((float) $tax->tax_percentage, 2) }}%</option>
                        @endforeach
                    </select>
                </label>
                <label class="grid gap-2 text-sm font-bold text-slate-700 md:col-span-2">
                    الوصف
                    <textarea name="description" rows="4" class="rounded-xl border border-slate-200 px-3 py-3 outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10">{{ old('description', $product?->description) }}</textarea>
                </label>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="mb-5">
                <h2 class="font-black text-slate-950">التعريف والمسح</h2>
                <p class="mt-1 text-sm text-slate-500">SKU داخلي إلزامي، والباركود مستقل ويمكن مسحه في نقطة البيع.</p>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
                <label class="grid gap-2 text-sm font-bold text-slate-700">
                    SKU
                    <input name="sku" value="{{ old('sku', $product?->sku) }}" required maxlength="100" dir="ltr" class="rounded-xl border border-slate-200 px-3 py-3 text-left outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10">
                </label>
                <label class="grid gap-2 text-sm font-bold text-slate-700">
                    الباركود
                    <input name="barcode" value="{{ old('barcode', $product?->barcode) }}" maxlength="100" dir="ltr" inputmode="numeric" class="rounded-xl border border-slate-200 px-3 py-3 text-left outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10">
                </label>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="font-black text-slate-950">التسعير</h2>
                    <p class="mt-1 text-sm text-slate-500">السعر والتكلفة محفوظان بدقة مالية، وهامش الربح للعرض فقط.</p>
                </div>
                <span class="rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-black text-emerald-700">هامش الربح: <span data-profit-margin>0.00%</span></span>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
                <label class="grid gap-2 text-sm font-bold text-slate-700">
                    سعر الشراء
                    <input name="cost_price" data-cost-price type="number" min="0" step="0.01" value="{{ old('cost_price', $product?->cost_price ?? '0.00') }}" required @disabled(! $canEditPrice) class="rounded-xl border border-slate-200 px-3 py-3 outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10 disabled:bg-slate-100 disabled:text-slate-500">
                </label>
                <label class="grid gap-2 text-sm font-bold text-slate-700">
                    سعر البيع
                    <input name="price" data-sale-price type="number" min="0" step="0.01" value="{{ old('price', $product?->price ?? '0.00') }}" required @disabled(! $canEditPrice) class="rounded-xl border border-slate-200 px-3 py-3 outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10 disabled:bg-slate-100 disabled:text-slate-500">
                </label>
            </div>
            @if (! $canEditPrice)
                <p class="mt-3 text-xs font-bold text-amber-700">لا تملك صلاحية تعديل الأسعار.</p>
            @endif
        </section>

        <section data-inventory-fields class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6 {{ $currentType === 'stock' ? '' : 'hidden' }}">
            <div class="mb-5">
                <h2 class="font-black text-slate-950">المخزون</h2>
                <p class="mt-1 text-sm text-slate-500">المخزون مرتبط بالمستودع، وكل كمية افتتاحية تسجل كحركة مستقلة.</p>
            </div>
            <div class="grid gap-4 md:grid-cols-3">
                @if ($isEditing)
                    <div class="grid gap-2 text-sm font-bold text-slate-700">
                        المستودع
                        <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3">{{ $selectedWarehouse->name }}</div>
                        <input data-inventory-input type="hidden" name="warehouse_id" value="{{ $selectedWarehouse->id }}">
                    </div>
                    <div class="grid gap-2 text-sm font-bold text-slate-700">
                        الكمية الحالية
                        <div class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3">{{ $balance?->quantity_on_hand ?? '0.000' }}</div>
                        <p class="text-xs font-normal text-slate-500">تُعدّل من الجرد أو حركة المخزون.</p>
                    </div>
                @else
                    <label class="grid gap-2 text-sm font-bold text-slate-700">
                        المستودع
                        <select name="warehouse_id" data-inventory-input class="rounded-xl border border-slate-200 bg-white px-3 py-3 outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10">
                            @foreach ($warehouses as $warehouse)
                                <option value="{{ $warehouse->id }}" @selected($warehouseValue === $warehouse->id)>{{ $warehouse->name }}{{ $warehouse->is_default ? ' — افتراضي' : '' }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="grid gap-2 text-sm font-bold text-slate-700">
                        الكمية الافتتاحية
                        <input name="opening_quantity" data-inventory-input type="number" min="0" step="0.001" value="{{ old('opening_quantity', '0.000') }}" class="rounded-xl border border-slate-200 px-3 py-3 outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10">
                    </label>
                @endif
                <label class="grid gap-2 text-sm font-bold text-slate-700">
                    حد التنبيه
                    <input name="reorder_level" data-inventory-input type="number" min="0" step="0.001" value="{{ old('reorder_level', $balance?->reorder_level ?? '0.000') }}" class="rounded-xl border border-slate-200 px-3 py-3 outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10">
                </label>
            </div>
        </section>
    </div>

    <aside class="h-fit rounded-2xl border border-slate-200 bg-white p-5 shadow-sm xl:sticky xl:top-24">
        <h2 class="font-black text-slate-950">صورة المنتج</h2>
        <div class="mt-4 overflow-hidden rounded-2xl bg-slate-100">
            <img data-product-image-preview src="{{ $product?->image_url ?? asset('images/product-placeholder.svg') }}" alt="معاينة صورة المنتج" class="aspect-square w-full object-cover">
        </div>
        <label class="mt-4 grid cursor-pointer gap-2 text-sm font-bold text-slate-700">
            رفع صورة
            <input data-product-image-input name="image" type="file" accept="image/jpeg,image/png,image/webp" class="block w-full rounded-xl border border-slate-200 text-xs text-slate-600 file:ml-3 file:border-0 file:bg-brand-50 file:px-3 file:py-2.5 file:font-bold file:text-brand-700">
        </label>
        <p class="mt-2 text-xs leading-5 text-slate-500">JPG أو PNG أو WebP، بحد أقصى 5MB.</p>
    </aside>
</div>
