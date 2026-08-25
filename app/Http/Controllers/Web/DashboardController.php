<?php

namespace App\Http\Controllers\Web;

use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\Order;
use App\Models\Product;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $todayOrders = Order::query()->whereDate('order_date', today());

        return view('dashboard', [
            'salesToday' => (clone $todayOrders)->where('status', 'completed')->sum('final_amount'),
            'ordersToday' => (clone $todayOrders)->count(),
            'productsCount' => Product::query()->count(),
            'customersCount' => Contact::query()->where('type', 'customer')->count(),
            'lowStockProducts' => Product::query()
                ->with('category:id,category_name')
                ->where('type', ProductType::Stock->value)
                ->whereColumn('quantity', '<=', 'reorder_level')
                ->orderBy('quantity')
                ->limit(6)
                ->get(),
            'recentOrders' => Order::query()
                ->with(['contact:id,name', 'user:id,full_name'])
                ->latest('order_date')
                ->limit(6)
                ->get(),
        ]);
    }
}
