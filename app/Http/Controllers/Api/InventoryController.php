<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\InventoryAdjustmentRequest;
use App\Http\Requests\InventoryDamageRequest;
use App\Http\Requests\InventoryIndexRequest;
use App\Http\Requests\InventoryTransferRequest;
use App\Http\Requests\StockMovementIndexRequest;
use App\Http\Requests\UpdateReorderLevelRequest;
use App\Http\Resources\InventoryBalanceResource;
use App\Http\Resources\StockMovementResource;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InventoryController extends Controller
{
    public function __construct(public InventoryService $inventoryService) {}

    public function index(InventoryIndexRequest $request): AnonymousResourceCollection
    {
        $query = $this->balanceQuery($request->validated())
            ->latest('updated_at');

        if ($request->validated('all')) {
            return InventoryBalanceResource::collection($query->get());
        }

        return InventoryBalanceResource::collection($query->paginate($request->integer('per_page', 20))->withQueryString());
    }

    public function lowStock(InventoryIndexRequest $request): AnonymousResourceCollection
    {
        return InventoryBalanceResource::collection(
            $this->balanceQuery($request->validated(), lowStockOnly: true)
                ->orderBy('quantity_on_hand')
                ->paginate($request->integer('per_page', 20))
                ->withQueryString(),
        );
    }

    public function movements(StockMovementIndexRequest $request): AnonymousResourceCollection
    {
        $validated = $request->validated();
        $movements = StockMovement::query()
            ->with(['product:id,product_name,sku,barcode', 'warehouse:id,name,code', 'user:id,full_name'])
            ->when($validated['warehouse_id'] ?? null, fn (Builder $query, string $warehouseId) => $query->where('warehouse_id', $warehouseId))
            ->when($validated['product_id'] ?? null, fn (Builder $query, string $productId) => $query->where('product_id', $productId))
            ->when($validated['user_id'] ?? null, fn (Builder $query, string $userId) => $query->where('user_id', $userId))
            ->when($validated['movement_type'] ?? null, fn (Builder $query, string $type) => $query->where('movement_type', $type))
            ->when($validated['from'] ?? null, fn (Builder $query, string $from) => $query->whereDate('occurred_at', '>=', $from))
            ->when($validated['to'] ?? null, fn (Builder $query, string $to) => $query->whereDate('occurred_at', '<=', $to))
            ->when($validated['search'] ?? null, fn (Builder $query, string $search) => $query->whereHas('product', fn (Builder $productQuery) => $productQuery
                ->where('product_name', 'like', "%{$search}%")
                ->orWhere('sku', 'like', "%{$search}%")
                ->orWhere('barcode', $search)))
            ->latest('occurred_at')
            ->paginate($request->integer('per_page', 20))
            ->withQueryString();

        return StockMovementResource::collection($movements);
    }

    public function adjust(InventoryAdjustmentRequest $request): StockMovementResource
    {
        $validated = $request->validated();
        $movement = $this->inventoryService->adjustTo(
            Product::query()->findOrFail($validated['product_id']),
            Warehouse::query()->findOrFail($validated['warehouse_id']),
            (string) $validated['counted_quantity'],
            $request->user(),
            notes: $validated['notes'],
        );

        return new StockMovementResource($movement->load(['product', 'warehouse', 'user']));
    }

    public function recordDamage(InventoryDamageRequest $request): StockMovementResource
    {
        $validated = $request->validated();
        $movement = $this->inventoryService->recordDamage(
            Product::query()->findOrFail($validated['product_id']),
            Warehouse::query()->findOrFail($validated['warehouse_id']),
            (string) $validated['quantity'],
            $request->user(),
            $validated['notes'],
        );

        return new StockMovementResource($movement->load(['product', 'warehouse', 'user']));
    }

    public function transfer(InventoryTransferRequest $request): AnonymousResourceCollection
    {
        $validated = $request->validated();
        $movements = $this->inventoryService->transfer(
            Product::query()->findOrFail($validated['product_id']),
            Warehouse::query()->findOrFail($validated['source_warehouse_id']),
            Warehouse::query()->findOrFail($validated['destination_warehouse_id']),
            (string) $validated['quantity'],
            $request->user(),
            $validated['notes'],
        );

        return StockMovementResource::collection(
            collect($movements)->map->load(['product', 'warehouse', 'user']),
        );
    }

    public function updateReorderLevel(
        UpdateReorderLevelRequest $request,
        InventoryBalance $inventoryBalance,
    ): InventoryBalanceResource {
        $inventoryBalance->loadMissing(['product', 'warehouse']);
        $balance = $this->inventoryService->setReorderLevel(
            $inventoryBalance->product,
            $inventoryBalance->warehouse,
            (string) $request->validated('reorder_level'),
        );

        return new InventoryBalanceResource($balance->load(['product.unit', 'product.category', 'warehouse']));
    }

    /** @param array<string, mixed> $filters */
    private function balanceQuery(array $filters, bool $lowStockOnly = false): Builder
    {
        return InventoryBalance::query()
            ->with(['product.unit', 'product.category', 'warehouse:id,name,code,is_active'])
            ->when($filters['warehouse_id'] ?? null, fn (Builder $query, string $warehouseId) => $query->where('warehouse_id', $warehouseId))
            ->when($filters['category_id'] ?? null, fn (Builder $query, string $categoryId) => $query->whereHas('product', fn (Builder $productQuery) => $productQuery->where('category_id', $categoryId)))
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->whereHas('product', fn (Builder $productQuery) => $productQuery
                ->where('product_name', 'like', "%{$search}%")
                ->orWhere('sku', 'like', "%{$search}%")
                ->orWhere('barcode', $search)))
            ->when(
                $lowStockOnly || filter_var($filters['low_stock'] ?? false, FILTER_VALIDATE_BOOL),
                fn (Builder $query) => $query->whereRaw('(quantity_on_hand - quantity_reserved) <= reorder_level'),
            );
    }
}
