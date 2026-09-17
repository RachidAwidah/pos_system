<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductImageTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_can_upload_a_product_image(): void
    {
        Storage::fake('public');
        $this->authenticateAdmin();

        $response = $this->post('/v1/products', [
            'product_name' => 'Product With Image',
            'sku' => 'IMAGE-SKU-001',
            'barcode' => '5449000000996',
            'type' => 'non_stock',
            'unit_id' => Unit::query()->firstOrFail()->id,
            'category_id' => Category::query()->firstOrFail()->id,
            'cost_price' => '1.00',
            'price' => '2.00',
            'image' => UploadedFile::fake()->image('product.jpg', 300, 300),
        ], ['Accept' => 'application/json']);

        $response->assertCreated()->assertJsonPath('data.sku', 'IMAGE-SKU-001');
        $product = Product::query()->findOrFail($response->json('data.id'));
        $this->assertNotNull($product->image);
        Storage::disk('public')->assertExists($product->image);
        $this->assertStringContainsString('/storage/products/', $response->json('data.image_url'));
    }

    public function test_admin_can_fetch_and_store_a_trusted_image_by_barcode(): void
    {
        Storage::fake('public');
        Http::preventStrayRequests();
        Http::fake([
            'world.openfoodfacts.org/api/v2/product/*' => Http::response([
                'status' => 1,
                'product' => [
                    'image_front_url' => 'https://images.openfoodfacts.org/images/products/demo/front.jpg',
                ],
            ]),
            'images.openfoodfacts.org/*' => Http::response('jpeg-image-bytes', 200, ['Content-Type' => 'image/jpeg']),
        ]);
        $this->authenticateAdmin();
        $product = Product::query()->whereNotNull('barcode')->firstOrFail();

        $response = $this->postJson("/v1/products/{$product->id}/fetch-image");

        $response->assertOk();
        $product->refresh();
        Storage::disk('public')->assertExists($product->image);
        $this->assertStringEndsWith('.jpg', $product->image);
        Http::assertSentCount(2);
    }

    private function authenticateAdmin(): void
    {
        $admin = User::query()->where('email', config('pos.admin_email'))->firstOrFail();
        $admin->update(['must_change_password' => false]);
        Sanctum::actingAs($admin);

    }
}
