<div>
    <!-- Live as if you were to die tomorrow. Learn as if you were to live forever. - Mahatma Gandhi -->
</div>
@props(['category', 'filters', 'selectedCategoryId' => null, 'selectedPathIds' => []])

@php
    $isSelected = $selectedCategoryId === $category->id;
    $isExpanded = $category->depth === 0 || in_array($category->id, $selectedPathIds, true);
    $categoryUrl = route('products.index', array_merge(
        collect($filters)->except(['category_id', 'page'])->all(),
        ['category_id' => $category->id],
    ));
@endphp

<li>
    <div class="flex items-center gap-1 rounded-lg {{ $isSelected ? 'bg-brand-500 text-white' : 'text-slate-200 hover:bg-white/10' }}">
        @if ($category->children->isNotEmpty())
            <button type="button" data-tree-toggle aria-expanded="{{ $isExpanded ? 'true' : 'false' }}" class="grid size-8 shrink-0 place-items-center rounded-lg text-slate-400 hover:text-white" aria-label="فتح أو إغلاق {{ $category->category_name }}">
                <span data-tree-arrow class="text-xs transition-transform {{ $isExpanded ? 'rotate-90' : '' }}">▶</span>
            </button>
        @else
            <span class="block size-8 shrink-0"></span>
        @endif

        <a href="{{ $categoryUrl }}" class="flex min-w-0 flex-1 items-center justify-between gap-2 py-2 pl-2 text-sm font-bold">
            <span class="truncate">{{ $category->category_name }}</span>
            <span class="rounded bg-black/20 px-1.5 py-0.5 text-[11px]">{{ $category->tree_products_count }}</span>
        </a>
    </div>

    @if ($category->children->isNotEmpty())
        <ul data-tree-children class="mr-4 grid gap-0.5 border-r border-white/10 pr-2 {{ $isExpanded ? '' : 'hidden' }}">
            @foreach ($category->children as $child)
                <x-products.category-tree-filter
                    :category="$child"
                    :filters="$filters"
                    :selected-category-id="$selectedCategoryId"
                    :selected-path-ids="$selectedPathIds"
                />
            @endforeach
        </ul>
    @endif
</li>
