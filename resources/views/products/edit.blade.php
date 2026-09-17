<div>
    <!-- Waste no more time arguing what a good man should be, be one. - Marcus Aurelius -->
</div>
<x-layouts.app title="تعديل المنتج" subtitle="حدّث بيانات {{ $product->product_name }} بدون تغيير سجل حركات المخزون">
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('products.index') }}" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50">العودة للمنتجات</a>
        @if ($product->barcode)
            <form method="POST" action="{{ route('products.fetch-image', $product) }}">
                @csrf
                <button class="rounded-xl border border-brand-200 bg-brand-50 px-4 py-2.5 text-sm font-black text-brand-700 hover:bg-brand-100">جلب الصورة بالباركود</button>
            </form>
        @endif
    </div>

    <form method="POST" action="{{ route('products.update', $product) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        <x-products.form
            :categories="$categories"
            :units="$units"
            :taxes="$taxes"
            :warehouses="$warehouses"
            :default-warehouse="$defaultWarehouse"
            :product-types="$productTypes"
            :product="$product"
            :balance="$balance"
            :selected-warehouse="$selectedWarehouse"
        />

        <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <a href="{{ route('products.index') }}" class="rounded-xl border border-slate-300 px-5 py-3 text-center text-sm font-bold text-slate-700 hover:bg-slate-50">إلغاء</a>
            <button class="rounded-xl bg-brand-600 px-6 py-3 text-sm font-black text-white shadow-lg shadow-brand-500/20 hover:bg-brand-700">حفظ التغييرات</button>
        </div>
    </form>
</x-layouts.app>
