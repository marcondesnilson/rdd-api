<?php

namespace App\Http\Requests;

use App\Models\File;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $contentType = $this->string('contentType')->toString();

            if ($contentType !== 'image') {
                return;
            }

            $hasMediaUrl = $this->filled('mediaUrl');
            $fileIds = collect($this->input('fileIds', []))
                ->filter(fn ($value) => is_string($value) && trim($value) !== '')
                ->values();

            if (! $hasMediaUrl && $fileIds->isEmpty()) {
                $validator->errors()->add('fileIds', 'Para publicar imagem, envie mediaUrl ou ao menos um fileId.');
                return;
            }

            if ($fileIds->isEmpty()) {
                return;
            }

            $imageCount = File::query()
                ->whereIn('id', $fileIds)
                ->where('mime_type', 'like', 'image/%')
                ->count();

            if ($imageCount < 1) {
                $validator->errors()->add('fileIds', 'Para contentType=image, ao menos um arquivo deve ser imagem.');
            }
        });
    }
}
