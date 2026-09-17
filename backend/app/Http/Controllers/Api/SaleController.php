<?php

namespace App\Http\Controllers\Api;

use App\Enums\ShiftStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\CheckoutRequest;
use App\Http\Requests\OrderIndexRequest;
use App\Http\Requests\PosReferenceRequest;
use App\Http\Resources\OrderResource;
use App\Http\Resources\ShiftResource;
use App\Models\Customer;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Register;
use App\Models\Shift;
use App\Models\Warehouse;
use App\Services\AuditLogService;
use App\Services\CheckoutService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SaleController extends Controller
{
    public function __construct(public CheckoutService $checkoutService) {}

    public function index(OrderIndexRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        $query = Order::query()->with(['customer', 'user'])
            ->when($filters['customer_id'] ?? null, fn (Builder $query, string $id) => $query->where('customer_id', $id))
            ->when($filters['due_only'] ?? false, fn (Builder $query) => $query->where('due_amount', '>', 0))
            ->latest('order_date')
            ->orderByDesc('id');

        if ($filters['all'] ?? false) {
            return OrderResource::collection($query->get());
        }

        return OrderResource::collection($query->paginate((int) ($filters['per_page'] ?? 20))->appends($filters));
    }

    public function referenceData(PosReferenceRequest $request): JsonResponse
    {
        $currentShift = Shift::query()
            ->with('register')
            ->whereBelongsTo($request->user(), 'openedBy')
            ->where('status', ShiftStatus::Open->value)
            ->first();
        $warehouse = isset($request->validated()['warehouse_id'])
            ? Warehouse::query()->active()->findOrFail($request->validated()['warehouse_id'])
            : ($currentShift?->register?->warehouse_id
                ? Warehouse::query()->active()->findOrFail($currentShift->register->warehouse_id)
                : Warehouse::query()->active()->defaultWarehouse()->firstOrFail());
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

        $registers = Register::query()->whereBelongsTo($warehouse)->where('is_active', true)->orderBy('name')->get();
        $openShifts = Shift::query()
            ->with('openedBy:id,full_name')
            ->whereIn('register_id', $registers->modelKeys())
            ->where('status', ShiftStatus::Open->value)
            ->get()
            ->keyBy('register_id');

        return response()->json(['data' => [
            'warehouse' => $warehouse,
            'registers' => $registers->map(function (Register $register) use ($openShifts, $request): array {
                $openShift = $openShifts->get($register->id);

                return [
                    'id' => $register->id,
                    'name' => $register->name,
                    'open_shift' => $openShift === null ? null : [
                        'id' => $openShift->id,
                        'opened_by_user_id' => $openShift->opened_by_user_id,
                        'opened_by_name' => $openShift->openedBy?->full_name,
                        'opening_cash' => $openShift->opening_cash,
                        'opened_at' => $openShift->opened_at,
                        'owned_by_current_user' => $openShift->opened_by_user_id === $request->user()->id,
                    ],
                ];
            }),
            'payment_methods' => PaymentMethod::query()->where('is_active', true)->orderBy('name')->get(),
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name', 'phone', 'credit_limit', 'balance', 'loyalty_points']),
            'products' => $products,
            'current_shift' => $currentShift === null ? null : (new ShiftResource($currentShift))->resolve($request),
            'can_force_close_shifts' => $request->user()->hasRole('Admin'),
        ]]);
    }

    public function products(PosReferenceRequest $request): JsonResponse
    {
        $warehouse = isset($request->validated()['warehouse_id'])
            ? Warehouse::query()->active()->findOrFail($request->validated()['warehouse_id'])
            : Warehouse::query()->active()->defaultWarehouse()->firstOrFail();
        $perPage = min(max($request->integer('per_page', 25), 10), 100);
        $products = Product::query()
            ->with(['tax:id,tax_percentage', 'unit:id,symbol,decimal_places', 'inventoryBalances' => fn ($query) => $query->whereBelongsTo($warehouse)])
            ->orderBy('product_name')
            ->paginate($perPage);
        $products->getCollection()->transform(function (Product $product): array {
            $balance = $product->inventoryBalances->first();

            return [
                'id' => $product->id, 'name' => $product->product_name, 'sku' => $product->sku,
                'barcode' => $product->barcode, 'price' => $product->price, 'type' => $product->type,
                'tax_rate' => $product->tax?->tax_percentage ?? '0.00', 'unit' => $product->unit,
                'quantity_available' => $balance === null ? '0.000' : bcsub((string) $balance->quantity_on_hand, (string) $balance->quantity_reserved, 3),
            ];
        });

        return response()->json(['data' => $products->items(), 'meta' => [
            'current_page' => $products->currentPage(),
            'last_page' => $products->lastPage(),
            'per_page' => $products->perPage(),
            'total' => $products->total(),
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
            $validated['display_currency'] ?? 'USD',
            isset($validated['exchange_rate']) ? (float) $validated['exchange_rate'] : null,
            $validated['rate_provider'] ?? null,
        );

        return new OrderResource($order);
    }

    public function show(Order $order): OrderResource
    {
        AuditLogService::viewed(Order::class, $order->id);

        return new OrderResource($order->load([
            'items.product',
            'payments.paymentMethod',
            'customer',
            'user',
            'warehouse',
            'shift.register',
            'returns.items.product',
            'returns.payments.paymentMethod',
            'returns.user',
            'returns.warehouse',
            'returns.shift',
        ]));
    }
}
