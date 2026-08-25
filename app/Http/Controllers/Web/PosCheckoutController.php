<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckoutRequest;
use App\Http\Resources\OrderResource;
use App\Models\Customer;
use App\Models\Shift;
use App\Services\CheckoutService;

class PosCheckoutController extends Controller
{
    public function __invoke(CheckoutRequest $request, CheckoutService $checkoutService): OrderResource
    {
        $validated = $request->validated();

        return new OrderResource($checkoutService->checkout(
            $request->user(),
            Shift::query()->findOrFail($validated['shift_id']),
            $validated['items'],
            $validated['payments'],
            isset($validated['customer_id']) ? Customer::query()->findOrFail($validated['customer_id']) : null,
            $validated['discount_type'] ?? 'none',
            (string) ($validated['discount_value'] ?? '0'),
            $validated['loyalty_points'] ?? 0,
            $validated['notes'] ?? null,
        ));
    }
}
