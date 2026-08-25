<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseOrderDetailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'purchase_order_id' => ['required', 'uuid', 'exists:purchase_orders,id'],
            'product_id' => ['required', 'uuid', 'exists:products,id'],
            'quantity' => ['required', 'numeric', 'decimal:0,3', 'gt:0'],
            'cost_price' => ['required', 'numeric', 'min:0'],
        ];
    }
}
