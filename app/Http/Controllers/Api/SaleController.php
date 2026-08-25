<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckoutRequest;
use App\Http\Requests\PosReferenceRequest;
use App\Http\Resources\OrderResource;
use App\Models\Customer;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Register;
use App\Models\Shift;
use App\Models\Warehouse;
use App\Services\AuditLogService;
use App\Services\CheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SaleController extends Controller
{
    public function __construct(public CheckoutService $checkoutService) {}

    public function index(): AnonymousResourceCollection
    {
        return OrderResource::collection(Order::query()->with(['customer', 'user'])->latest('order_date')->paginate(20));
    }

    public function referenceData(PosReferenceRequest $request): JsonResponse
    {
        $warehouse = isset($request->validated()['warehouse_id'])
            ? Warehouse::query()->active()->findOrFail($request->validated()['warehouse_id'])
            : Warehouse::query()->active()->defaultWarehouse()->firstOrFail();
        $products = Product::query()
            ->with(['tax:id,tax_percentage', 'unit:id,symbol,decimal_places', 'inventoryBalances' => fn ($query) => $query->whereBelongsTo($warehouse)])
            ->orderBy('product_name')
            ->get()
            ->map(function (Product $product): array {
                $balance = $product->inventoryBalances->first();

                return [
                    'id' => $product->id, 'name' => $product->product_name, 'sku' => $product->sku,
                    'barcode' => $product->barcode, 'price' => $product->price, 'type' => $product->type,
                    'tax_rate' => $product->tax?->tax_percentage ?? '0.00', 'unit' => $product->unit,
                    'quantity_available' => $balance === null ? '0.000' : bcsub((string) $balance->quantity_on_hand, (string) $balance->quantity_reserved, 3),
                ];
            });

        return response()->json(['data' => [
            'warehouse' => $warehouse,
            'registers' => Register::query()->whereBelongsTo($warehouse)->where('is_active', true)->orderBy('name')->get(),
            'payment_methods' => PaymentMethod::query()->where('is_active', true)->orderBy('name')->get(),
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name', 'phone', 'credit_limit', 'balance', 'loyalty_points']),
            'products' => $products,
        ]]);
    }

    public function store(CheckoutRequest $request): OrderResource
    {
        $validated = $request->validated();
        $order = $this->checkoutService->checkout(
            $request->user(),
            Shift::query()->findOrFail($validated['shift_id']),
            $validated['items'],
            $validated['payments'],
            isset($validated['customer_id']) ? Customer::query()->findOrFail($validated['customer_id']) : null,
            $validated['discount_type'] ?? 'none',
            (string) ($validated['discount_value'] ?? '0'),
            $validated['loyalty_points'] ?? 0,
            $validated['notes'] ?? null,
        );

        return new OrderResource($order);
    }

    public function show(Order $order): OrderResource
    {
        AuditLogService::viewed(Order::class, $order->id);

        return new OrderResource($order->load(['items', 'payments.paymentMethod', 'customer', 'warehouse', 'returns']));
    }
}
