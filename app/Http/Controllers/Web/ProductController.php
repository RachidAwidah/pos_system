<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(): View
    {
        return view('products.index', [
            'products' => Product::query()->with(['category:id,category_name', 'tax:id,tax_percentage', 'unit:id,symbol,decimal_places'])->orderBy('product_name')->paginate(20),
        ]);
    }
}
