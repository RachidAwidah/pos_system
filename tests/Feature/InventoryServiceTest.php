<?php

namespace Tests\Feature;

use App\Enums\InventoryCountStatus;
use App\Enums\ProductType;
use App\Enums\StockMovementType;
use App\Models\AuditLog;
use App\Models\InventoryBalance;
use App\Models\InventoryCount;
use App\Models\InventoryCountItem;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use DomainException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use LogicException;
use Tests\TestCase;

class InventoryServiceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_receiving_and_selling_stock_updates_balance_cost_ledger_and_audit(): void
    {
        [$product, $warehouse, $user] = $this->seededInventoryContext();
        $service = app(InventoryService::class);

        $receipt = $service->receive($product, $warehouse, '5', $user, unitCost: '1.5000');
        $sale = $service->sell($product, $warehouse, '3', $user);
        $balance = $this->balance($product, $warehouse);

        $this->assertSame(StockMovementType::Purchase, $receipt->movement_type);
        $this->assertSame('25.000', $receipt->balance_after);
        $this->assertSame(StockMovementType::Sale, $sale->movement_type);
        $this->assertSame('-3.000', $sale->quantity_delta);
        $this->assertSame('22.000', $balance->quantity_on_hand);
        $this->assertSame('0.7000', $balance->average_cost);
        $this->assertTrue(AuditLog::query()->where('entity_type', InventoryBalance::class)->where('entity_id', $balance->id)->exists());
        $this->assertTrue(AuditLog::query()->where('entity_type', StockMovement::class)->where('entity_id', $sale->id)->exists());
    }

    public function test_insufficient_or_reserved_stock_cannot_be_sold(): void
    {
        [$product, $warehouse, $user] = $this->seededInventoryContext();
        $service = app(InventoryService::class);

        $service->reserve($product, $warehouse, '18');

        try {
            $service->sell($product, $warehouse, '3', $user);
            $this->fail('Selling reserved inventory should fail.');
        } catch (DomainException $exception) {
            $this->assertSame('This operation would consume reserved inventory.', $exception->getMessage());
        }

        $balance = $this->balance($product, $warehouse);
        $this->assertSame('20.000', $balance->quantity_on_hand);
        $this->assertSame('18.000', $balance->quantity_reserved);

        $service->release($product, $warehouse, '18');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Insufficient inventory for this operation.');
        $service->sell($product, $warehouse, '21', $user);
    }

    public function test_transfer_moves_inventory_between_warehouses_with_linked_ledger_entries(): void
    {
        [$product, $sourceWarehouse, $user] = $this->seededInventoryContext();
        $destinationWarehouse = Warehouse::factory()->create();

        $movements = app(InventoryService::class)->transfer(
            $product,
            $sourceWarehouse,
            $destinationWarehouse,
            '4',
            $user,
        );

        $this->assertSame('16.000', $this->balance($product, $sourceWarehouse)->quantity_on_hand);
        $this->assertSame('4.000', $this->balance($product, $destinationWarehouse)->quantity_on_hand);
        $this->assertSame(StockMovementType::TransferOut, $movements['out']->movement_type);
        $this->assertSame(StockMovementType::TransferIn, $movements['in']->movement_type);
        $this->assertNotNull($movements['out']->transfer_batch_id);
        $this->assertSame($movements['out']->transfer_batch_id, $movements['in']->transfer_batch_id);
    }

    public function test_only_stock_products_can_have_inventory(): void
    {
        [, $warehouse, $user] = $this->seededInventoryContext();
        $serviceProduct = Product::factory()->create(['type' => ProductType::Service]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Inventory can only be changed for stock products.');
        app(InventoryService::class)->receive($serviceProduct, $warehouse, '1', $user);
    }

    public function test_reviewed_inventory_count_creates_adjustment_and_becomes_applied(): void
    {
        [$product, $warehouse, $user] = $this->seededInventoryContext();
        $count = InventoryCount::factory()->create([
            'warehouse_id' => $warehouse->id,
            'started_by_user_id' => $user->id,
            'status' => InventoryCountStatus::Reviewed,
        ]);
        $item = InventoryCountItem::factory()->create([
            'inventory_count_id' => $count->id,
            'product_id' => $product->id,
            'expected_quantity' => '20.000',
            'counted_quantity' => '17.000',
            'difference_quantity' => null,
        ]);

        $appliedCount = app(InventoryService::class)->applyInventoryCount($count, $user);

        $this->assertSame(InventoryCountStatus::Applied, $appliedCount->status);
        $this->assertSame($user->id, $appliedCount->approved_by_user_id);
        $this->assertSame('17.000', $this->balance($product, $warehouse)->quantity_on_hand);
        $this->assertSame('-3.000', $item->refresh()->difference_quantity);
        $this->assertDatabaseHas('stock_movements', [
            'inventory_count_id' => $count->id,
            'movement_type' => StockMovementType::Adjustment->value,
            'quantity_delta' => '-3.000',
        ]);
    }

    public function test_stale_inventory_count_is_rejected_without_partial_changes(): void
    {
        [$product, $warehouse, $user] = $this->seededInventoryContext();
        $count = InventoryCount::factory()->create([
            'warehouse_id' => $warehouse->id,
            'started_by_user_id' => $user->id,
            'status' => InventoryCountStatus::Reviewed,
        ]);
        InventoryCountItem::factory()->create([
            'inventory_count_id' => $count->id,
            'product_id' => $product->id,
            'expected_quantity' => '19.000',
            'counted_quantity' => '18.000',
            'difference_quantity' => null,
        ]);

        try {
            app(InventoryService::class)->applyInventoryCount($count, $user);
            $this->fail('A stale count should fail.');
        } catch (DomainException $exception) {
            $this->assertSame('Inventory changed after the physical count and must be reviewed again.', $exception->getMessage());
        }

        $this->assertSame('20.000', $this->balance($product, $warehouse)->quantity_on_hand);
        $this->assertSame(InventoryCountStatus::Reviewed, $count->refresh()->status);
        $this->assertFalse(StockMovement::query()->whereBelongsTo($count, 'inventoryCount')->exists());
    }

    public function test_stock_movements_are_append_only(): void
    {
        $movement = StockMovement::query()->where('movement_type', StockMovementType::Opening->value)->firstOrFail();

        $this->expectException(LogicException::class);
        $movement->update(['notes' => 'Changed']);
    }

    public function test_stock_movements_cannot_be_deleted(): void
    {
        $movement = StockMovement::query()->where('movement_type', StockMovementType::Opening->value)->firstOrFail();

        $this->expectException(LogicException::class);
        $movement->delete();
    }

    /** @return array{Product, Warehouse, User} */
    private function seededInventoryContext(): array
    {
        return [
            Product::query()->where('sku', 'SKU-001')->firstOrFail(),
            Warehouse::query()->where('code', 'MAIN')->firstOrFail(),
            User::query()->firstOrFail(),
        ];
    }

    private function balance(Product $product, Warehouse $warehouse): InventoryBalance
    {
        return InventoryBalance::query()
            ->whereBelongsTo($product)
            ->whereBelongsTo($warehouse)
            ->firstOrFail();
    }
}
