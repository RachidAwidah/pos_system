<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Contact;
use App\Models\Product;
use Illuminate\View\View;

class PosController extends Controller
{
    public function __invoke(): View
    {
        $products = Product::query()
            ->with(['category:id,category_name', 'tax:id,tax_percentage', 'unit:id,symbol,decimal_places'])
            ->orderBy('product_name')
            ->get()
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->product_name,
                'sku' => $product->sku,
                'barcode' => $product->barcode,
                'price' => (float) $product->price,
                'quantity' => (float) $product->quantity,
                'unit' => $product->unit->symbol,
                'type' => $product->type->value,
                'tracks_inventory' => $product->type->tracksInventory(),
                'category_id' => $product->category_id,
                'category' => $product->category->category_name,
                'tax_rate' => (float) ($product->tax?->tax_percentage ?? 0),
            ]);

        return view('pos.index', [
            'products' => $products,
            'categories' => Category::query()->orderBy('category_name')->get(['id', 'category_name']),
            'customers' => Contact::query()->where('type', 'customer')->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
