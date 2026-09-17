<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CloseShiftRequest extends FormRequest
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
            'closing_cash' => ['required', 'numeric', 'decimal:0,2', 'min:0'],
            'closing_notes' => ['nullable', 'string', 'max:2000'],
            'admin_override_reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
