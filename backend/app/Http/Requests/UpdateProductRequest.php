<?php

namespace App\Http\Requests;

use App\Enums\ProductType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProductRequest extends FormRequest
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
            'product_name' => ['sometimes', 'required', 'string', 'max:255'],
            'sku' => ['sometimes', 'required', 'string', 'max:100', Rule::unique('products', 'sku')->ignore($this->route('product'))],
            'barcode' => ['sometimes', 'nullable', 'string', 'max:100', Rule::unique('products', 'barcode')->ignore($this->route('product'))],
            'type' => ['sometimes', 'required', Rule::enum(ProductType::class)],
            'unit_id' => ['sometimes', 'required', 'uuid', 'exists:units,id'],
            'category_id' => ['sometimes', 'required', 'uuid', 'exists:categories,id'],
            'tax_id' => ['sometimes', 'nullable', 'uuid', 'exists:taxes,id'],
            'cost_price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'description' => ['sometimes', 'nullable', 'string'],
            'image' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'warehouse_id' => [
                'required_if:type,stock',
                'nullable',
                'uuid',
                Rule::exists('warehouses', 'id')->where('is_active', true),
            ],
            'reorder_level' => ['required_if:type,stock', 'nullable', 'numeric', 'decimal:0,3', 'min:0'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->user()?->hasPermission('products.edit_price')) {
                    return;
                }

                foreach (['cost_price', 'price'] as $priceField) {
                    if ($this->has($priceField)) {
                        $validator->errors()->add($priceField, 'لا تملك صلاحية تعديل أسعار المنتجات.');
                    }
                }
            },
        ];
    }
}
