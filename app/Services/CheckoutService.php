<?php

namespace App\Services;

use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\ShiftStatus;
use App\Exceptions\BusinessInputException as InvalidArgumentException;
use App\Exceptions\BusinessRuleException as DomainException;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CheckoutService
{
    public function __construct(
        public InventoryService $inventoryService,
        public CustomerAccountService $customerAccountService,
        public LoyaltyService $loyaltyService,
    ) {}

    /**
     * @param  array<int, array{product_id: string, quantity: string}>  $items
     * @param  array<int, array{payment_method_id: string, amount: string, amount_tendered?: string|null, reference_number?: string|null}>  $payments
     */
    public function checkout(
        User $user,
        Shift $shift,
        array $items,
        array $payments,
        ?Customer $customer = null,
        string $discountType = 'none',
        string $discountValue = '0',
        int $loyaltyPoints = 0,
        ?string $notes = null,
        string $displayCurrency = 'USD',
        ?float $exchangeRate = null,
        ?string $rateProvider = null,
    ): Order {
        if ($items === []) {
            throw new InvalidArgumentException('A sale must contain at least one item.');
        }
        if ($loyaltyPoints < 0) {
            throw new InvalidArgumentException('Loyalty points cannot be negative.');
        }

        return DB::transaction(function () use ($user, $shift, $items, $payments, $customer, $discountType, $discountValue, $loyaltyPoints, $notes, $displayCurrency, $exchangeRate, $rateProvider): Order {
            $lockedShift = Shift::query()
                ->with('register.warehouse')
                ->lockForUpdate()
                ->findOrFail($shift->id);
            if ($lockedShift->status !== ShiftStatus::Open) {
                throw new DomainException('Sales require an open cash session.');
            }
            if ($lockedShift->opened_by_user_id !== $user->id) {
                throw new DomainException('The user must operate their own cash session.');
            }

            $lockedCustomer = $customer === null
                ? null
                : Customer::query()->lockForUpdate()->findOrFail($customer->id);
            $normalizedItems = $this->normalizeItems($items);
            $subtotalAmount = '0.00';
            foreach ($normalizedItems as $item) {
                $subtotalAmount = bcadd($subtotalAmount, $item['subtotal_amount'], 2);
            }

            $cartDiscount = $this->calculateDiscount($subtotalAmount, $discountType, $discountValue);
            $loyaltyDiscount = $this->loyaltyDiscount($lockedCustomer, $loyaltyPoints);
            $discountAmount = bcadd($cartDiscount, $loyaltyDiscount, 2);
            if (bccomp($discountAmount, $subtotalAmount, 2) === 1) {
                throw new DomainException('The combined discount cannot exceed the sale subtotal.');
            }

            $normalizedItems = $this->allocateDiscountAndTax($normalizedItems, $discountAmount, $subtotalAmount);
            $taxAmount = '0.00';
            $finalAmount = '0.00';
            foreach ($normalizedItems as $item) {
                $taxAmount = bcadd($taxAmount, $item['tax_amount'], 2);
                $finalAmount = bcadd($finalAmount, $item['total_amount'], 2);
            }

            $normalizedPayments = $this->normalizePayments($payments);
            $paidAmount = '0.00';
            foreach ($normalizedPayments as $payment) {
                $paidAmount = bcadd($paidAmount, $payment['amount'], 2);
            }
            if (bccomp($paidAmount, $finalAmount, 2) === 1) {
                throw new DomainException('Payments cannot exceed the final sale amount.');
            }

            $dueAmount = bcsub($finalAmount, $paidAmount, 2);
            if (bccomp($dueAmount, '0.00', 2) === 1 && $lockedCustomer === null) {
                throw new DomainException('A customer is required for a credit sale.');
            }

            $order = Order::query()->create([
                'invoice_number' => $this->nextInvoiceNumber(),
                'user_id' => $user->id,
                'customer_id' => $lockedCustomer?->id,
                'shift_id' => $lockedShift->id,
                'warehouse_id' => $lockedShift->register->warehouse_id,
                'status' => OrderStatus::Completed,
                'subtotal_amount' => $subtotalAmount,
                'discount_amount' => $discountAmount,
                'tax_amount' => $taxAmount,
                'final_amount' => $finalAmount,
                'paid_amount' => $paidAmount,
                'due_amount' => $dueAmount,
                'refunded_amount' => 0,
                'payment_status' => $this->paymentStatus($paidAmount, $dueAmount),
                'notes' => $notes,
                'order_date' => now(),
                'completed_at' => now(),
                'display_currency' => $displayCurrency,
                'exchange_rate_used' => $exchangeRate,
                'rate_provider' => $rateProvider,
            ]);
            AuditLogService::created(Order::class, $order->id, $this->orderValues($order));

            foreach ($normalizedItems as $item) {
                $product = $item['product'];
                $costPriceAtSale = (string) $product->cost_price;

                if ($product->type->tracksInventory()) {
                    $stockMovement = $this->inventoryService->sell(
                        $product,
                        $lockedShift->register->warehouse,
                        $item['quantity'],
                        $user,
                        $order,
                        'Sale '.$order->invoice_number,
                    );
                    $costPriceAtSale = (string) ($stockMovement->unit_cost ?? $product->cost_price);
                }

                $orderItem = $order->items()->create([
                    'product_id' => $product->id,
                    'product_name' => $product->product_name,
                    'sku' => $product->sku,
                    'quantity' => $item['quantity'],
                    'unit_price' => $product->price,
                    'cost_price_at_sale' => $costPriceAtSale,
                    'tax_rate' => $item['tax_rate'],
                    'subtotal_amount' => $item['subtotal_amount'],
                    'discount_amount' => $item['discount_amount'],
                    'tax_amount' => $item['tax_amount'],
                    'total_amount' => $item['total_amount'],
                ]);
                AuditLogService::created(OrderItem::class, $orderItem->id, $this->orderItemValues($orderItem));

            }

            foreach ($normalizedPayments as $values) {
                $payment = $order->payments()->create([
                    'shift_id' => $lockedShift->id,
                    'user_id' => $user->id,
                    'payment_method_id' => $values['payment_method']->id,
                    'type' => PaymentType::Payment,
                    'status' => PaymentStatus::Completed,
                    'amount' => $values['amount'],
                    'amount_tendered' => $values['amount_tendered'],
                    'change_amount' => $values['change_amount'],
                    'reference_number' => $values['reference_number'],
                    'paid_at' => now(),
                ]);
                AuditLogService::created(Payment::class, $payment->id, $this->paymentValues($payment));
            }

            if ($lockedCustomer !== null) {
                $this->customerAccountService->recordSale($lockedCustomer, $order, $user);
                if ($loyaltyPoints > 0) {
                    $this->loyaltyService->redeemForOrder($lockedCustomer, $order, $user, $loyaltyPoints);
                }
                $this->loyaltyService->earnForOrder($lockedCustomer, $order, $user);
            }

            return $order->load(['items', 'payments.paymentMethod', 'customer', 'warehouse', 'shift.register']);
        }, attempts: 5);
    }

    /**
     * @param  array<int, array{product_id: string, quantity: string}>  $items
     * @return array<int, array{product: Product, quantity: string, subtotal_amount: string, tax_rate: string}>
     */
    private function normalizeItems(array $items): array
    {
        $itemsByProductId = [];
        foreach ($items as $item) {
            $productId = $item['product_id'] ?? '';
            if ($productId === '' || isset($itemsByProductId[$productId])) {
                throw new InvalidArgumentException('Every sale item must reference a unique product.');
            }
            $itemsByProductId[$productId] = $item;
        }

        ksort($itemsByProductId);
        $products = Product::query()
            ->with(['tax', 'unit'])
            ->whereIn('id', array_keys($itemsByProductId))
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
        if ($products->count() !== count($itemsByProductId)) {
            throw new InvalidArgumentException('One or more sale products do not exist.');
        }

        $normalizedItems = [];
        foreach ($itemsByProductId as $productId => $item) {
            $product = $products->get($productId);
            $quantity = $this->normalizeQuantity((string) ($item['quantity'] ?? ''), $product->unit->decimal_places);
            $normalizedItems[] = [
                'product' => $product,
                'quantity' => $quantity,
                'subtotal_amount' => $this->roundMoney(bcmul($quantity, (string) $product->price, 6)),
                'tax_rate' => $this->normalizeDecimal((string) ($product->tax?->tax_percentage ?? 0), 4),
            ];
        }

        return $normalizedItems;
    }

    /**
     * @param  array<int, array{payment_method_id: string, amount: string, amount_tendered?: string|null, reference_number?: string|null}>  $payments
     * @return array<int, array{payment_method: PaymentMethod, amount: string, amount_tendered: string|null, change_amount: string, reference_number: string|null}>
     */
    private function normalizePayments(array $payments): array
    {
        if ($payments === []) {
            return [];
        }

        $methodIds = array_values(array_unique(array_map(fn (array $payment): string => $payment['payment_method_id'] ?? '', $payments)));
        sort($methodIds);
        if (in_array('', $methodIds, true)) {
            throw new InvalidArgumentException('Every payment must reference a payment method.');
        }

        $paymentMethods = PaymentMethod::query()->whereIn('id', $methodIds)->lockForUpdate()->get()->keyBy('id');
        if ($paymentMethods->count() !== count($methodIds)) {
            throw new InvalidArgumentException('One or more payment methods do not exist.');
        }

        $normalizedPayments = [];
        foreach ($payments as $payment) {
            $paymentMethod = $paymentMethods->get($payment['payment_method_id']);
            if (! $paymentMethod->is_active) {
                throw new DomainException('An inactive payment method cannot be used.');
            }

            $amount = $this->normalizePositiveMoney((string) ($payment['amount'] ?? ''));
            $referenceNumber = $this->nullableString($payment['reference_number'] ?? null);
            if ($paymentMethod->requires_reference && $referenceNumber === null) {
                throw new InvalidArgumentException('A reference number is required for this payment method.');
            }

            $amountTendered = null;
            $changeAmount = '0.00';
            if ($paymentMethod->category === 'cash') {
                $amountTendered = $this->normalizeMoney((string) ($payment['amount_tendered'] ?? $amount));
                if (bccomp($amountTendered, $amount, 2) === -1) {
                    throw new DomainException('Cash tendered cannot be less than the applied payment amount.');
                }
                $changeAmount = bcsub($amountTendered, $amount, 2);
            } elseif (($payment['amount_tendered'] ?? null) !== null) {
                throw new InvalidArgumentException('Amount tendered is only valid for cash payments.');
            }

            $normalizedPayments[] = compact('paymentMethod', 'amount', 'amountTendered', 'changeAmount', 'referenceNumber');
        }

        return array_map(fn (array $payment): array => [
            'payment_method' => $payment['paymentMethod'],
            'amount' => $payment['amount'],
            'amount_tendered' => $payment['amountTendered'],
            'change_amount' => $payment['changeAmount'],
            'reference_number' => $payment['referenceNumber'],
        ], $normalizedPayments);
    }

    /** @param array<int, array{product: Product, quantity: string, subtotal_amount: string, tax_rate: string}> $items */
    private function allocateDiscountAndTax(array $items, string $discountAmount, string $subtotalAmount): array
    {
        $remainingDiscount = $discountAmount;
        $lastIndex = array_key_last($items);
        foreach ($items as $index => &$item) {
            $lineDiscount = $index === $lastIndex
                ? $remainingDiscount
                : $this->roundMoney(bcdiv(bcmul($discountAmount, $item['subtotal_amount'], 6), $subtotalAmount, 6));
            if (bccomp($lineDiscount, $item['subtotal_amount'], 2) === 1) {
                $lineDiscount = $item['subtotal_amount'];
            }
            $remainingDiscount = bcsub($remainingDiscount, $lineDiscount, 2);
            $taxableAmount = bcsub($item['subtotal_amount'], $lineDiscount, 2);
            $taxAmount = $this->roundMoney(bcdiv(bcmul($taxableAmount, $item['tax_rate'], 6), '100', 6));
            $item['discount_amount'] = $lineDiscount;
            $item['tax_amount'] = $taxAmount;
            $item['total_amount'] = bcadd($taxableAmount, $taxAmount, 2);
        }
        unset($item);

        return $items;
    }

    private function calculateDiscount(string $subtotal, string $type, string $value): string
    {
        $normalizedValue = $this->normalizeMoney($value);

        return match ($type) {
            'none' => '0.00',
            'fixed' => $normalizedValue,
            'percentage' => bccomp($normalizedValue, '100.00', 2) === 1
                ? throw new InvalidArgumentException('A percentage discount cannot exceed 100.')
                : $this->roundMoney(bcdiv(bcmul($subtotal, $normalizedValue, 6), '100', 6)),
            default => throw new InvalidArgumentException('Discount type must be none, fixed, or percentage.'),
        };
    }

    private function loyaltyDiscount(?Customer $customer, int $points): string
    {
        if ($points === 0) {
            return '0.00';
        }
        if ($customer === null) {
            throw new DomainException('A customer is required to redeem loyalty points.');
        }
        if ($points > $customer->loyalty_points) {
            throw new DomainException('The customer does not have enough loyalty points.');
        }

        $pointValue = number_format((float) Setting::valueFor('loyalty_point_value', 0.01), 4, '.', '');

        return $this->roundMoney(bcmul((string) $points, $pointValue, 6));
    }

    private function paymentStatus(string $paidAmount, string $dueAmount): OrderPaymentStatus
    {
        if (bccomp($dueAmount, '0.00', 2) === 0) {
            return OrderPaymentStatus::Paid;
        }

        return bccomp($paidAmount, '0.00', 2) === 1 ? OrderPaymentStatus::Partial : OrderPaymentStatus::Unpaid;
    }

    private function normalizeQuantity(string $quantity, int $decimalPlaces): string
    {
        $quantity = trim($quantity);
        $pattern = $decimalPlaces === 0 ? '/^\d+$/' : '/^\d+(?:\.\d{1,'.$decimalPlaces.'})?$/';
        if (! preg_match($pattern, $quantity)) {
            throw new InvalidArgumentException("Quantity must use at most {$decimalPlaces} decimal places for this unit.");
        }
        $normalizedQuantity = bcadd($quantity, '0', 3);
        if (bccomp($normalizedQuantity, '0.000', 3) !== 1) {
            throw new InvalidArgumentException('Quantity must be greater than zero.');
        }

        return $normalizedQuantity;
    }

    private function normalizePositiveMoney(string $value): string
    {
        $value = $this->normalizeMoney($value);
        if (bccomp($value, '0.00', 2) !== 1) {
            throw new InvalidArgumentException('Amount must be greater than zero.');
        }

        return $value;
    }

    private function normalizeMoney(string $value): string
    {
        return $this->normalizeDecimal($value, 2);
    }

    private function normalizeDecimal(string $value, int $scale): string
    {
        $value = trim($value);
        if (! preg_match('/^\d+(?:\.\d{1,'.$scale.'})?$/', $value)) {
            throw new InvalidArgumentException("Value must be a non-negative decimal with at most {$scale} decimal places.");
        }

        return bcadd($value, '0', $scale);
    }

    private function roundMoney(string $value): string
    {
        return bcadd(bcadd($value, '0.005', 3), '0', 2);
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private function nextInvoiceNumber(): string
    {
        return 'INV-'.now()->format('Ymd').'-'.Str::upper(Str::random(10));
    }

    /** @return array<string, mixed> */
    private function orderValues(Order $order): array
    {
        return [
            'invoice_number' => $order->invoice_number,
            'user_id' => $order->user_id,
            'customer_id' => $order->customer_id,
            'shift_id' => $order->shift_id,
            'warehouse_id' => $order->warehouse_id,
            'status' => $order->status->value,
            'subtotal_amount' => (string) $order->subtotal_amount,
            'discount_amount' => (string) $order->discount_amount,
            'tax_amount' => (string) $order->tax_amount,
            'final_amount' => (string) $order->final_amount,
            'paid_amount' => (string) $order->paid_amount,
            'due_amount' => (string) $order->due_amount,
            'payment_status' => $order->payment_status->value,
        ];
    }

    /** @return array<string, mixed> */
    private function orderItemValues(OrderItem $item): array
    {
        return [
            'order_id' => $item->order_id,
            'product_id' => $item->product_id,
            'quantity' => (string) $item->quantity,
            'unit_price' => (string) $item->unit_price,
            'cost_price_at_sale' => (string) $item->cost_price_at_sale,
            'discount_amount' => (string) $item->discount_amount,
            'tax_amount' => (string) $item->tax_amount,
            'total_amount' => (string) $item->total_amount,
        ];
    }

    /** @return array<string, mixed> */
    private function paymentValues(Payment $payment): array
    {
        return [
            'order_id' => $payment->order_id,
            'shift_id' => $payment->shift_id,
            'user_id' => $payment->user_id,
            'payment_method_id' => $payment->payment_method_id,
            'type' => $payment->type->value,
            'status' => $payment->status->value,
            'amount' => (string) $payment->amount,
            'amount_tendered' => $payment->amount_tendered === null ? null : (string) $payment->amount_tendered,
            'change_amount' => (string) $payment->change_amount,
            'reference_number' => $payment->reference_number,
        ];
    }
}
