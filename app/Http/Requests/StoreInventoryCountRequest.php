<?php

namespace App\Http\Requests;

use App\Enums\ProductType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventoryCountRequest extends FormRequest
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
            'warehouse_id' => [
                'required',
                'uuid',
                Rule::exists('warehouses', 'id')->where('is_active', true),
            ],
            'product_ids' => ['sometimes', 'array', 'min:1'],
            'product_ids.*' => [
                'required',
                'uuid',
                'distinct',
                Rule::exists('products', 'id')->where('type', ProductType::Stock->value),
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
