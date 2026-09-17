<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReportFilterRequest;
use App\Http\Resources\OrderResource;
use App\Http\Resources\ReportResource;
use App\Services\ReportService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ReportController extends Controller
{
    public function __construct(public ReportService $reportService) {}

    public function overview(ReportFilterRequest $request): ReportResource
    {
        return new ReportResource($this->reportService->overview($request->validated()));
    }

    public function profit(ReportFilterRequest $request): ReportResource
    {
        return new ReportResource($this->reportService->profit($request->validated()));
    }

    public function products(ReportFilterRequest $request): ReportResource
    {
        return new ReportResource([
            'period' => ['from' => $request->validated('from'), 'to' => $request->validated('to')],
            'products' => $this->reportService->products($request->validated(), (int) ($request->validated('limit') ?? 10)),
        ]);
    }

    public function customers(ReportFilterRequest $request): ReportResource
    {
        return new ReportResource([
            'period' => ['from' => $request->validated('from'), 'to' => $request->validated('to')],
            'customers' => $this->reportService->customers($request->validated(), (int) ($request->validated('limit') ?? 10)),
        ]);
    }

    public function sales(ReportFilterRequest $request): AnonymousResourceCollection
    {
        return OrderResource::collection($this->reportService->sales($request->validated()));
    }
}
