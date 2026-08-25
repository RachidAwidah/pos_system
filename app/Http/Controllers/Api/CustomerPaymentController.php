<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCustomerPaymentRequest;
use App\Http\Resources\CustomerPaymentResource;
use App\Models\Customer;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Services\CustomerAccountService;

class CustomerPaymentController extends Controller
{
    public function __construct(public CustomerAccountService $customerAccountService) {}

    public function store(StoreCustomerPaymentRequest $request, Customer $customer): CustomerPaymentResource
    {
        $validated = $request->validated();

        return new CustomerPaymentResource($this->customerAccountService->collectPayment(
            $customer,
            Order::query()->findOrFail($validated['order_id']),
            $request->user(),
            PaymentMethod::query()->findOrFail($validated['payment_method_id']),
            (string) $validated['amount'],
            $validated['reference_number'] ?? null,
            $validated['notes'] ?? null,
        ));
    }
}
