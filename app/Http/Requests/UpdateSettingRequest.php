<?php

namespace App\Http\Requests;

use App\Models\Setting;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingRequest extends FormRequest
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
        $setting = $this->route('setting');
        $type = $setting instanceof Setting ? $setting->type : 'string';

        return [
            'value' => match ($type) {
                'boolean' => ['required', 'boolean'],
                'integer' => ['required', 'integer'],
                'float' => ['required', 'numeric'],
                'json' => ['required', 'array'],
                default => ['present', 'nullable', 'string'],
            },
        ];
    }
}
