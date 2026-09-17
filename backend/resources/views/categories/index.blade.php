<x-layouts.app title="شجرة التصنيفات" subtitle="نظّم المنتجات ضمن تصنيفات رئيسية وفرعية متعددة المستويات">
    @if ($errors->any())
        <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-bold text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-[360px_minmax(0,1fr)]">
        <section class="order-2 h-fit rounded-2xl bg-slate-900 p-4 text-white shadow-xl xl:order-1 xl:sticky xl:top-24">
            <div class="flex items-center justify-between gap-3 border-b border-white/10 pb-4">
                <div>
                    <h2 class="font-black">هيكل التصنيفات</h2>
                    <p class="mt-1 text-xs text-slate-400">اضغط على السهم لفتح الفروع أو إغلاقها</p>
                </div>
                <span class="rounded-lg bg-white/10 px-2.5 py-1.5 text-xs font-bold">{{ $categoryOptions->count() }} تصنيف</span>
            </div>

            <div class="mt-4 max-h-[70vh] overflow-y-auto">
                @forelse ($categoryTree as $category)
                    <ul class="grid gap-0.5">
                        <x-category-tree-node :category="$category" :category-options="$categoryOptions" />
                    </ul>
                @empty
                    <div class="rounded-xl border border-dashed border-white/20 p-8 text-center text-sm text-slate-400">
                        لا توجد تصنيفات بعد.
                    </div>
                @endforelse
            </div>
        </section>

        <section class="order-1 min-w-0 xl:order-2">
            @if (auth()->user()->hasPermission('categories.create'))
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-lg font-black text-slate-950">إضافة تصنيف إلى الشجرة</h2>
                    <p class="mt-1 text-sm text-slate-500">اترك التصنيف الأب فارغاً لإنشاء جذر جديد.</p>

                    <form method="POST" action="{{ route('categories.store') }}" class="mt-5 grid gap-4">
                        @csrf
                        <label class="grid gap-2 text-sm font-bold text-slate-700">
                            اسم التصنيف
                            <input name="category_name" value="{{ old('category_name') }}" required maxlength="255" class="rounded-xl border border-slate-200 px-3 py-3 outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10">
                        </label>
                        <label class="grid gap-2 text-sm font-bold text-slate-700">
                            التصنيف الأب
                            <select name="parent_id" class="rounded-xl border border-slate-200 bg-white px-3 py-3 outline-none focus:border-brand-500 focus:ring-4 focus:ring-brand-500/10">
                                <option value="">تصنيف رئيسي</option>
                                @foreach ($categoryOptions as $option)
                                    <option value="{{ $option->id }}" @selected(old('parent_id') === $option->id)>{{ $option->path }}</option>
                                @endforeach
                            </select>
                        </label>
                        <button class="rounded-xl bg-brand-600 px-5 py-3 font-black text-white hover:bg-brand-700">إضافة التصنيف</button>
                    </form>
                </div>
            @endif

            <div class="mt-5 rounded-2xl border border-slate-200 bg-white p-5 text-sm text-slate-600 shadow-sm">
                <h3 class="font-black text-slate-900">طريقة التنظيم</h3>
                <p class="mt-2 leading-7">يمكن أن يحتوي أي تصنيف على عدد غير محدود من الفروع. العدد الظاهر بجانب العقدة يشمل منتجاتها ومنتجات جميع الفروع التابعة لها.</p>
            </div>
        </section>
    </div>
</x-layouts.app>
