<?php

namespace App\Http\Controllers\Web;

use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\InventoryBalance;
use App\Models\Order;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\ReportService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(public ReportService $reportService) {}

    public function __invoke(): View
    {
        $warehouse = Warehouse::query()->active()->defaultWarehouse()->firstOrFail();
        $filters = [
            'from' => now()->startOfMonth()->toDateString(),
            'to' => now()->toDateString(),
            'warehouse_id' => $warehouse->id,
            'limit' => 5,
        ];

        return view('dashboard', [
            'overview' => $this->reportService->overview($filters),
            'profit' => $this->reportService->profit($filters),
            'productsCount' => Product::query()->count(),
            'customersCount' => Customer::query()->count(),
            'lowStockProducts' => Product::query()
                ->with([
                    'category:id,category_name',
                    'inventoryBalances' => fn ($query) => $query->whereBelongsTo($warehouse),
                ])
                ->where('type', ProductType::Stock->value)
                ->whereHas('inventoryBalances', function ($query) use ($warehouse): void {
                    $query->whereBelongsTo($warehouse)
                        ->whereColumn('quantity_on_hand', '<=', 'reorder_level');
                })
                ->orderBy(
                    InventoryBalance::query()
                        ->select('quantity_on_hand')
                        ->whereColumn('product_id', 'products.id')
                        ->whereBelongsTo($warehouse)
                        ->limit(1),
                )
                ->limit(6)
                ->get()
                ->each(function (Product $product): void {
                    $product->setAttribute('quantity', $product->inventoryBalances->first()?->quantity_on_hand ?? '0.000');
                }),
            'recentOrders' => Order::query()
                ->with(['customer:id,name', 'user:id,full_name'])
                ->latest('order_date')
                ->limit(6)
                ->get(),
        ]);
    }
}
