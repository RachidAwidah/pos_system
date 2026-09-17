<?php

namespace App\Http\Requests;

use App\Models\Setting;
use App\Services\StripeKeyResolver;
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

        if ($setting instanceof Setting && $setting->key === 'stripe_publishable_key') {
            return ['value' => ['present', 'nullable', 'string', 'regex:'.StripeKeyResolver::PUBLISHABLE_PATTERN, 'not_regex:/REPLACE/i']];
        }

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

    public function messages(): array
    {
        return [
            'value.regex' => 'صيغة مفتاح Stripe غير صحيحة. انسخه من لوحة Stripe أو اتركه فارغًا لاستخدام الإعداد الافتراضي.',
            'value.not_regex' => 'أدخل مفتاح Stripe الحقيقي بدل القيمة التجريبية.',
        ];
    }
}
