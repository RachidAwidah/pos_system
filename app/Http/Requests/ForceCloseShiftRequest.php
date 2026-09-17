<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ForceCloseShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('Admin') ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'closing_cash' => ['required', 'numeric', 'decimal:0,2', 'min:0'],
            'reason' => ['required', 'string', 'max:2000'],
            'closing_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
