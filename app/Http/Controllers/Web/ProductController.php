<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(): View
    {
        $warehouse = Warehouse::query()->active()->defaultWarehouse()->firstOrFail();
        $products = Product::query()
            ->with([
                'category:id,category_name',
                'tax:id,tax_percentage',
                'unit:id,symbol,decimal_places',
                'inventoryBalances' => fn ($query) => $query->whereBelongsTo($warehouse),
            ])
            ->orderBy('product_name')
            ->paginate(20);

        $products->through(function (Product $product): Product {
            $balance = $product->inventoryBalances->first();
            $product->setAttribute('quantity', $balance?->quantity_on_hand ?? '0.000');
            $product->setAttribute('reorder_level', $balance?->reorder_level ?? '0.000');

            return $product;
        });

        return view('products.index', [
            'products' => $products,
        ]);
    }
}
