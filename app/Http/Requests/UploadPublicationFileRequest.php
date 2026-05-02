<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadPublicationFileRequest extends FormRequest
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
            'file' => ['required', 'file', 'mimes:jpeg,jpg,png,webp,gif,mp4,mov,avi,webm', 'max:20480'],
            'kind' => ['nullable', 'string', 'in:image,video,cover,attachment'],
        ];
    }
}

