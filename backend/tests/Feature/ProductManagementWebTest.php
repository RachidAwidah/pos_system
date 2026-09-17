<?php

namespace Tests\Feature;

use App\Enums\ProductType;
use App\Enums\StockMovementType;
use App\Models\Category;
use App\Models\InventoryBalance;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\Tax;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductManagementWebTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_can_open_the_product_management_and_compact_category_tree_screens(): void
    {
        $admin = $this->admin();
        $root = Category::factory()->create(['category_name' => 'الأغذية', 'parent_id' => null]);
        Category::factory()->create(['category_name' => 'المشروبات', 'parent_id' => $root->id]);

        $this->actingAs($admin)->get(route('products.index'))
            ->assertOk()->assertSee('إدارة المنتجات')->assertSee('شجرة التصنيفات')->assertSee('المشروبات');

        $this->actingAs($admin)->get(route('categories.index'))
            ->assertOk()->assertSee('هيكل التصنيفات')->assertSee('data-tree-toggle', escape: false);
    }

    public function test_admin_can_create_a_stock_product_with_an_opening_inventory_balance(): void
    {
        $admin = $this->admin();
        $warehouse = Warehouse::query()->active()->defaultWarehouse()->firstOrFail();
        $sku = 'WEB-STOCK-'.Str::upper(Str::random(8));

        $response = $this->actingAs($admin)->post(route('products.store'), [
            ...$this->productPayload($sku),
            'type' => ProductType::Stock->value,
            'warehouse_id' => $warehouse->id,
            'opening_quantity' => '15.500',
            'reorder_level' => '4.000',
        ]);

        $product = Product::query()->where('sku', $sku)->firstOrFail();
        $response->assertRedirect(route('products.edit', $product));

        $balance = InventoryBalance::query()->whereBelongsTo($product)->whereBelongsTo($warehouse)->firstOrFail();
        $this->assertSame('15.500', $balance->quantity_on_hand);
        $this->assertSame('4.000', $balance->reorder_level);
        $this->assertSame('2.5000', $balance->average_cost);

        $movement = StockMovement::query()->whereBelongsTo($product)
            ->where('movement_type', StockMovementType::Opening->value)->firstOrFail();
        $this->assertSame('15.500', $movement->quantity_delta);
        $this->assertTrue($movement->user->is($admin));
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'create', 'entity_type' => Product::class, 'entity_id' => $product->id,
        ]);
    }

    public function test_editing_a_product_updates_its_reorder_level_without_changing_stock_quantity(): void
    {
        $admin = $this->admin();
        $warehouse = Warehouse::query()->active()->defaultWarehouse()->firstOrFail();
        $product = Product::factory()->create();
        InventoryBalance::factory()->create([
            'product_id' => $product->id, 'warehouse_id' => $warehouse->id,
            'quantity_on_hand' => '9.000', 'quantity_reserved' => '0.000', 'reorder_level' => '2.000',
        ]);

        $this->actingAs($admin)->put(route('products.update', $product), [
            ...$this->productPayload($product->sku),
            'product_name' => 'منتج محدث',
            'type' => ProductType::Stock->value,
            'warehouse_id' => $warehouse->id,
            'reorder_level' => '5.000',
        ])->assertRedirect(route('products.edit', $product));

        $balance = InventoryBalance::query()->whereBelongsTo($product)->whereBelongsTo($warehouse)->firstOrFail();
        $this->assertSame('9.000', $balance->quantity_on_hand);
        $this->assertSame('5.000', $balance->reorder_level);
        $this->assertSame('منتج محدث', $product->refresh()->product_name);
        $this->assertDatabaseMissing('stock_movements', ['product_id' => $product->id]);
    }

    public function test_user_without_price_permission_cannot_modify_prices_with_a_crafted_request(): void
    {
        $user = User::factory()->create(['must_change_password' => false]);
        $role = Role::factory()->create();
        $role->permissions()->sync(Permission::query()->whereIn('permission_key', ['products.view', 'products.edit'])->pluck('id'));
        $user->assignRole($role);
        $product = Product::factory()->create(['cost_price' => '10.00', 'price' => '15.00']);
        $warehouse = Warehouse::query()->active()->defaultWarehouse()->firstOrFail();

        $response = $this->actingAs($user)->from(route('products.edit', $product))->put(route('products.update', $product), [
            ...$this->productPayload($product->sku),
            'type' => ProductType::Stock->value,
            'warehouse_id' => $warehouse->id,
            'reorder_level' => '0.000',
            'cost_price' => '0.01',
            'price' => '0.02',
        ]);

        $response->assertRedirect(route('products.edit', $product))->assertSessionHasErrors(['cost_price', 'price']);
        $this->assertSame('10.00', $product->refresh()->cost_price);
        $this->assertSame('15.00', $product->price);
    }

    public function test_filtering_by_a_parent_category_includes_products_from_descendants(): void
    {
        $admin = $this->admin();
        $root = Category::factory()->create(['category_name' => 'Root', 'parent_id' => null]);
        $child = Category::factory()->create(['category_name' => 'Child', 'parent_id' => $root->id]);
        $product = Product::factory()->create(['category_id' => $child->id, 'product_name' => 'Nested Product']);

        $this->actingAs($admin)->get(route('products.index', ['category_id' => $root->id]))
            ->assertOk()->assertSee($product->product_name);
    }

    /** @return array<string, string|null> */
    private function productPayload(string $sku): array
    {
        return [
            'product_name' => 'اختبار منتج',
            'sku' => $sku,
            'barcode' => null,
            'unit_id' => Unit::query()->firstOrFail()->id,
            'category_id' => Category::query()->firstOrFail()->id,
            'tax_id' => Tax::query()->firstOrFail()->id,
            'cost_price' => '2.50',
            'price' => '4.00',
            'description' => 'منتج لاختبار شاشة الإدارة.',
        ];
    }

    private function admin(): User
    {
        $admin = User::query()->where('email', config('pos.admin_email'))->firstOrFail();
        $admin->update(['must_change_password' => false]);

        return $admin;
    }
}
