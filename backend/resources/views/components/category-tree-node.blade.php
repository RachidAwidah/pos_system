@props(['category', 'categoryOptions'])

<li>
    <div class="group flex min-h-9 items-center gap-1 rounded-lg px-1.5 text-sm text-slate-100 hover:bg-white/10">
        @if ($category->children->isNotEmpty())
            <button type="button" data-tree-toggle aria-expanded="{{ $category->depth === 0 ? 'true' : 'false' }}" class="grid size-7 shrink-0 place-items-center rounded text-slate-400 hover:bg-white/10 hover:text-white">
                <svg data-tree-arrow viewBox="0 0 20 20" fill="currentColor" class="size-4 transition-transform {{ $category->depth === 0 ? 'rotate-90' : '' }}" aria-hidden="true">
                    <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L10.94 10 7.23 6.29a.75.75 0 0 1 1.06-1.06l4.24 4.24a.75.75 0 0 1 0 1.06l-4.24 4.24a.75.75 0 0 1-1.08 0Z" clip-rule="evenodd" />
                </svg>
                <span class="sr-only">فتح الفروع أو إغلاقها</span>
            </button>
        @else
            <span class="grid size-7 shrink-0 place-items-center text-brand-400">•</span>
        @endif

        <span class="min-w-0 flex-1 truncate font-bold" title="{{ $category->path }}">{{ $category->category_name }}</span>
        <span class="rounded bg-black/20 px-1.5 py-0.5 text-[11px] text-slate-400" title="عدد المنتجات ضمن هذا الفرع">{{ $category->tree_products_count }}</span>

        @if (auth()->user()->hasPermission('categories.edit'))
            <details class="relative">
                <summary class="grid size-7 cursor-pointer list-none place-items-center rounded text-slate-400 hover:bg-white/10 hover:text-white" title="تعديل">⋯</summary>
                <div class="absolute left-0 z-20 mt-1 w-80 rounded-xl border border-slate-200 bg-white p-4 text-slate-700 shadow-2xl">
                    <form method="POST" action="{{ route('categories.update', $category) }}" class="grid gap-3">
                        @csrf
                        @method('PUT')
                        <label class="grid gap-1.5 text-xs font-bold">
                            الاسم
                            <input name="category_name" value="{{ $category->category_name }}" required maxlength="255" class="rounded-lg border border-slate-200 px-3 py-2 outline-none focus:border-brand-500">
                        </label>
                        <label class="grid gap-1.5 text-xs font-bold">
                            التصنيف الأب
                            <select name="parent_id" class="rounded-lg border border-slate-200 bg-white px-3 py-2 outline-none focus:border-brand-500">
                                <option value="">تصنيف رئيسي</option>
                                @foreach ($categoryOptions as $option)
                                    @continue(in_array($category->id, $option->path_ids, true))
                                    <option value="{{ $option->id }}" @selected($category->parent_id === $option->id)>{{ $option->path }}</option>
                                @endforeach
                            </select>
                        </label>
                        <button class="rounded-lg bg-brand-600 px-3 py-2 text-xs font-black text-white hover:bg-brand-700">حفظ التعديل</button>
                    </form>

                    @if (auth()->user()->hasPermission('categories.delete'))
                        @if ($category->children_count === 0 && $category->products_count === 0)
                            <form method="POST" action="{{ route('categories.destroy', $category) }}" class="mt-3 border-t border-slate-100 pt-3" onsubmit="return confirm('هل تريد حذف هذا التصنيف؟')">
                                @csrf
                                @method('DELETE')
                                <button class="w-full rounded-lg border border-red-200 px-3 py-2 text-xs font-bold text-red-600 hover:bg-red-50">حذف التصنيف</button>
                            </form>
                        @else
                            <p class="mt-3 border-t border-slate-100 pt-3 text-xs text-slate-400">لا يمكن حذفه قبل نقل منتجاته وحذف فروعه.</p>
                        @endif
                    @endif
                </div>
            </details>
        @endif
    </div>

    @if ($category->children->isNotEmpty())
        <ul data-tree-children class="mr-4 grid gap-0.5 border-r border-white/10 pr-2 {{ $category->depth === 0 ? '' : 'hidden' }}">
            @foreach ($category->children as $child)
                <x-category-tree-node :category="$child" :category-options="$categoryOptions" />
            @endforeach
        </ul>
    @endif
</li>
