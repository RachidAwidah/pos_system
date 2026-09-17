<?php

namespace Tests\Feature;

use App\Enums\InventoryCountStatus;
use App\Enums\StockMovementType;
use App\Models\AuditLog;
use App\Models\InventoryBalance;
use App\Models\InventoryCount;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InventoryApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_authorized_user_can_filter_balances_and_low_stock(): void
    {
        $this->actingAsAdmin();
        [$product, $warehouse, $balance] = $this->inventoryContext('3.000', '5.000');

        $this->getJson('/v1/inventory/balances?search='.urlencode($product->sku).'&warehouse_id='.$warehouse->id)
            ->assertOk()
            ->assertJsonPath('data.0.id', $balance->id)
            ->assertJsonPath('data.0.quantity_available', '3.000')
            ->assertJsonPath('data.0.is_low_stock', true)
            ->assertJsonPath('data.0.average_cost', '2.5000')
            ->assertJsonStructure(['data', 'links', 'meta']);

        $this->getJson('/v1/inventory/low-stock?warehouse_id='.$warehouse->id)
            ->assertOk()
            ->assertJsonPath('data.0.id', $balance->id);
    }

    public function test_inventory_routes_enforce_permissions(): void
    {
        $cashier = $this->createTestUser();
        $cashier->assignRole('Cashier');
        Sanctum::actingAs($cashier);

        $this->getJson('/v1/inventory/balances')->assertForbidden();
        $this->postJson('/v1/inventory/adjustments', [])->assertForbidden();
        $this->postJson('/v1/inventory-counts', [])->assertForbidden();
    }

    public function test_adjustment_damage_transfer_and_reorder_level_are_recorded(): void
    {
        $admin = $this->actingAsAdmin();
        [$product, $sourceWarehouse, $balance] = $this->inventoryContext('20.000', '5.000');
        $destinationWarehouse = Warehouse::factory()->create();

        $this->putJson("/v1/inventory/balances/{$balance->id}/reorder-level", [
            'reorder_level' => '7.500',
        ])->assertOk()->assertJsonPath('data.reorder_level', '7.500');

        $this->postJson('/v1/inventory/adjustments', [
            'product_id' => $product->id,
            'warehouse_id' => $sourceWarehouse->id,
            'counted_quantity' => '18.000',
            'notes' => 'تصحيح بعد مراجعة الرف',
        ])->assertCreated()
            ->assertJsonPath('data.movement_type', StockMovementType::Adjustment->value)
            ->assertJsonPath('data.balance_after', '18.000');

        $this->postJson('/v1/inventory/damages', [
            'product_id' => $product->id,
            'warehouse_id' => $sourceWarehouse->id,
            'quantity' => '2.000',
            'notes' => 'تلف أثناء التخزين',
        ])->assertCreated()
            ->assertJsonPath('data.movement_type', StockMovementType::Damage->value)
            ->assertJsonPath('data.balance_after', '16.000');

        $transfer = $this->postJson('/v1/inventory/transfers', [
            'product_id' => $product->id,
            'source_warehouse_id' => $sourceWarehouse->id,
            'destination_warehouse_id' => $destinationWarehouse->id,
            'quantity' => '4.000',
            'notes' => 'تغذية مستودع الفرع',
        ])->assertOk();

        $transfer->assertJsonCount(2, 'data')
            ->assertJsonPath('data.out.movement_type', StockMovementType::TransferOut->value)
            ->assertJsonPath('data.in.movement_type', StockMovementType::TransferIn->value);

        $this->assertSame('12.000', $balance->refresh()->quantity_on_hand);
        $this->assertSame(
            '4.000',
            InventoryBalance::query()
                ->whereBelongsTo($product)
                ->whereBelongsTo($destinationWarehouse)
                ->value('quantity_on_hand'),
        );
        $this->assertTrue(AuditLog::query()->where('user_id', $admin->id)->where('entity_type', StockMovement::class)->exists());
    }

    public function test_inventory_count_completes_full_workflow_and_adjusts_stock(): void
    {
        $admin = $this->actingAsAdmin();
        [$product, $warehouse, $balance] = $this->inventoryContext('10.000', '3.000');

        $created = $this->postJson('/v1/inventory-counts', [
            'warehouse_id' => $warehouse->id,
            'product_ids' => [$product->id],
            'notes' => 'جرد نهاية الوردية',
        ])->assertCreated()
            ->assertJsonPath('data.status', InventoryCountStatus::Counting->value)
            ->assertJsonPath('data.items.0.expected_quantity', '10.000');

        $inventoryCountId = $created->json('data.id');

        $this->putJson("/v1/inventory-counts/{$inventoryCountId}/items", [
            'items' => [[
                'product_id' => $product->id,
                'counted_quantity' => '8.000',
            ]],
        ])->assertOk()
            ->assertJsonPath('data.items.0.counted_quantity', '8.000')
            ->assertJsonPath('data.items.0.difference_quantity', '-2.000');

        $this->postJson("/v1/inventory-counts/{$inventoryCountId}/review")
            ->assertOk()
            ->assertJsonPath('data.status', InventoryCountStatus::Reviewed->value);

        $this->postJson("/v1/inventory-counts/{$inventoryCountId}/apply")
            ->assertOk()
            ->assertJsonPath('data.status', InventoryCountStatus::Applied->value)
            ->assertJsonPath('data.approved_by_user_id', $admin->id);

        $this->assertSame('8.000', $balance->refresh()->quantity_on_hand);
        $this->assertTrue(StockMovement::query()
            ->where('inventory_count_id', $inventoryCountId)
            ->where('movement_type', StockMovementType::Adjustment)
            ->exists());

        $this->getJson("/v1/inventory-counts/{$inventoryCountId}")
            ->assertOk()
            ->assertJsonPath('data.items_count', 1);
        $this->assertTrue(AuditLog::query()
            ->where('entity_type', InventoryCount::class)
            ->where('entity_id', $inventoryCountId)
            ->where('action', 'view')
            ->exists());
    }

    public function test_count_cannot_be_reviewed_with_missing_values_or_duplicated_for_warehouse(): void
    {
        $this->actingAsAdmin();
        [$product, $warehouse] = $this->inventoryContext();

        $created = $this->postJson('/v1/inventory-counts', [
            'warehouse_id' => $warehouse->id,
            'product_ids' => [$product->id],
        ])->assertCreated();

        $inventoryCountId = $created->json('data.id');

        $this->postJson("/v1/inventory-counts/{$inventoryCountId}/review")
            ->assertConflict()
            ->assertJsonPath('code', 'BUSINESS_RULE_VIOLATION');

        $this->postJson('/v1/inventory-counts', [
            'warehouse_id' => $warehouse->id,
            'product_ids' => [$product->id],
        ])->assertConflict();

        $this->postJson("/v1/inventory-counts/{$inventoryCountId}/cancel", [
            'reason' => 'إلغاء الجرد لإعادة البدء',
        ])->assertOk()
            ->assertJsonPath('data.status', InventoryCountStatus::Cancelled->value);
    }

    public function test_stock_movements_can_be_filtered(): void
    {
        $this->actingAsAdmin();
        [$product, $warehouse] = $this->inventoryContext();

        $this->postJson('/v1/inventory/damages', [
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => '1.000',
            'notes' => 'تالف للفحص',
        ])->assertCreated();

        $this->getJson('/v1/inventory/movements?warehouse_id='.$warehouse->id.'&movement_type=damage&search='.urlencode($product->sku))
            ->assertOk()
            ->assertJsonPath('data.0.product_id', $product->id)
            ->assertJsonPath('data.0.movement_type', StockMovementType::Damage->value)
            ->assertJsonStructure(['data', 'links', 'meta']);
    }

    /** @return array{Product, Warehouse, InventoryBalance} */
    private function inventoryContext(string $quantity = '20.000', string $reorderLevel = '5.000'): array
    {
        $product = Product::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $balance = InventoryBalance::factory()->create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity_on_hand' => $quantity,
            'quantity_reserved' => '0.000',
            'reorder_level' => $reorderLevel,
            'average_cost' => '2.5000',
        ]);

        return [$product, $warehouse, $balance];
    }

    private function actingAsAdmin(): User
    {
        $admin = $this->createTestUser();
        $admin->assignRole('Admin');
        Sanctum::actingAs($admin);

        return $admin;
    }
}
