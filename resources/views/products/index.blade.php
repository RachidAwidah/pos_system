<x-layouts.app title="المنتجات" subtitle="مراقبة الأسعار والكميات وحالة المخزون">
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 p-5"><div><h2 class="font-black">قائمة المنتجات</h2><p class="mt-1 text-sm text-slate-500">{{ $products->total() }} منتج</p></div><span class="rounded-xl bg-slate-100 px-3 py-2 text-sm font-bold text-slate-600">البيانات الفعلية</span></div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[760px] text-sm">
                <thead class="bg-slate-50 text-right text-slate-500"><tr><th class="px-5 py-3">المنتج</th><th class="px-5 py-3">SKU / باركود</th><th class="px-5 py-3">الفئة</th><th class="px-5 py-3">التكلفة</th><th class="px-5 py-3">سعر البيع</th><th class="px-5 py-3">المخزون</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($products as $product)
                        <tr class="hover:bg-slate-50"><td class="px-5 py-4"><div class="flex items-center gap-3"><span class="grid size-10 place-items-center rounded-xl bg-brand-50 font-black text-brand-700">{{ str($product->product_name)->substr(0, 1) }}</span><span class="font-bold">{{ $product->product_name }}</span></div></td><td class="px-5 py-4 text-slate-500"><span class="block">{{ $product->sku }}</span><span class="text-xs">{{ $product->barcode ?? '—' }}</span></td><td class="px-5 py-4">{{ $product->category->category_name }}</td><td class="px-5 py-4">{{ number_format((float) $product->cost_price, 2) }} $</td><td class="px-5 py-4 font-black text-brand-700">{{ number_format((float) $product->price, 2) }} $</td><td class="px-5 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $product->type->tracksInventory() && $product->quantity <= $product->reorder_level ? 'bg-red-50 text-red-700' : 'bg-emerald-50 text-emerald-700' }}">{{ $product->type->tracksInventory() ? number_format((float) $product->quantity, $product->unit->decimal_places) : 'غير مخزني' }} {{ $product->unit->symbol }}</span></td></tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-14 text-center text-slate-500">لا توجد منتجات.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-100 p-4">{{ $products->links() }}</div>
    </div>
</x-layouts.app>
