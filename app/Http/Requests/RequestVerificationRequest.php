<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RequestVerificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'requestedRole' => ['required', Rule::in(['aluno', 'professor', 'advogado'])],
            'document' => ['required', 'string', 'max:255'],
        ];
    }
}
