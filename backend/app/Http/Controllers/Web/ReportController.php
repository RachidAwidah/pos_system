<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReportFilterRequest;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ReportService;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function __construct(public ReportService $reportService) {}

    public function __invoke(ReportFilterRequest $request): View
    {
        $filters = $request->validated();

        return view('reports.index', [
            'filters' => $filters,
            'overview' => $this->reportService->overview($filters),
            'profit' => $this->reportService->profit($filters),
            'products' => $this->reportService->products($filters, 10),
            'customers' => $this->reportService->customers($filters, 10),
            'orders' => $this->reportService->sales($filters),
            'warehouses' => Warehouse::query()->active()->orderBy('name')->get(['id', 'name']),
            'customerOptions' => Customer::query()->orderBy('name')->get(['id', 'name']),
            'cashiers' => User::query()->orderBy('full_name')->get(['id', 'full_name']),
            'paymentMethods' => PaymentMethod::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
