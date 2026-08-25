<?php

namespace Tests\Feature;

use App\Enums\ProductType;
use App\Models\Product;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductCatalogStructureTest extends TestCase
{
    use DatabaseTransactions;

    public function test_products_use_separate_sku_barcode_unit_and_type_fields(): void
    {
        $product = Product::query()
            ->with('unit')
            ->where('sku', 'SKU-001')
            ->firstOrFail();

        $this->assertSame('100000000001', $product->barcode);
        $this->assertSame('pc', $product->unit->symbol);
        $this->assertSame(ProductType::Stock, $product->type);
        $this->assertTrue($product->type->tracksInventory());
    }

    public function test_catalog_schema_uses_units_and_products_for_services(): void
    {
        $this->assertTrue(Schema::hasTable('units'));
        $this->assertTrue(Schema::hasColumns('products', ['sku', 'barcode', 'unit_id', 'type']));
        $this->assertFalse(Schema::hasColumn('products', 'unit'));
        $this->assertFalse(Schema::hasColumn('products', 'is_stockable'));
        $this->assertFalse(Schema::hasTable('services'));
        $this->assertFalse(Schema::hasColumn('orders_details', 'service_id'));
    }
}
