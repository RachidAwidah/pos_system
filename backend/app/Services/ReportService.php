<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\SalesReturnStatus;
use App\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportService
{
    /** @param array<string, mixed> $filters */
    public function overview(array $filters): array
    {
        return [
            'period' => $this->period($filters),
            'summary' => $this->summary($filters),
            'trend' => $this->trend($filters),
            'top_products' => $this->products($filters, min((int) ($filters['limit'] ?? 5), 10)),
            'top_customers' => $this->customers($filters, min((int) ($filters['limit'] ?? 5), 10)),
            'payment_methods' => $this->paymentMethods($filters),
        ];
    }

    /** @param array<string, mixed> $filters */
    public function profit(array $filters): array
    {
        $current = $this->summary($filters);
        $from = CarbonImmutable::parse($filters['from'])->startOfDay();
        $to = CarbonImmutable::parse($filters['to'])->endOfDay();
        $days = $from->diffInDays($to) + 1;
        $comparisonFilters = $filters;
        $comparisonFilters['from'] = $from->subDays($days)->toDateString();
        $comparisonFilters['to'] = $from->subDay()->toDateString();
        $previous = $this->summary($comparisonFilters);

        return [
            'period' => $this->period($filters),
            'summary' => $current,
            'previous_period' => [...$this->period($comparisonFilters), 'summary' => $previous],
            'changes' => [
                'net_sales_percentage' => $this->percentageChange($previous['net_sales'], $current['net_sales']),
                'gross_profit_percentage' => $this->percentageChange($previous['gross_profit'], $current['gross_profit']),
                'orders_percentage' => $this->percentageChange((string) $previous['orders_count'], (string) $current['orders_count']),
            ],
            'trend' => $this->trend($filters),
        ];
    }

    /** @param array<string, mixed> $filters */
    public function summary(array $filters): array
    {
        [$from, $to] = $this->dateBounds($filters);
        $orders = $this->ordersQuery($filters)
            ->selectRaw('COUNT(*) as orders_count')
            ->selectRaw('COALESCE(SUM(final_amount), 0) as total_sales')
            ->first();
        $items = $this->salesItemsQuery($filters)
            ->selectRaw('COALESCE(SUM(oi.subtotal_amount), 0) as gross_sales')
            ->selectRaw('COALESCE(SUM(oi.discount_amount), 0) as discounts')
            ->selectRaw('COALESCE(SUM(oi.tax_amount), 0) as taxes')
            ->selectRaw('COALESCE(SUM(oi.quantity * oi.cost_price_at_sale), 0) as sold_cogs')
            ->selectRaw('COALESCE(SUM(oi.quantity), 0) as sold_quantity')
            ->first();
        $returns = DB::table('sales_return_items as sri')
            ->join('sales_returns as sr', 'sr.id', '=', 'sri.sales_return_id')
            ->join('orders as ro', 'ro.id', '=', 'sr.order_id')
            ->join('order_items as oi', 'oi.id', '=', 'sri.order_item_id')
            ->where('sr.status', SalesReturnStatus::Completed->value)
            ->whereBetween('sr.returned_at', [$from, $to])
            ->when($filters['warehouse_id'] ?? null, fn (Builder $query, string $warehouseId) => $query->where('sr.warehouse_id', $warehouseId))
            ->when($filters['customer_id'] ?? null, fn (Builder $query, string $customerId) => $query->where('ro.customer_id', $customerId))
            ->when($filters['user_id'] ?? null, fn (Builder $query, string $userId) => $query->where('ro.user_id', $userId))
            ->when($filters['payment_method_id'] ?? null, fn (Builder $query, string $methodId) => $query->whereExists(fn (Builder $payments) => $payments->from('payments as rp')->whereColumn('rp.order_id', 'ro.id')->where('rp.payment_method_id', $methodId)))
            ->selectRaw('COUNT(DISTINCT sr.id) as returns_count')
            ->selectRaw('COALESCE(SUM(sri.subtotal_amount), 0) as returned_sales')
            ->selectRaw('COALESCE(SUM(sri.tax_amount), 0) as returned_taxes')
            ->selectRaw('COALESCE(SUM(sri.refund_amount), 0) as refunds')
            ->selectRaw('COALESCE(SUM(sri.quantity), 0) as returned_quantity')
            ->selectRaw('COALESCE(SUM(CASE WHEN sri.restock = 1 THEN sri.quantity * oi.cost_price_at_sale ELSE 0 END), 0) as returned_cogs')
            ->first();

        $netSales = bcsub(bcsub((string) $items->gross_sales, (string) $items->discounts, 2), (string) $returns->returned_sales, 2);
        $netTax = bcsub((string) $items->taxes, (string) $returns->returned_taxes, 2);
        $netTotal = bcsub((string) $orders->total_sales, (string) $returns->refunds, 2);
        $netCogs = bcsub((string) $items->sold_cogs, (string) $returns->returned_cogs, 4);
        $grossProfit = bcsub($netSales, $netCogs, 2);
        $orderCount = (int) $orders->orders_count;

        return [
            'orders_count' => $orderCount,
            'returns_count' => (int) $returns->returns_count,
            'gross_sales' => $this->money((string) $items->gross_sales),
            'discounts' => $this->money((string) $items->discounts),
            'refunds' => $this->money((string) $returns->refunds),
            'net_sales' => $this->money($netSales),
            'net_tax' => $this->money($netTax),
            'net_total' => $this->money($netTotal),
            'cost_of_goods_sold' => $this->money($netCogs),
            'gross_profit' => $this->money($grossProfit),
            'gross_margin_percentage' => bccomp($netSales, '0.00', 2) === 1
                ? number_format(((float) $grossProfit / (float) $netSales) * 100, 2, '.', '')
                : '0.00',
            'average_order_value' => $orderCount > 0 ? $this->money(bcdiv($netTotal, (string) $orderCount, 4)) : '0.00',
            'net_quantity' => bcsub((string) $items->sold_quantity, (string) $returns->returned_quantity, 3),
        ];
    }

    /** @param array<string, mixed> $filters */
    public function products(array $filters, ?int $limit = null): array
    {
        [$from, $to] = $this->dateBounds($filters);
        $returns = DB::table('sales_return_items as sri')
            ->join('sales_returns as sr', 'sr.id', '=', 'sri.sales_return_id')
            ->join('orders as ro', 'ro.id', '=', 'sr.order_id')
            ->join('order_items as roi', 'roi.id', '=', 'sri.order_item_id')
            ->where('sr.status', SalesReturnStatus::Completed->value)
            ->whereBetween('sr.returned_at', [$from, $to])
            ->when($filters['warehouse_id'] ?? null, fn (Builder $query, string $warehouseId) => $query->where('sr.warehouse_id', $warehouseId))
            ->when($filters['customer_id'] ?? null, fn (Builder $query, string $customerId) => $query->where('ro.customer_id', $customerId))
            ->when($filters['user_id'] ?? null, fn (Builder $query, string $userId) => $query->where('ro.user_id', $userId))
            ->when($filters['payment_method_id'] ?? null, fn (Builder $query, string $methodId) => $query->whereExists(fn (Builder $payments) => $payments->from('payments as rp')->whereColumn('rp.order_id', 'ro.id')->where('rp.payment_method_id', $methodId)))
            ->groupBy('sri.product_id')
            ->select('sri.product_id')
            ->selectRaw('SUM(sri.quantity) as returned_quantity')
            ->selectRaw('SUM(sri.subtotal_amount) as returned_sales')
            ->selectRaw('SUM(CASE WHEN sri.restock = 1 THEN sri.quantity * roi.cost_price_at_sale ELSE 0 END) as returned_cogs');

        $query = $this->salesItemsQuery($filters)
            ->leftJoinSub($returns, 'returns', fn ($join) => $join->on('returns.product_id', '=', 'oi.product_id'))
            ->groupBy('oi.product_id')
            ->select('oi.product_id')
            ->selectRaw('MAX(oi.product_name) as product_name')
            ->selectRaw('MAX(oi.sku) as sku')
            ->selectRaw('SUM(oi.quantity) - COALESCE(MAX(returns.returned_quantity), 0) as net_quantity')
            ->selectRaw('SUM(oi.subtotal_amount - oi.discount_amount) - COALESCE(MAX(returns.returned_sales), 0) as net_sales')
            ->selectRaw('SUM(oi.quantity * oi.cost_price_at_sale) - COALESCE(MAX(returns.returned_cogs), 0) as cogs')
            ->orderByDesc('net_quantity');
        $rows = $limit === null ? $query->get() : $query->limit($limit)->get();

        return $rows->map(function (object $row): array {
            $profit = bcsub((string) $row->net_sales, (string) $row->cogs, 2);

            return [
                'product_id' => $row->product_id,
                'product_name' => $row->product_name,
                'sku' => $row->sku,
                'net_quantity' => number_format((float) $row->net_quantity, 3, '.', ''),
                'net_sales' => $this->money((string) $row->net_sales),
                'cost_of_goods_sold' => $this->money((string) $row->cogs),
                'gross_profit' => $this->money($profit),
                'gross_margin_percentage' => bccomp((string) $row->net_sales, '0.00', 2) === 1
                    ? number_format(((float) $profit / (float) $row->net_sales) * 100, 2, '.', '')
                    : '0.00',
            ];
        })->all();
    }

    /** @param array<string, mixed> $filters */
    public function customers(array $filters, ?int $limit = null): array
    {
        [$from, $to] = $this->dateBounds($filters);
        $returns = DB::table('sales_return_items as sri')
            ->join('sales_returns as sr', 'sr.id', '=', 'sri.sales_return_id')
            ->join('orders as ro', 'ro.id', '=', 'sr.order_id')
            ->join('order_items as roi', 'roi.id', '=', 'sri.order_item_id')
            ->whereNotNull('ro.customer_id')
            ->where('sr.status', SalesReturnStatus::Completed->value)
            ->whereBetween('sr.returned_at', [$from, $to])
            ->when($filters['warehouse_id'] ?? null, fn (Builder $query, string $warehouseId) => $query->where('sr.warehouse_id', $warehouseId))
            ->when($filters['customer_id'] ?? null, fn (Builder $query, string $customerId) => $query->where('ro.customer_id', $customerId))
            ->when($filters['user_id'] ?? null, fn (Builder $query, string $userId) => $query->where('ro.user_id', $userId))
            ->when($filters['payment_method_id'] ?? null, fn (Builder $query, string $methodId) => $query->whereExists(fn (Builder $payments) => $payments->from('payments as rp')->whereColumn('rp.order_id', 'ro.id')->where('rp.payment_method_id', $methodId)))
            ->groupBy('ro.customer_id')
            ->select('ro.customer_id')
            ->selectRaw('SUM(sri.subtotal_amount) as returned_sales')
            ->selectRaw('SUM(CASE WHEN sri.restock = 1 THEN sri.quantity * roi.cost_price_at_sale ELSE 0 END) as returned_cogs');

        $query = $this->salesItemsQuery($filters)
            ->join('customers as c', 'c.id', '=', 'o.customer_id')
            ->leftJoinSub($returns, 'returns', fn ($join) => $join->on('returns.customer_id', '=', 'o.customer_id'))
            ->whereNotNull('o.customer_id')
            ->groupBy('o.customer_id', 'c.name', 'c.phone')
            ->select('o.customer_id', 'c.name', 'c.phone')
            ->selectRaw('COUNT(DISTINCT o.id) as orders_count')
            ->selectRaw('MAX(o.order_date) as last_order_at')
            ->selectRaw('SUM(oi.subtotal_amount - oi.discount_amount) - COALESCE(MAX(returns.returned_sales), 0) as net_sales')
            ->selectRaw('SUM(oi.quantity * oi.cost_price_at_sale) - COALESCE(MAX(returns.returned_cogs), 0) as cogs')
            ->orderByDesc('net_sales');
        $rows = $limit === null ? $query->get() : $query->limit($limit)->get();

        return $rows->map(function (object $row): array {
            $profit = bcsub((string) $row->net_sales, (string) $row->cogs, 2);

            return [
                'customer_id' => $row->customer_id,
                'name' => $row->name,
                'phone' => $row->phone,
                'orders_count' => (int) $row->orders_count,
                'last_order_at' => $row->last_order_at,
                'net_sales' => $this->money((string) $row->net_sales),
                'cost_of_goods_sold' => $this->money((string) $row->cogs),
                'gross_profit' => $this->money($profit),
                'average_order_value' => (int) $row->orders_count > 0
                    ? $this->money(bcdiv((string) $row->net_sales, (string) $row->orders_count, 4))
                    : '0.00',
            ];
        })->all();
    }

    /** @param array<string, mixed> $filters */
    public function sales(array $filters): LengthAwarePaginator
    {
        [$from, $to] = $this->dateBounds($filters);

        return Order::query()
            ->with(['customer:id,name,phone', 'user:id,full_name', 'warehouse:id,name', 'items', 'payments.paymentMethod'])
            ->withCount('items')
            ->whereBetween('order_date', [$from, $to])
            ->when($filters['warehouse_id'] ?? null, fn (EloquentBuilder $query, string $warehouseId) => $query->where('warehouse_id', $warehouseId))
            ->when($filters['customer_id'] ?? null, fn (EloquentBuilder $query, string $customerId) => $query->where('customer_id', $customerId))
            ->when($filters['user_id'] ?? null, fn (EloquentBuilder $query, string $userId) => $query->where('user_id', $userId))
            ->when($filters['status'] ?? null, fn (EloquentBuilder $query, string $status) => $query->where('status', $status))
            ->when($filters['payment_method_id'] ?? null, fn (EloquentBuilder $query, string $methodId) => $query->whereHas('payments', fn (EloquentBuilder $payments) => $payments->where('payment_method_id', $methodId)))
            ->when($filters['search'] ?? null, function (EloquentBuilder $query, string $search): void {
                $query->where(function (EloquentBuilder $searchQuery) use ($search): void {
                    $searchQuery->where('invoice_number', 'like', '%'.$search.'%')
                        ->orWhereIn('customer_id', DB::table('customers')->select('id')->where('name', 'like', '%'.$search.'%'));
                });
            })
            ->latest('order_date')
            ->paginate((int) ($filters['per_page'] ?? 25))
            ->withQueryString();
    }

    /** @param array<string, mixed> $filters */
    private function trend(array $filters): array
    {
        $sales = $this->salesItemsQuery($filters)
            ->groupByRaw('DATE(o.order_date)')
            ->selectRaw('DATE(o.order_date) as report_date')
            ->selectRaw('SUM(oi.subtotal_amount - oi.discount_amount) as net_sales')
            ->selectRaw('SUM(oi.quantity * oi.cost_price_at_sale) as cogs')
            ->get()
            ->keyBy('report_date');
        [$from, $to] = $this->dateBounds($filters);
        $returns = DB::table('sales_return_items as sri')
            ->join('sales_returns as sr', 'sr.id', '=', 'sri.sales_return_id')
            ->join('orders as ro', 'ro.id', '=', 'sr.order_id')
            ->join('order_items as oi', 'oi.id', '=', 'sri.order_item_id')
            ->where('sr.status', SalesReturnStatus::Completed->value)
            ->whereBetween('sr.returned_at', [$from, $to])
            ->when($filters['warehouse_id'] ?? null, fn (Builder $query, string $warehouseId) => $query->where('sr.warehouse_id', $warehouseId))
            ->when($filters['customer_id'] ?? null, fn (Builder $query, string $customerId) => $query->where('ro.customer_id', $customerId))
            ->when($filters['user_id'] ?? null, fn (Builder $query, string $userId) => $query->where('ro.user_id', $userId))
            ->when($filters['payment_method_id'] ?? null, fn (Builder $query, string $methodId) => $query->whereExists(fn (Builder $payments) => $payments->from('payments as rp')->whereColumn('rp.order_id', 'ro.id')->where('rp.payment_method_id', $methodId)))
            ->groupByRaw('DATE(sr.returned_at)')
            ->selectRaw('DATE(sr.returned_at) as report_date')
            ->selectRaw('SUM(sri.subtotal_amount) as returned_sales')
            ->selectRaw('SUM(CASE WHEN sri.restock = 1 THEN sri.quantity * oi.cost_price_at_sale ELSE 0 END) as returned_cogs')
            ->get()
            ->keyBy('report_date');

        return Collection::make($sales->keys()->merge($returns->keys())->unique()->sort()->values())
            ->map(function (string $date) use ($sales, $returns): array {
                $sale = $sales->get($date);
                $return = $returns->get($date);
                $netSales = bcsub((string) ($sale->net_sales ?? 0), (string) ($return->returned_sales ?? 0), 2);
                $cogs = bcsub((string) ($sale->cogs ?? 0), (string) ($return->returned_cogs ?? 0), 4);

                return [
                    'date' => $date,
                    'net_sales' => $this->money($netSales),
                    'gross_profit' => $this->money(bcsub($netSales, $cogs, 2)),
                ];
            })->all();
    }

    /** @param array<string, mixed> $filters */
    private function paymentMethods(array $filters): array
    {
        [$from, $to] = $this->dateBounds($filters);

        return DB::table('payments as p')
            ->join('payment_methods as pm', 'pm.id', '=', 'p.payment_method_id')
            ->join('orders as o', 'o.id', '=', 'p.order_id')
            ->where('p.status', PaymentStatus::Completed->value)
            ->whereBetween('p.paid_at', [$from, $to])
            ->when($filters['warehouse_id'] ?? null, fn (Builder $query, string $warehouseId) => $query->where('o.warehouse_id', $warehouseId))
            ->when($filters['customer_id'] ?? null, fn (Builder $query, string $customerId) => $query->where('o.customer_id', $customerId))
            ->when($filters['user_id'] ?? null, fn (Builder $query, string $userId) => $query->where('o.user_id', $userId))
            ->when($filters['payment_method_id'] ?? null, fn (Builder $query, string $methodId) => $query->where('p.payment_method_id', $methodId))
            ->groupBy('pm.id', 'pm.name', 'pm.code')
            ->select('pm.id', 'pm.name', 'pm.code')
            ->selectRaw('SUM(CASE WHEN p.type = ? THEN p.amount ELSE -p.amount END) as net_amount', [PaymentType::Payment->value])
            ->orderByDesc('net_amount')
            ->get()
            ->map(fn (object $row): array => [
                'payment_method_id' => $row->id,
                'name' => $row->name,
                'code' => $row->code,
                'net_amount' => $this->money((string) $row->net_amount),
            ])->all();
    }

    /** @param array<string, mixed> $filters */
    private function ordersQuery(array $filters): Builder
    {
        [$from, $to] = $this->dateBounds($filters);

        return DB::table('orders as o')
            ->whereNull('o.deleted_at')
            ->whereIn('o.status', $this->reportableStatuses())
            ->whereBetween('o.order_date', [$from, $to])
            ->when($filters['warehouse_id'] ?? null, fn (Builder $query, string $warehouseId) => $query->where('o.warehouse_id', $warehouseId))
            ->when($filters['customer_id'] ?? null, fn (Builder $query, string $customerId) => $query->where('o.customer_id', $customerId))
            ->when($filters['user_id'] ?? null, fn (Builder $query, string $userId) => $query->where('o.user_id', $userId))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('o.status', $status))
            ->when($filters['payment_method_id'] ?? null, fn (Builder $query, string $methodId) => $query->whereExists(fn (Builder $payments) => $payments->from('payments as fp')->whereColumn('fp.order_id', 'o.id')->where('fp.payment_method_id', $methodId)));
    }

    /** @param array<string, mixed> $filters */
    private function salesItemsQuery(array $filters): Builder
    {
        return DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->whereNull('o.deleted_at')
            ->whereIn('o.status', $this->reportableStatuses())
            ->whereBetween('o.order_date', $this->dateBounds($filters))
            ->when($filters['warehouse_id'] ?? null, fn (Builder $query, string $warehouseId) => $query->where('o.warehouse_id', $warehouseId))
            ->when($filters['customer_id'] ?? null, fn (Builder $query, string $customerId) => $query->where('o.customer_id', $customerId))
            ->when($filters['user_id'] ?? null, fn (Builder $query, string $userId) => $query->where('o.user_id', $userId))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('o.status', $status))
            ->when($filters['payment_method_id'] ?? null, fn (Builder $query, string $methodId) => $query->whereExists(fn (Builder $payments) => $payments->from('payments as fp')->whereColumn('fp.order_id', 'o.id')->where('fp.payment_method_id', $methodId)));
    }

    /** @param array<string, mixed> $filters */
    private function dateBounds(array $filters): array
    {
        return [CarbonImmutable::parse($filters['from'])->startOfDay(), CarbonImmutable::parse($filters['to'])->endOfDay()];
    }

    /** @param array<string, mixed> $filters */
    private function period(array $filters): array
    {
        return ['from' => $filters['from'], 'to' => $filters['to']];
    }

    /** @return array<int, string> */
    private function reportableStatuses(): array
    {
        return [OrderStatus::Completed->value, OrderStatus::PartiallyRefunded->value, OrderStatus::Refunded->value];
    }

    private function money(string $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    private function percentageChange(string $previous, string $current): string
    {
        if (bccomp($previous, '0.00', 2) === 0) {
            return bccomp($current, '0.00', 2) === 0 ? '0.00' : '100.00';
        }

        return number_format((((float) $current - (float) $previous) / abs((float) $previous)) * 100, 2, '.', '');
    }
}
