<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->input('password') === null || $this->input('password') === '') {
            $this->request->remove('password');
            $this->request->remove('password_confirmation');
        }
    }

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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->route('user'))],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'password' => ['sometimes', 'required', 'string', Password::defaults(), 'confirmed'],
            'role_ids' => ['sometimes', 'required', 'array', 'size:1'],
            'role_ids.*' => ['required', 'uuid', 'distinct', 'exists:roles,id'],
            'must_change_password' => ['sometimes', 'boolean'],
        ];
    }
}
