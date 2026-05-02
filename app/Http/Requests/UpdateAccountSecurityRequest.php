<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccountSecurityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'currentPassword' => ['required_with:newPassword', 'string'],
            'newPassword' => ['sometimes', 'required', 'string', 'min:8', 'max:128'],
            'newPasswordConfirmation' => ['sometimes', 'required_with:newPassword', 'string'],
            'mfaEnabled' => ['sometimes', 'boolean'],
            'mfaMethod' => ['sometimes', 'string', Rule::in(['totp', 'certificate'])],
            'mfaSecret' => ['sometimes', 'nullable', 'string', 'min:16', 'max:128', 'regex:/^[A-Z2-7]+$/'],
            'mfaCode' => ['sometimes', 'nullable', 'digits:6'],
            'credentialId' => ['sometimes', 'nullable', 'string', 'min:16', 'max:255'],
            'securityEmailAlerts' => ['sometimes', 'boolean'],
        ];
    }
}
