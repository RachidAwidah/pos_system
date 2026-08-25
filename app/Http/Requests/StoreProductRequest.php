<?php

namespace App\Http\Requests;

use App\Enums\ProductType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
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
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'product_name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:100', 'unique:products,sku'],
            'barcode' => ['nullable', 'string', 'max:100', 'unique:products,barcode'],
            'type' => ['required', Rule::enum(ProductType::class)],
            'unit_id' => ['required', 'uuid', 'exists:units,id'],
            'category_id' => ['required', 'uuid', 'exists:categories,id'],
            'tax_id' => ['nullable', 'uuid', 'exists:taxes,id'],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'price' => ['required', 'numeric', 'min:0'],
            'quantity' => ['required', 'numeric', 'decimal:0,3', 'min:0'],
            'reorder_level' => ['required', 'numeric', 'decimal:0,3', 'min:0'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'string', 'max:2048'],
        ];
    }
}
