<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VerifyMfaRequest extends FormRequest
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
            'method' => ['required', 'string', Rule::in(['totp', 'certificate'])],
            'mfaCode' => ['required_if:method,totp', 'nullable', 'digits:6'],
            'credentialId' => ['required_if:method,certificate', 'nullable', 'string', 'min:16', 'max:255'],
        ];
    }
}
