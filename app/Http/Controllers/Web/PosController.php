<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Register;
use App\Models\Shift;
use App\Models\Warehouse;
use App\Services\CategoryTreeService;
use Illuminate\View\View;

class PosController extends Controller
{
    public function __invoke(CategoryTreeService $categoryTree): View
    {
        $openShift = Shift::query()
            ->with('register.warehouse')
            ->where('opened_by_user_id', auth()->id())
            ->where('status', 'open')
            ->first();
        $warehouse = $openShift?->register->warehouse
            ?? Warehouse::query()->active()->defaultWarehouse()->firstOrFail();
        $tree = $categoryTree->tree();
        $categories = $categoryTree->flatten($tree);
        $categoryPaths = $categories->mapWithKeys(
            fn (Category $category): array => [$category->id => $category->path_ids],
        );

        $products = Product::query()
            ->with([
                'category:id,category_name',
                'tax:id,tax_percentage',
                'unit:id,symbol,decimal_places',
                'inventoryBalances' => fn ($query) => $query->whereBelongsTo($warehouse),
            ])
            ->orderBy('product_name')
            ->get()
            ->map(function (Product $product) use ($categoryPaths): array {
                $balance = $product->inventoryBalances->first();
                $availableQuantity = bcsub(
                    (string) ($balance?->quantity_on_hand ?? '0.000'),
                    (string) ($balance?->quantity_reserved ?? '0.000'),
                    3,
                );

                return [
                    'id' => $product->id,
                    'name' => $product->product_name,
                    'sku' => $product->sku,
                    'barcode' => $product->barcode,
                    'image_url' => $product->image_url,
                    'price' => (float) $product->price,
                    'quantity' => (float) $availableQuantity,
                    'unit' => $product->unit->symbol,
                    'type' => $product->type->value,
                    'tracks_inventory' => $product->type->tracksInventory(),
                    'category_id' => $product->category_id,
                    'category_path_ids' => $categoryPaths->get($product->category_id, [$product->category_id]),
                    'category' => $product->category->category_name,
                    'tax_rate' => (float) ($product->tax?->tax_percentage ?? 0),
                ];
            });

        return view('pos.index', [
            'products' => $products,
            'categories' => $categories,
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name', 'balance', 'credit_limit', 'loyalty_points']),
            'paymentMethods' => PaymentMethod::query()->where('is_active', true)->orderBy('name')->get(),
            'registers' => Register::query()->whereBelongsTo($warehouse)->where('is_active', true)->orderBy('name')->get(),
            'openShift' => $openShift,
        ]);
    }
}
