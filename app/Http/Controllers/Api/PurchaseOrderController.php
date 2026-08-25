<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CancelPurchaseOrderRequest;
use App\Http\Requests\StorePurchaseOrderRequest;
use App\Http\Resources\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\AuditLogService;
use App\Services\PurchaseOrderService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PurchaseOrderController extends Controller
{
    public function __construct(public PurchaseOrderService $purchaseOrderService) {}

    public function index(): AnonymousResourceCollection
    {
        return PurchaseOrderResource::collection(PurchaseOrder::query()->with(['supplier', 'warehouse'])->latest()->paginate(20));
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

        return new PurchaseOrderResource($purchaseOrder->load(['items.product', 'supplier', 'warehouse', 'goodsReceipts.items']));
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
