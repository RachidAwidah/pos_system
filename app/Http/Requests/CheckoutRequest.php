<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'shift_id' => ['required', 'uuid', 'exists:shifts,id'],
            'customer_id' => ['nullable', 'uuid', 'exists:customers,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'uuid', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'decimal:0,3', 'gt:0'],
            'payments' => ['present', 'array'],
            'payments.*.payment_method_id' => ['required', 'uuid', 'exists:payment_methods,id'],
            'payments.*.amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0'],
            'payments.*.amount_tendered' => ['nullable', 'numeric', 'decimal:0,2', 'gt:0'],
            'payments.*.reference_number' => ['nullable', 'string', 'max:255'],
            'discount_type' => ['sometimes', 'required', 'in:none,fixed,percentage'],
            'discount_value' => ['sometimes', 'required', 'numeric', 'decimal:0,2', 'min:0'],
            'loyalty_points' => ['sometimes', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
