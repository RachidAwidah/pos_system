<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GoodsReceiptIndexRequest;
use App\Http\Requests\StoreGoodsReceiptRequest;
use App\Http\Resources\GoodsReceiptResource;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Services\AuditLogService;
use App\Services\GoodsReceiptService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GoodsReceiptController extends Controller
{
    public function __construct(public GoodsReceiptService $goodsReceiptService) {}

    public function index(GoodsReceiptIndexRequest $request): AnonymousResourceCollection
    {
        $validated = $request->validated();
        $goodsReceipts = GoodsReceipt::query()
            ->with(['purchaseOrder:id,purchase_order_number,supplier_id', 'warehouse:id,name,code', 'receivedBy:id,full_name'])
            ->withCount('items')
            ->when($validated['search'] ?? null, fn (Builder $query, string $search) => $query->where(function (Builder $searchQuery) use ($search): void {
                $searchQuery->where('receipt_number', 'like', "%{$search}%")
                    ->orWhere('supplier_reference', 'like', "%{$search}%")
                    ->orWhereHas('purchaseOrder', fn (Builder $purchaseOrderQuery) => $purchaseOrderQuery
                        ->where('purchase_order_number', 'like', "%{$search}%"));
            }))
            ->when($validated['purchase_order_id'] ?? null, fn (Builder $query, string $purchaseOrderId) => $query->where('purchase_order_id', $purchaseOrderId))
            ->when($validated['supplier_id'] ?? null, fn (Builder $query, string $supplierId) => $query->whereHas('purchaseOrder', fn (Builder $purchaseOrderQuery) => $purchaseOrderQuery->where('supplier_id', $supplierId)))
            ->when($validated['warehouse_id'] ?? null, fn (Builder $query, string $warehouseId) => $query->where('warehouse_id', $warehouseId))
            ->when($validated['from'] ?? null, fn (Builder $query, string $from) => $query->whereDate('received_at', '>=', $from))
            ->when($validated['to'] ?? null, fn (Builder $query, string $to) => $query->whereDate('received_at', '<=', $to))
            ->latest('received_at')
            ->paginate($request->integer('per_page', 20))
            ->withQueryString();

        return GoodsReceiptResource::collection($goodsReceipts);
    }

    public function store(StoreGoodsReceiptRequest $request, PurchaseOrder $purchaseOrder): GoodsReceiptResource
    {
        $validated = $request->validated();

        return new GoodsReceiptResource($this->goodsReceiptService->receive(
            $purchaseOrder,
            $request->user(),
            $validated['items'],
            $validated['supplier_reference'] ?? null,
            $validated['notes'] ?? null,
        ));
    }

    public function show(GoodsReceipt $goodsReceipt): GoodsReceiptResource
    {
        AuditLogService::viewed(GoodsReceipt::class, $goodsReceipt->id);

        return new GoodsReceiptResource($goodsReceipt->load([
            'purchaseOrder',
            'warehouse',
            'receivedBy',
            'items.product',
        ])->loadCount('items'));
    }
}
