<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductIndexRequest;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\Tax;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Services\AuditLogService;
use App\Services\CatalogManagementService;
use App\Services\CategoryTreeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ProductController extends Controller
{
    public function index(ProductIndexRequest $request, CategoryTreeService $categoryTree): AnonymousResourceCollection
    {
        $filters = $request->validated();
        $warehouse = isset($filters['warehouse_id'])
            ? Warehouse::query()->active()->findOrFail($filters['warehouse_id'])
            : Warehouse::query()->active()->defaultWarehouse()->firstOrFail();
        $search = trim((string) ($filters['search'] ?? ''));
        $categoryIds = isset($filters['category_id'])
            ? $categoryTree->descendantIds($filters['category_id'])
            : [];

        $query = Product::query()
            ->with([
                'category:id,category_name,parent_id',
                'unit:id,name,symbol,decimal_places',
                'tax:id,tax_name,tax_percentage',
                'inventoryBalances' => fn ($query) => $query->with('warehouse:id,name'),
            ])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery->where('sku', $search)
                        ->orWhere('barcode', $search)
                        ->orWhere('product_name', 'like', '%'.$search.'%')
                        ->orWhere('sku', 'like', $search.'%');
                })->orderByRaw('CASE WHEN sku = ? OR barcode = ? THEN 0 ELSE 1 END', [$search, $search]);
            })
            ->when($categoryIds !== [], fn (Builder $query) => $query->whereIn('category_id', $categoryIds))
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('type', $type))
            ->when($filters['low_stock'] ?? false, fn (Builder $query) => $query->whereHas('inventoryBalances', fn (Builder $balance) => $balance->whereBelongsTo($warehouse)->whereColumn('quantity_on_hand', '<=', 'reorder_level')))
            ->when($search === '', fn (Builder $query) => $query->orderBy('product_name'));

        if ($filters['all'] ?? false) {
            return ProductResource::collection($query->get());
        }

        return ProductResource::collection($query->paginate((int) ($filters['per_page'] ?? 25))->withQueryString());
    }

    public function referenceData(CategoryTreeService $categoryTree): JsonResponse
    {
        return response()->json(['data' => [
            'categories' => $categoryTree->flatten($categoryTree->tree())->values(),
            'units' => Unit::query()->orderBy('name')->get(['id', 'name', 'symbol', 'decimal_places']),
            'taxes' => Tax::query()->orderBy('tax_name')->get(['id', 'tax_name', 'tax_percentage']),
            'warehouses' => Warehouse::query()->active()->orderByDesc('is_default')->orderBy('name')->get(['id', 'name', 'code']),
        ]]);
    }

    public function store(StoreProductRequest $request, CatalogManagementService $catalog): JsonResponse
    {
        return (new ProductResource($catalog->createProduct($request->validated(), $request->user())))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Product $product): ProductResource
    {
        $product->load(['category', 'unit', 'tax', 'inventoryBalances.warehouse']);
        AuditLogService::viewed(Product::class, $product->id);

        return new ProductResource($product);
    }

    public function update(UpdateProductRequest $request, Product $product, CatalogManagementService $catalog): ProductResource
    {
        return new ProductResource($catalog->updateProduct($product, $request->validated()));
    }

    public function fetchImage(Product $product, CatalogManagementService $catalog): ProductResource
    {
        return new ProductResource($catalog->fetchProductImage($product));
    }

    public function destroy(Product $product, CatalogManagementService $catalog): Response
    {
        $catalog->deleteProduct($product);

        return response()->noContent();
    }
}
