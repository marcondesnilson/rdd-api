<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePublicationRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:180'],
            'excerpt' => ['required', 'string', 'max:500'],
            'content' => ['required', 'string', 'max:50000'],
            'tag' => ['nullable', 'string', 'max:120'],
            'coverUrl' => ['nullable', 'url', 'max:500'],
            'contentType' => ['nullable', Rule::in(['text', 'image', 'video', 'link'])],
            'mediaUrl' => ['nullable', 'url', 'max:500'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:120'],
            'fileIds' => ['nullable', 'array'],
            'fileIds.*' => ['string', Rule::exists('files', 'id')],
            'status' => ['nullable', Rule::in(['draft', 'pending_review'])],
            'searchEngineIndex' => ['nullable', 'boolean'],
        ];
    }
}
