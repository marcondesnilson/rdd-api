<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
class StoreTimelinePostRequest extends FormRequest
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
            'body' => ['required', 'string', 'max:1000'],
            'contentType' => ['required', 'string', 'in:text,image,video,link'],
            'mediaUrl' => ['nullable', 'url', 'max:500'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:120'],
            'fileIds' => ['nullable', 'array'],
            'fileIds.*' => ['string', 'exists:files,id'],
        ];
    }
}
