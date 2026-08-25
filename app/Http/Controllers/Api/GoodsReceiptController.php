<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGoodsReceiptRequest;
use App\Http\Resources\GoodsReceiptResource;
use App\Models\PurchaseOrder;
use App\Services\GoodsReceiptService;

class GoodsReceiptController extends Controller
{
    public function __construct(public GoodsReceiptService $goodsReceiptService) {}

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
}
