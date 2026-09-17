<?php

namespace App\Http\Requests;

use App\Models\Setting;
use App\Services\StripeKeyResolver;
use Illuminate\Foundation\Http\FormRequest;

class BulkUpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $current = Setting::query()->where('key', 'stripe_publishable_key')
            ->pluck('value', 'key')
            ->toArray();

        if ($this->input('settings.stripe_publishable_key') === ($current['stripe_publishable_key'] ?? null)) {
            $this->request->remove('settings.stripe_publishable_key');
        }
    }

    public function rules(): array
    {
        return [
            'settings' => ['required', 'array'],
            'settings.*' => ['nullable'],
            'settings.stripe_publishable_key' => ['nullable', 'string', 'regex:'.StripeKeyResolver::PUBLISHABLE_PATTERN, 'not_regex:/REPLACE/i'],
        ];
    }

    public function messages(): array
    {
        return [
            'settings.stripe_publishable_key.regex' => 'مفتاح Stripe العام غير صالح؛ يجب أن يبدأ بـ pk_test_ أو pk_live_.',
            'settings.stripe_publishable_key.not_regex' => 'أدخل مفتاح Stripe الحقيقي بدل القيمة التجريبية.',
        ];
    }
}
