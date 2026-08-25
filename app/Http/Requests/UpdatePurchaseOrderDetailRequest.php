<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePurchaseOrderDetailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'purchase_order_id' => ['sometimes', 'required', 'uuid', 'exists:purchase_orders,id'],
            'product_id' => ['sometimes', 'required', 'uuid', 'exists:products,id'],
            'quantity' => ['sometimes', 'required', 'numeric', 'decimal:0,3', 'gt:0'],
            'cost_price' => ['sometimes', 'required', 'numeric', 'min:0'],
        ];
    }
}
