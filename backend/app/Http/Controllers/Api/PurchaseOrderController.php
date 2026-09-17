<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CancelPurchaseOrderRequest;
use App\Http\Requests\PurchaseOrderIndexRequest;
use App\Http\Requests\StorePurchaseOrderRequest;
use App\Http\Requests\UpdatePurchaseOrderRequest;
use App\Http\Resources\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\AuditLogService;
use App\Services\PurchaseOrderService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class PurchaseOrderController extends Controller
{
    public function __construct(public PurchaseOrderService $purchaseOrderService) {}

    public function index(PurchaseOrderIndexRequest $request): AnonymousResourceCollection
    {
        $validated = $request->validated();
        $query = PurchaseOrder::query()
            ->with(['supplier:id,name,company_name,balance', 'warehouse:id,name,code', 'user:id,full_name'])
            ->withCount('items')
            ->when($validated['search'] ?? null, fn (Builder $query, string $search) => $query->where(function (Builder $searchQuery) use ($search): void {
                $searchQuery->where('purchase_order_number', 'like', "%{$search}%")
                    ->orWhereHas('supplier', fn (Builder $supplierQuery) => $supplierQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('company_name', 'like', "%{$search}%"));
            }))
            ->when($validated['supplier_id'] ?? null, fn (Builder $query, string $supplierId) => $query->where('supplier_id', $supplierId))
            ->when($validated['warehouse_id'] ?? null, fn (Builder $query, string $warehouseId) => $query->where('warehouse_id', $warehouseId))
            ->when($validated['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($validated['from'] ?? null, fn (Builder $query, string $from) => $query->whereDate('created_at', '>=', $from))
            ->when($validated['to'] ?? null, fn (Builder $query, string $to) => $query->whereDate('created_at', '<=', $to))
            ->latest();

        if ($validated['all'] ?? false) {
            return PurchaseOrderResource::collection($query->get());
        }

        return PurchaseOrderResource::collection($query->paginate($request->integer('per_page', 20))->withQueryString());
    }

    public function store(StorePurchaseOrderRequest $request): PurchaseOrderResource
    {
        $validated = $request->validated();

        return new PurchaseOrderResource($this->purchaseOrderService->createDraft(
            $request->user(),
            Supplier::query()->findOrFail($validated['supplier_id']),
            Warehouse::query()->findOrFail($validated['warehouse_id']),
            $validated['items'],
            $validated['expected_at'] ?? null,
            $validated['notes'] ?? null,
        ));
    }

    public function show(PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        AuditLogService::viewed(PurchaseOrder::class, $purchaseOrder->id);

        return new PurchaseOrderResource($purchaseOrder->load([
            'items.product',
            'supplier',
            'warehouse',
            'user',
            'goodsReceipts.items.product',
            'goodsReceipts.warehouse',
            'goodsReceipts.receivedBy',
        ])->loadCount('items'));
    }

    public function update(UpdatePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        $validated = $request->validated();

        return new PurchaseOrderResource($this->purchaseOrderService->updateDraft(
            $purchaseOrder,
            Supplier::query()->findOrFail($validated['supplier_id']),
            Warehouse::query()->findOrFail($validated['warehouse_id']),
            $validated['items'],
            $validated['expected_at'] ?? null,
            $validated['notes'] ?? null,
        ));
    }

    public function destroy(PurchaseOrder $purchaseOrder): Response
    {
        $this->purchaseOrderService->deleteDraft($purchaseOrder);

        return response()->noContent();
    }

    public function send(PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        return new PurchaseOrderResource($this->purchaseOrderService->send($purchaseOrder));
    }

    public function cancel(CancelPurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): PurchaseOrderResource
    {
        return new PurchaseOrderResource($this->purchaseOrderService->cancel($purchaseOrder, (string) $request->string('reason')));
    }
}
