<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePurchaseOrderDetailRequest;
use App\Http\Requests\UpdatePurchaseOrderDetailRequest;
use App\Http\Resources\PurchaseOrderDetailResource;
use App\Models\PurchaseOrderDetail;
use App\Services\AuditLogService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class PurchaseOrderDetailController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $details = PurchaseOrderDetail::query()->latest('id')->paginate(15);

        return PurchaseOrderDetailResource::collection($details);
    }

    public function store(StorePurchaseOrderDetailRequest $request): PurchaseOrderDetailResource
    {
        return DB::transaction(function () use ($request): PurchaseOrderDetailResource {
            $detail = PurchaseOrderDetail::query()->create($request->validated());
            AuditLogService::created(PurchaseOrderDetail::class, $detail->id, $this->auditValues($detail));

            return new PurchaseOrderDetailResource($detail);
        });
    }

    public function show(PurchaseOrderDetail $purchaseOrderDetail): PurchaseOrderDetailResource
    {
        AuditLogService::viewed(PurchaseOrderDetail::class, $purchaseOrderDetail->id);

        return new PurchaseOrderDetailResource($purchaseOrderDetail);
    }

    public function update(
        UpdatePurchaseOrderDetailRequest $request,
        PurchaseOrderDetail $purchaseOrderDetail
    ): PurchaseOrderDetailResource {
        return DB::transaction(function () use ($request, $purchaseOrderDetail): PurchaseOrderDetailResource {
            $oldValues = $this->auditValues($purchaseOrderDetail);
            $purchaseOrderDetail->update($request->validated());
            $purchaseOrderDetail->refresh();
            AuditLogService::updated(
                PurchaseOrderDetail::class,
                $purchaseOrderDetail->id,
                $oldValues,
                $this->auditValues($purchaseOrderDetail),
            );

            return new PurchaseOrderDetailResource($purchaseOrderDetail);
        });
    }

    public function destroy(PurchaseOrderDetail $purchaseOrderDetail): Response
    {
        DB::transaction(function () use ($purchaseOrderDetail): void {
            AuditLogService::deleted(
                PurchaseOrderDetail::class,
                $purchaseOrderDetail->id,
                $this->auditValues($purchaseOrderDetail),
            );
            $purchaseOrderDetail->delete();
        });

        return response()->noContent();
    }

    /** @return array{purchase_order_id: string, product_id: string, quantity: string, cost_price: string} */
    private function auditValues(PurchaseOrderDetail $detail): array
    {
        return $detail->only(['purchase_order_id', 'product_id', 'quantity', 'cost_price']);
    }
}
