<?php

namespace App\Http\Controllers\Api;

use App\Enums\PurchaseOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\PartyIndexRequest;
use App\Http\Requests\StoreSupplierRequest;
use App\Http\Requests\SupplierAccountIndexRequest;
use App\Http\Requests\UpdateSupplierRequest;
use App\Http\Resources\SupplierLedgerEntryResource;
use App\Http\Resources\SupplierPaymentResource;
use App\Http\Resources\SupplierProductResource;
use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use App\Models\SupplierLedgerEntry;
use App\Models\SupplierPayment;
use App\Services\AuditLogService;
use App\Services\PartyManagementService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class SupplierController extends Controller
{
    public function products(PartyIndexRequest $request, Supplier $supplier): AnonymousResourceCollection
    {
        $filters = $request->validated();
        $search = trim((string) ($filters['search'] ?? ''));
        $links = $supplier->supplierProducts()->with('product:id,product_name,sku')
            ->whereHas('product')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $matches) use ($search): void {
                    $matches->where('supplier_sku', 'like', '%'.$search.'%')
                        ->orWhereHas('product', fn (Builder $products) => $products->where('product_name', 'like', '%'.$search.'%')->orWhere('sku', 'like', '%'.$search.'%'));
                });
            })
            ->orderBy('id')->paginate((int) ($filters['per_page'] ?? 25))->appends($filters);

        return SupplierProductResource::collection($links);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(PartyIndexRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();
        $search = trim((string) ($filters['search'] ?? ''));

        $query = Supplier::query()
            ->withSum([
                'purchaseOrders as purchases_total' => fn (Builder $orders) => $orders->where('status', '!=', PurchaseOrderStatus::Cancelled->value),
            ], 'total_amount')
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $matches) use ($search): void {
                    $matches->where('name', 'like', '%'.$search.'%')
                        ->orWhere('company_name', 'like', '%'.$search.'%')
                        ->orWhere('phone', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%')
                        ->orWhere('tax_number', 'like', '%'.$search.'%');
                });
            })
            ->latest()
            ->orderByDesc('id');

        if ($filters['all'] ?? false) {
            return SupplierResource::collection($query->get());
        }

        return SupplierResource::collection($query->paginate((int) ($filters['per_page'] ?? 25))->appends($filters));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreSupplierRequest $request, PartyManagementService $parties): JsonResponse
    {
        return (new SupplierResource($parties->createSupplier($request->validated())))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Supplier $supplier): SupplierResource
    {
        AuditLogService::viewed(Supplier::class, $supplier->id);

        return new SupplierResource($supplier);
    }

    public function ledger(SupplierAccountIndexRequest $request, Supplier $supplier): AnonymousResourceCollection
    {
        $validated = $request->validated();
        $entries = SupplierLedgerEntry::query()
            ->whereBelongsTo($supplier)
            ->with([
                'purchaseOrder:id,purchase_order_number',
                'goodsReceipt:id,receipt_number',
                'user:id,full_name',
            ])
            ->when($validated['from'] ?? null, fn (Builder $query, string $from) => $query->whereDate('occurred_at', '>=', $from))
            ->when($validated['to'] ?? null, fn (Builder $query, string $to) => $query->whereDate('occurred_at', '<=', $to))
            ->latest('occurred_at')
            ->paginate($request->integer('per_page', 20))
            ->withQueryString();

        return SupplierLedgerEntryResource::collection($entries);
    }

    public function payments(SupplierAccountIndexRequest $request, Supplier $supplier): AnonymousResourceCollection
    {
        $validated = $request->validated();
        $payments = SupplierPayment::query()
            ->whereBelongsTo($supplier)
            ->with(['paymentMethod:id,name,code', 'purchaseOrder:id,purchase_order_number', 'user:id,full_name', 'ledgerEntry:id,supplier_payment_id,balance_after'])
            ->when($validated['from'] ?? null, fn (Builder $query, string $from) => $query->whereDate('paid_at', '>=', $from))
            ->when($validated['to'] ?? null, fn (Builder $query, string $to) => $query->whereDate('paid_at', '<=', $to))
            ->latest('paid_at')
            ->paginate($request->integer('per_page', 20))
            ->withQueryString();

        return SupplierPaymentResource::collection($payments);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSupplierRequest $request, Supplier $supplier, PartyManagementService $parties): SupplierResource
    {
        return new SupplierResource($parties->updateSupplier($supplier, $request->validated()));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Supplier $supplier, PartyManagementService $parties): Response
    {
        $parties->deleteSupplier($supplier);

        return response()->noContent();
    }
}
