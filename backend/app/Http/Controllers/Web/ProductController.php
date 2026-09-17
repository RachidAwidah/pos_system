<?php

namespace App\Http\Controllers\Web;

use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProductIndexRequest;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use App\Models\Tax;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Services\AuditLogService;
use App\Services\CatalogManagementService;
use App\Services\CategoryTreeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(ProductIndexRequest $request, CategoryTreeService $categoryTree): View
    {
        $filters = $request->validated();
        $warehouse = isset($filters['warehouse_id'])
            ? Warehouse::query()->active()->findOrFail($filters['warehouse_id'])
            : Warehouse::query()->active()->defaultWarehouse()->firstOrFail();
        $tree = $categoryTree->tree();
        $categoryOptions = $categoryTree->flatten($tree);
        $categoryIds = isset($filters['category_id'])
            ? $categoryTree->descendantIds($filters['category_id'], $tree)
            : [];
        $search = trim((string) ($filters['search'] ?? ''));

        $products = Product::query()
            ->with([
                'category:id,category_name,parent_id',
                'tax:id,tax_percentage',
                'unit:id,symbol,decimal_places',
                'inventoryBalances' => fn ($query) => $query->whereBelongsTo($warehouse),
            ])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery->where('sku', $search)
                        ->orWhere('barcode', $search)
                        ->orWhere('product_name', 'like', '%'.$search.'%')
                        ->orWhere('sku', 'like', $search.'%');
                });
            })
            ->when($categoryIds !== [], fn (Builder $query) => $query->whereIn('category_id', $categoryIds))
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('type', $type))
            ->when($filters['low_stock'] ?? false, fn (Builder $query) => $query->whereHas(
                'inventoryBalances',
                fn (Builder $balance) => $balance
                    ->whereBelongsTo($warehouse)
                    ->whereColumn('quantity_on_hand', '<=', 'reorder_level'),
            ))
            ->orderBy('product_name')
            ->paginate(20)
            ->withQueryString();

        $products->through(function (Product $product): Product {
            $balance = $product->inventoryBalances->first();
            $product->setAttribute('quantity', $balance?->quantity_on_hand ?? '0.000');
            $product->setAttribute('reorder_level', $balance?->reorder_level ?? '0.000');

            return $product;
        });

        $selectedCategory = isset($filters['category_id'])
            ? $categoryOptions->firstWhere('id', $filters['category_id'])
            : null;

        return view('products.index', [
            'products' => $products,
            'categoryTree' => $tree,
            'selectedCategory' => $selectedCategory,
            'warehouses' => Warehouse::query()->active()->orderByDesc('is_default')->orderBy('name')->get(),
            'warehouse' => $warehouse,
            'filters' => $filters,
            'productTypes' => ProductType::cases(),
        ]);
    }

    public function create(CategoryTreeService $categoryTree): View
    {
        return view('products.create', $this->formData($categoryTree));
    }

    public function store(StoreProductRequest $request, CatalogManagementService $catalog): RedirectResponse
    {
        $product = $catalog->createProduct($request->validated(), $request->user());

        return redirect()->route('products.edit', $product)->with('status', 'تم إنشاء المنتج والمخزون الافتتاحي بنجاح.');
    }

    public function edit(Product $product, CategoryTreeService $categoryTree): View
    {
        AuditLogService::viewed(Product::class, $product->id);

        $warehouse = Warehouse::query()->active()->defaultWarehouse()->firstOrFail();
        $product->load([
            'category',
            'unit',
            'tax',
            'inventoryBalances' => fn ($query) => $query->whereBelongsTo($warehouse),
        ]);

        return view('products.edit', [
            ...$this->formData($categoryTree),
            'product' => $product,
            'balance' => $product->inventoryBalances->first(),
            'selectedWarehouse' => $warehouse,
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product, CatalogManagementService $catalog): RedirectResponse
    {
        $catalog->updateProduct($product, $request->validated());

        return redirect()->route('products.edit', $product)->with('status', 'تم تحديث المنتج بنجاح.');
    }

    public function fetchImage(Product $product, CatalogManagementService $catalog): RedirectResponse
    {
        $catalog->fetchProductImage($product);

        return redirect()->route('products.edit', $product)->with('status', 'تم جلب صورة المنتج وحفظها بنجاح.');
    }

    public function destroy(Product $product, CatalogManagementService $catalog): RedirectResponse
    {
        $catalog->deleteProduct($product);

        return redirect()->route('products.index')->with('status', 'تمت أرشفة المنتج بنجاح.');
    }

    /** @return array<string, mixed> */
    private function formData(CategoryTreeService $categoryTree): array
    {
        return [
            'categories' => $categoryTree->flatten($categoryTree->tree()),
            'units' => Unit::query()->orderBy('name')->get(),
            'taxes' => Tax::query()->orderBy('tax_name')->get(),
            'warehouses' => Warehouse::query()->active()->orderByDesc('is_default')->orderBy('name')->get(),
            'productTypes' => ProductType::cases(),
            'defaultWarehouse' => Warehouse::query()->active()->defaultWarehouse()->firstOrFail(),
        ];
    }
}
