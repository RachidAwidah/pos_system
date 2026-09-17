<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductSearchApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_product_api_searches_by_exact_barcode_sku_and_partial_name(): void
    {
        $admin = User::query()->where('email', config('pos.admin_email'))->firstOrFail();
        $admin->update(['must_change_password' => false]);
        Sanctum::actingAs($admin);
        $product = Product::query()->where('sku', 'DEMO-001')->firstOrFail();

        foreach ([$product->sku, $product->barcode, 'مياه'] as $search) {
            $this->getJson('/v1/products?search='.urlencode($search))
                ->assertOk()
                ->assertJsonPath('data.0.id', $product->id)
                ->assertJsonPath('data.0.sku', 'DEMO-001')
                ->assertJsonStructure(['data' => [['inventory' => ['quantity_available', 'reorder_level']]], 'links', 'meta']);
        }
    }

    public function test_product_api_can_filter_low_stock_products(): void
    {
        $admin = User::query()->where('email', config('pos.admin_email'))->firstOrFail();
        $admin->update(['must_change_password' => false]);
        Sanctum::actingAs($admin);

        $response = $this->getJson('/v1/products?low_stock=1');

        $response->assertOk();
        $this->assertNotEmpty($response->json('data'));
        foreach ($response->json('data') as $product) {
            $this->assertLessThanOrEqual(
                (float) $product['inventory']['reorder_level'],
                (float) $product['inventory']['quantity_on_hand'],
            );
        }
    }

    public function test_cashier_product_search_does_not_expose_costs(): void
    {
        $cashier = User::factory()->create(['must_change_password' => false]);
        $cashier->roles()->sync([Role::query()->where('role_name', 'Cashier')->firstOrFail()->id]);
        Sanctum::actingAs($cashier);

        $response = $this->getJson('/v1/products?per_page=10')->assertOk();

        $response->assertJsonMissingPath('data.0.cost_price');
        $response->assertJsonMissingPath('data.0.inventory.average_cost');
    }
}
