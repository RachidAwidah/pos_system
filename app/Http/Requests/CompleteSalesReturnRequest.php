<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CompleteSalesReturnRequest extends FormRequest
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
            'shift_id' => ['nullable', 'uuid', 'exists:shifts,id'],
            'reason' => ['required', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.order_item_id' => ['required', 'uuid', 'distinct', 'exists:order_items,id'],
            'items.*.quantity' => ['required', 'numeric', 'decimal:0,3', 'gt:0'],
            'items.*.restock' => ['sometimes', 'boolean'],
            'items.*.reason' => ['nullable', 'string', 'max:255'],
            'refunds' => ['present', 'array'],
            'refunds.*.payment_method_id' => ['required', 'uuid', 'exists:payment_methods,id'],
            'refunds.*.amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0'],
            'refunds.*.reference_number' => ['nullable', 'string', 'max:255'],
        ];
    }
}
