<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CatalogCrudApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_can_manage_categories_and_products_with_audits(): void
    {
        $admin = User::query()->where('email', config('pos.admin_email'))->firstOrFail();
        $admin->update(['must_change_password' => false]);
        Sanctum::actingAs($admin);

        $categoryResponse = $this->postJson('/v1/categories', [
            'category_name' => 'API Category',
        ])->assertCreated()->assertJsonPath('data.category_name', 'API Category');
        $category = Category::query()->findOrFail($categoryResponse->json('data.id'));

        $productResponse = $this->postJson('/v1/products', [
            'product_name' => 'API Product',
            'sku' => 'API-SKU-001',
            'barcode' => '990000000001',
            'type' => 'non_stock',
            'unit_id' => Unit::query()->firstOrFail()->id,
            'category_id' => $category->id,
            'cost_price' => '2.50',
            'price' => '4.00',
        ])->assertCreated()->assertJsonPath('data.sku', 'API-SKU-001');
        $product = Product::query()->findOrFail($productResponse->json('data.id'));

        $this->getJson("/v1/products/{$product->id}")->assertOk();
        $this->patchJson("/v1/products/{$product->id}", ['price' => '4.50'])
            ->assertOk()
            ->assertJsonPath('data.price', '4.50');
        $this->deleteJson("/v1/products/{$product->id}")->assertNoContent();
        $this->assertSoftDeleted($product);
        $this->deleteJson("/v1/categories/{$category->id}")
            ->assertConflict()
            ->assertJsonPath('code', 'BUSINESS_RULE_VIOLATION');

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => Product::class,
            'entity_id' => $product->id,
            'action' => 'delete',
        ]);
    }
}
