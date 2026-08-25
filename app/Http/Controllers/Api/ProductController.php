<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductIndexRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    public function index(ProductIndexRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();
        $warehouse = isset($filters['warehouse_id'])
            ? Warehouse::query()->active()->findOrFail($filters['warehouse_id'])
            : Warehouse::query()->active()->defaultWarehouse()->firstOrFail();
        $search = trim((string) ($filters['search'] ?? ''));

        $products = Product::query()
            ->with([
                'category:id,category_name,parent_id',
                'unit:id,name,symbol,decimal_places',
                'tax:id,tax_name,tax_percentage',
                'inventoryBalances' => fn ($query) => $query->whereBelongsTo($warehouse),
            ])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery->where('sku', $search)
                        ->orWhere('barcode', $search)
                        ->orWhere('product_name', 'like', '%'.$search.'%')
                        ->orWhere('sku', 'like', $search.'%');
                })->orderByRaw('CASE WHEN sku = ? OR barcode = ? THEN 0 ELSE 1 END', [$search, $search]);
            })
            ->when($filters['category_id'] ?? null, fn (Builder $query, string $categoryId) => $query->where('category_id', $categoryId))
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('type', $type))
            ->when($filters['low_stock'] ?? false, fn (Builder $query) => $query->whereHas('inventoryBalances', fn (Builder $balance) => $balance->whereBelongsTo($warehouse)->whereColumn('quantity_on_hand', '<=', 'reorder_level')))
            ->when($search === '', fn (Builder $query) => $query->orderBy('product_name'))
            ->paginate((int) ($filters['per_page'] ?? 25))
            ->withQueryString();

        return ProductResource::collection($products);
    }
}
