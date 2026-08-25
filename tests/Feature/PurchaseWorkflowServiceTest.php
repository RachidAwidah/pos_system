<?php

namespace Tests\Feature;

use App\Enums\PurchaseOrderStatus;
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
        $this->assertSame('24.000', $this->balance($product, $warehouse)->quantity_on_hand);
        $this->assertDatabaseHas('supplier_ledger_entries', ['goods_receipt_id' => $firstReceipt->id, 'amount_delta' => '3.20']);

        app(GoodsReceiptService::class)->receive(
            $purchaseOrder,
            $user,
            [['purchase_order_item_id' => $item->id, 'quantity' => '6']],
        );

        $this->assertSame(PurchaseOrderStatus::Received, $purchaseOrder->refresh()->status);
        $this->assertSame('10.000', $item->refresh()->received_quantity);
        $this->assertSame('30.000', $this->balance($product, $warehouse)->quantity_on_hand);
    }

    private function balance(Product $product, Warehouse $warehouse): InventoryBalance
    {
        return InventoryBalance::query()->whereBelongsTo($product)->whereBelongsTo($warehouse)->firstOrFail();
    }
}
