<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CompleteSalesReturnRequest;
use App\Http\Resources\SalesReturnResource;
use App\Models\Order;
use App\Models\Shift;
use App\Services\SalesReturnService;

class SalesReturnController extends Controller
{
    public function __construct(public SalesReturnService $salesReturnService) {}

    public function store(CompleteSalesReturnRequest $request, Order $order): SalesReturnResource
    {
        $validated = $request->validated();

        $shift = isset($validated['shift_id']) ? Shift::query()->findOrFail($validated['shift_id']) : null;

        return new SalesReturnResource($this->salesReturnService->complete(
            $order,
            $shift,
            $request->user(),
            $validated['items'],
            $validated['refunds'],
            $validated['reason'],
        ));
    }
}
