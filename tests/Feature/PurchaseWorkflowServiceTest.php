<?php

namespace Tests\Feature;

use App\Enums\PurchaseOrderStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\GoodsReceiptService;
use App\Services\PurchaseOrderService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PurchaseWorkflowServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_partial_and_final_receipts_update_inventory_purchase_and_supplier_ledger(): void
    {
        $user = User::query()->firstOrFail();
        $supplier = Supplier::query()->firstOrFail();
        $warehouse = Warehouse::query()->where('code', 'MAIN')->firstOrFail();
        $product = Product::query()->where('sku', 'SKU-001')->firstOrFail();
        $startingQuantity = (string) $this->balance($product, $warehouse)->quantity_on_hand;
        $purchaseService = app(PurchaseOrderService::class);
        $purchaseOrder = $purchaseService->createDraft(
            $user,
            $supplier,
            $warehouse,
            [['product_id' => $product->id, 'quantity' => '10', 'unit_cost' => '0.8000']],
        );
        $purchaseOrder = $purchaseService->send($purchaseOrder);
        $item = $purchaseOrder->items()->firstOrFail();

        $firstReceipt = app(GoodsReceiptService::class)->receive(
            $purchaseOrder,
            $user,
            [['purchase_order_item_id' => $item->id, 'quantity' => '4']],
        );

        $this->assertSame(PurchaseOrderStatus::PartiallyReceived, $purchaseOrder->refresh()->status);
        $this->assertSame('4.000', $item->refresh()->received_quantity);
        $this->assertSame(bcadd($startingQuantity, '4.000', 3), $this->balance($product, $warehouse)->quantity_on_hand);
        $this->assertDatabaseHas('supplier_ledger_entries', ['goods_receipt_id' => $firstReceipt->id, 'amount_delta' => '3.20']);

        try {
            app(GoodsReceiptService::class)->receive(
                $purchaseOrder,
                $user,
                [['purchase_order_item_id' => $item->id, 'quantity' => '7']],
            );
            $this->fail('Receiving more than the outstanding quantity should fail.');
        } catch (BusinessRuleException $exception) {
            $this->assertSame('A receipt quantity cannot exceed the outstanding ordered quantity.', $exception->getMessage());
        }
        $this->assertSame('4.000', $item->refresh()->received_quantity);

        app(GoodsReceiptService::class)->receive(
            $purchaseOrder,
            $user,
            [['purchase_order_item_id' => $item->id, 'quantity' => '6']],
        );

        $this->assertSame(PurchaseOrderStatus::Received, $purchaseOrder->refresh()->status);
        $this->assertSame('10.000', $item->refresh()->received_quantity);
        $this->assertSame(bcadd($startingQuantity, '10.000', 3), $this->balance($product, $warehouse)->quantity_on_hand);
    }

    public function test_final_partial_receipt_absorbs_rounding_residuals(): void
    {
        $user = User::query()->firstOrFail();
        $supplier = Supplier::factory()->create(['balance' => '0.00', 'payable_limit' => '0.00']);
        $warehouse = Warehouse::query()->where('code', 'MAIN')->firstOrFail();
        $product = Product::query()->where('sku', 'SKU-002')->firstOrFail();
        $purchaseService = app(PurchaseOrderService::class);
        $purchaseOrder = $purchaseService->send($purchaseService->createDraft(
            $user,
            $supplier,
            $warehouse,
            [[
                'product_id' => $product->id,
                'quantity' => '3',
                'unit_cost' => '1.0000',
                'discount_amount' => '1.00',
            ]],
        ));
        $item = $purchaseOrder->items()->firstOrFail();
        $receipts = collect();

        foreach (range(1, 3) as $_) {
            $receipts->push(app(GoodsReceiptService::class)->receive(
                $purchaseOrder,
                $user,
                [['purchase_order_item_id' => $item->id, 'quantity' => '1']],
            ));
        }

        foreach (['subtotal_amount', 'discount_amount', 'tax_amount', 'total_amount'] as $amountColumn) {
            $receivedTotal = $receipts->reduce(
                fn (string $total, $receipt): string => bcadd($total, (string) $receipt->{$amountColumn}, 2),
                '0.00',
            );
            $this->assertSame((string) $purchaseOrder->{$amountColumn}, $receivedTotal);
        }
        $this->assertSame((string) $purchaseOrder->total_amount, $supplier->refresh()->balance);

        $this->expectException(BusinessRuleException::class);
        app(GoodsReceiptService::class)->receive(
            $purchaseOrder,
            $user,
            [['purchase_order_item_id' => $item->id, 'quantity' => '1']],
        );
    }

    private function balance(Product $product, Warehouse $warehouse): InventoryBalance
    {
        return InventoryBalance::query()->whereBelongsTo($product)->whereBelongsTo($warehouse)->firstOrFail();
    }
}
