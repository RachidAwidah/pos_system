<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CancelInventoryCountRequest;
use App\Http\Requests\InventoryCountIndexRequest;
use App\Http\Requests\ReviewInventoryCountRequest;
use App\Http\Requests\StoreInventoryCountRequest;
use App\Http\Requests\UpdateInventoryCountItemsRequest;
use App\Http\Resources\InventoryCountResource;
use App\Models\InventoryCount;
use App\Models\Warehouse;
use App\Services\AuditLogService;
use App\Services\InventoryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InventoryCountController extends Controller
{
    public function __construct(public InventoryService $inventoryService) {}

    public function index(InventoryCountIndexRequest $request): AnonymousResourceCollection
    {
        $validated = $request->validated();
        $inventoryCounts = InventoryCount::query()
            ->with(['warehouse:id,name,code', 'startedBy:id,full_name', 'approvedBy:id,full_name'])
            ->withCount('items')
            ->when($validated['warehouse_id'] ?? null, fn (Builder $query, string $warehouseId) => $query->where('warehouse_id', $warehouseId))
            ->when($validated['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($validated['from'] ?? null, fn (Builder $query, string $from) => $query->whereDate('created_at', '>=', $from))
            ->when($validated['to'] ?? null, fn (Builder $query, string $to) => $query->whereDate('created_at', '<=', $to))
            ->latest()
            ->paginate($request->integer('per_page', 20))
            ->withQueryString();

        return InventoryCountResource::collection($inventoryCounts);
    }

    public function store(StoreInventoryCountRequest $request): InventoryCountResource
    {
        $validated = $request->validated();

        return new InventoryCountResource($this->inventoryService->startInventoryCount(
            Warehouse::query()->findOrFail($validated['warehouse_id']),
            $request->user(),
            $validated['product_ids'] ?? [],
            $validated['notes'] ?? null,
        ));
    }

    public function show(InventoryCount $inventoryCount): InventoryCountResource
    {
        AuditLogService::viewed(InventoryCount::class, $inventoryCount->id);

        return new InventoryCountResource($inventoryCount->load([
            'warehouse',
            'startedBy',
            'approvedBy',
            'items.product.unit',
        ])->loadCount('items'));
    }

    public function updateItems(
        UpdateInventoryCountItemsRequest $request,
        InventoryCount $inventoryCount,
    ): InventoryCountResource {
        return new InventoryCountResource($this->inventoryService->recordInventoryCount(
            $inventoryCount,
            $request->validated('items'),
        ));
    }

    public function review(
        ReviewInventoryCountRequest $request,
        InventoryCount $inventoryCount,
    ): InventoryCountResource {
        return new InventoryCountResource($this->inventoryService->reviewInventoryCount($inventoryCount));
    }

    public function apply(
        ReviewInventoryCountRequest $request,
        InventoryCount $inventoryCount,
    ): InventoryCountResource {
        $appliedCount = $this->inventoryService->applyInventoryCount($inventoryCount, $request->user());

        return new InventoryCountResource($appliedCount->load([
            'warehouse',
            'startedBy',
            'approvedBy',
            'items.product',
        ])->loadCount('items'));
    }

    public function cancel(
        CancelInventoryCountRequest $request,
        InventoryCount $inventoryCount,
    ): InventoryCountResource {
        return new InventoryCountResource($this->inventoryService->cancelInventoryCount(
            $inventoryCount,
            (string) $request->string('reason'),
        ));
    }
}
