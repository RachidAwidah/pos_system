<div>
    <!-- Smile, breathe, and go slowly. - Thich Nhat Hanh -->
</div>
<x-layouts.app title="إضافة منتج" subtitle="أنشئ منتجاً جديداً وحدد بياناته ومخزونه الافتتاحي">
    <form method="POST" action="{{ route('products.store') }}" enctype="multipart/form-data">
        @csrf
        <x-products.form
            :categories="$categories"
            :units="$units"
            :taxes="$taxes"
            :warehouses="$warehouses"
            :default-warehouse="$defaultWarehouse"
            :product-types="$productTypes"
        />

        <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <a href="{{ route('products.index') }}" class="rounded-xl border border-slate-300 px-5 py-3 text-center text-sm font-bold text-slate-700 hover:bg-slate-50">إلغاء</a>
            <button class="rounded-xl bg-brand-600 px-6 py-3 text-sm font-black text-white shadow-lg shadow-brand-500/20 hover:bg-brand-700">حفظ المنتج</button>
        </div>
    </form>
</x-layouts.app>
