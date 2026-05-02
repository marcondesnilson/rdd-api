<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->user()?->id),
            ],
            'headline' => ['sometimes', 'nullable', 'string', 'max:255'],
            'bio' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'language' => ['sometimes', 'required', 'string', Rule::in(['pt-BR', 'en', 'es'])],
            'publicProfile' => ['sometimes', 'boolean'],
            'showEmail' => ['sometimes', 'boolean'],
            'searchEngineIndex' => ['sometimes', 'boolean'],
            'allowMessages' => ['sometimes', 'boolean'],
            'showActivity' => ['sometimes', 'boolean'],
        ];
    }
}
