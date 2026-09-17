<?php

namespace App\Http\Requests;

use App\Enums\OrderStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportFilterRequest extends FormRequest
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
            'from' => ['required', Rule::date()->format('Y-m-d')],
            'to' => ['required', Rule::date()->format('Y-m-d'), 'after_or_equal:from'],
            'warehouse_id' => ['nullable', 'uuid', 'exists:warehouses,id'],
            'customer_id' => ['nullable', 'uuid', 'exists:customers,id'],
            'user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'payment_method_id' => ['nullable', 'uuid', 'exists:payment_methods,id'],
            'status' => ['nullable', Rule::enum(OrderStatus::class)],
            'search' => ['nullable', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'from' => $this->input('from', now()->startOfMonth()->toDateString()),
            'to' => $this->input('to', now()->toDateString()),
        ]);
    }
}
