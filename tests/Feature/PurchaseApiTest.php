<?php

namespace Tests\Feature;

use App\Enums\AccountEntryType;
use App\Enums\PurchaseOrderStatus;
use App\Models\AuditLog;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\SupplierLedgerEntry;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\GoodsReceiptService;
use App\Services\PurchaseOrderService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PurchaseApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_draft_purchase_order_can_be_created_updated_and_deleted_only_while_draft(): void
    {
        $this->actingAsAdmin();
        $supplier = Supplier::factory()->create();
        $warehouse = Warehouse::factory()->create(['is_active' => true]);
        $product = Product::query()->where('sku', 'SKU-001')->firstOrFail();
        $payload = [
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'items' => [[
                'product_id' => $product->id,
                'quantity' => '2.000',
                'unit_cost' => '2.5000',
                'discount_amount' => '1.00',
            ]],
        ];

        $created = $this->postJson('/v1/purchase-orders', $payload)
            ->assertCreated()
            ->assertJsonPath('data.total_amount', '4.00');
        $purchaseOrderId = $created->json('data.id');

        $payload['items'][0]['quantity'] = '3.000';
        $payload['items'][0]['discount_amount'] = '0.00';
        $this->putJson("/v1/purchase-orders/{$purchaseOrderId}", $payload)
            ->assertOk()
            ->assertJsonPath('data.subtotal_amount', '7.50')
            ->assertJsonPath('data.items.0.ordered_quantity', '3.000');

        $this->postJson("/v1/purchase-orders/{$purchaseOrderId}/send")
            ->assertOk()
            ->assertJsonPath('data.status', PurchaseOrderStatus::Sent->value);
        $this->putJson("/v1/purchase-orders/{$purchaseOrderId}", $payload)->assertConflict();
        $this->deleteJson("/v1/purchase-orders/{$purchaseOrderId}")->assertConflict();

        $deletableId = $this->postJson('/v1/purchase-orders', $payload)
            ->assertCreated()
            ->json('data.id');
        $this->deleteJson("/v1/purchase-orders/{$deletableId}")->assertNoContent();
        $this->assertSoftDeleted('purchase_orders', ['id' => $deletableId]);
        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => PurchaseOrder::class,
            'entity_id' => $deletableId,
            'action' => 'delete',
        ]);

        $cancelledId = $this->postJson('/v1/purchase-orders', $payload)
            ->assertCreated()
            ->json('data.id');
        $this->postJson("/v1/purchase-orders/{$cancelledId}/cancel", [
            'reason' => 'No longer required',
        ])->assertOk()->assertJsonPath('data.status', PurchaseOrderStatus::Cancelled->value);
        $this->postJson("/v1/purchase-orders/{$cancelledId}/send")->assertConflict();
    }

    public function test_supplier_payment_updates_balance_and_rejects_an_excess_payment(): void
    {
        $admin = $this->actingAsAdmin();
        $supplier = Supplier::factory()->create(['balance' => '0.00', 'payable_limit' => '0.00']);
        $warehouse = Warehouse::query()->where('code', 'MAIN')->firstOrFail();
        $product = Product::query()->where('sku', 'SKU-001')->firstOrFail();
        $purchaseOrder = app(PurchaseOrderService::class)->createDraft(
            $admin,
            $supplier,
            $warehouse,
            [['product_id' => $product->id, 'quantity' => '2', 'unit_cost' => '1.0000']],
        );
        $purchaseOrder = app(PurchaseOrderService::class)->send($purchaseOrder);
        app(GoodsReceiptService::class)->receive($purchaseOrder, $admin, [[
            'purchase_order_item_id' => $purchaseOrder->items()->value('id'),
            'quantity' => '2',
        ]]);
        $cash = PaymentMethod::query()->where('code', 'CASH')->firstOrFail();

        $this->postJson("/v1/suppliers/{$supplier->id}/payments", [
            'purchase_order_id' => $purchaseOrder->id,
            'payment_method_id' => $cash->id,
            'amount' => '1.25',
            'notes' => 'Partial supplier payment',
        ])->assertCreated()
            ->assertJsonPath('data.amount', '1.25')
            ->assertJsonPath('data.balance_after', '0.75');

        $this->assertSame('0.75', $supplier->refresh()->balance);
        $this->assertDatabaseHas('supplier_ledger_entries', [
            'supplier_id' => $supplier->id,
            'amount_delta' => '-1.25',
        ]);
        $this->postJson("/v1/suppliers/{$supplier->id}/payments", [
            'payment_method_id' => $cash->id,
            'amount' => '1.00',
        ])->assertConflict();
    }

    public function test_purchase_orders_can_be_filtered_for_the_admin_panel(): void
    {
        $this->actingAsAdmin();
        $supplier = Supplier::factory()->create(['name' => 'Filtered Supplier']);
        $warehouse = Warehouse::factory()->create();
        $purchaseOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'status' => PurchaseOrderStatus::Sent,
        ]);
        PurchaseOrderItem::factory()->create(['purchase_order_id' => $purchaseOrder->id]);
        PurchaseOrder::factory()->create(['status' => PurchaseOrderStatus::Cancelled]);

        $this->getJson('/v1/purchase-orders?supplier_id='.$supplier->id.'&warehouse_id='.$warehouse->id.'&status=sent&search=Filtered')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $purchaseOrder->id)
            ->assertJsonPath('data.0.items_count', 1)
            ->assertJsonPath('data.0.supplier.id', $supplier->id)
            ->assertJsonStructure(['data', 'links', 'meta']);
    }

    public function test_goods_receipts_can_be_listed_and_viewed_with_audit(): void
    {
        $this->actingAsAdmin();
        $supplier = Supplier::factory()->create();
        $purchaseOrder = PurchaseOrder::factory()->create(['supplier_id' => $supplier->id]);
        $purchaseOrderItem = PurchaseOrderItem::factory()->create(['purchase_order_id' => $purchaseOrder->id]);
        $goodsReceipt = GoodsReceipt::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'warehouse_id' => $purchaseOrder->warehouse_id,
            'supplier_reference' => 'SUP-TEST-100',
        ]);
        GoodsReceiptItem::factory()->create([
            'goods_receipt_id' => $goodsReceipt->id,
            'purchase_order_item_id' => $purchaseOrderItem->id,
            'product_id' => $purchaseOrderItem->product_id,
        ]);

        $this->getJson('/v1/goods-receipts?supplier_id='.$supplier->id.'&search=SUP-TEST-100')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $goodsReceipt->id)
            ->assertJsonPath('data.0.items_count', 1);

        $this->getJson('/v1/goods-receipts/'.$goodsReceipt->id)
            ->assertOk()
            ->assertJsonPath('data.id', $goodsReceipt->id)
            ->assertJsonPath('data.items.0.product_id', $purchaseOrderItem->product_id);

        $this->assertTrue(AuditLog::query()
            ->where('entity_type', GoodsReceipt::class)
            ->where('entity_id', $goodsReceipt->id)
            ->where('action', 'view')
            ->exists());
    }

    public function test_supplier_ledger_and_payments_are_scoped_to_the_selected_supplier(): void
    {
        $this->actingAsAdmin();
        $supplier = Supplier::factory()->create();
        $otherSupplier = Supplier::factory()->create();
        $ledgerEntry = SupplierLedgerEntry::factory()->create([
            'supplier_id' => $supplier->id,
            'entry_type' => AccountEntryType::Purchase,
            'amount_delta' => '75.00',
            'balance_before' => '0.00',
            'balance_after' => '75.00',
        ]);
        SupplierLedgerEntry::factory()->create(['supplier_id' => $otherSupplier->id]);
        $payment = SupplierPayment::factory()->create(['supplier_id' => $supplier->id]);
        SupplierPayment::factory()->create(['supplier_id' => $otherSupplier->id]);

        $this->getJson('/v1/suppliers/'.$supplier->id.'/ledger')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ledgerEntry->id)
            ->assertJsonPath('data.0.balance_after', '75.00');

        $this->getJson('/v1/suppliers/'.$supplier->id.'/payments')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $payment->id)
            ->assertJsonPath('data.0.payment_method.id', $payment->payment_method_id);
    }

    public function test_purchase_account_routes_enforce_permissions(): void
    {
        $cashier = $this->createTestUser();
        $cashier->assignRole('Cashier');
        Sanctum::actingAs($cashier);
        $supplier = Supplier::factory()->create();

        $this->getJson('/v1/purchase-orders')->assertForbidden();
        $this->getJson('/v1/goods-receipts')->assertForbidden();
        $this->getJson('/v1/suppliers/'.$supplier->id.'/ledger')->assertForbidden();
    }

    private function actingAsAdmin(): User
    {
        $admin = $this->createTestUser();
        $admin->assignRole('Admin');
        Sanctum::actingAs($admin);

        return $admin;
    }
}
