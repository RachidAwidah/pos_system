<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSupplierPaymentRequest;
use App\Http\Resources\SupplierPaymentResource;
use App\Models\PaymentMethod;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\SupplierAccountService;

class SupplierPaymentController extends Controller
{
    public function __construct(public SupplierAccountService $supplierAccountService) {}

    public function store(StoreSupplierPaymentRequest $request, Supplier $supplier): SupplierPaymentResource
    {
        $validated = $request->validated();

        return new SupplierPaymentResource($this->supplierAccountService->pay(
            $supplier,
            $request->user(),
            PaymentMethod::query()->findOrFail($validated['payment_method_id']),
            (string) $validated['amount'],
            isset($validated['purchase_order_id']) ? PurchaseOrder::query()->findOrFail($validated['purchase_order_id']) : null,
            $validated['reference_number'] ?? null,
            $validated['notes'] ?? null,
        ));
    }
}
