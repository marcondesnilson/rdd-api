<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Throwable;
use Illuminate\Validation\Validator;

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
            'file' => ['required', 'file', 'max:20480'],
            'kind' => ['nullable', 'string', 'in:image,video,cover,attachment'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $uploadLimit = (string) ini_get('upload_max_filesize');
        $postLimit = (string) ini_get('post_max_size');
        $limitHint = $uploadLimit !== '' ? $uploadLimit : '2M';

        return [
            'file.uploaded' => "Falha ao receber o arquivo enviado. Limite atual do servidor: {$limitHint} (post_max_size: {$postLimit}).",
            'file.max' => 'Arquivo muito grande. Tamanho máximo permitido: 20MB.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $file = $this->file('file');
            if ($file === null) {
                return;
            }

            if (! $file->isValid()) {
                return;
            }

            $kind = $this->string('kind')->toString();
            $extension = strtolower((string) $file->getClientOriginalExtension());
            $mime = '';

            try {
                // Usa o MIME informado pelo cliente para evitar excecao de path vazio
                // em arquivos temporarios invalidos durante a validacao.
                $mime = strtolower((string) $file->getClientMimeType());
            } catch (Throwable) {
                $mime = '';
            }

            $imageExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'heic', 'heif', 'avif'];
            $videoExtensions = ['mp4', 'mov', 'avi', 'webm', 'm4v'];

            $looksLikeImage = str_starts_with($mime, 'image/') || in_array($extension, $imageExtensions, true);
            $looksLikeVideo = str_starts_with($mime, 'video/') || in_array($extension, $videoExtensions, true);

            if (in_array($kind, ['image', 'cover'], true) && ! $looksLikeImage) {
                $validator->errors()->add('file', 'Arquivo inválido para imagem. Use JPG, PNG, WEBP, GIF, HEIC, HEIF ou AVIF.');
                return;
            }

            if ($kind === 'video' && ! $looksLikeVideo) {
                $validator->errors()->add('file', 'Arquivo inválido para vídeo. Use MP4, MOV, AVI, WEBM ou M4V.');
                return;
            }

            if ($kind === 'attachment' && ! $looksLikeImage && ! $looksLikeVideo) {
                $validator->errors()->add('file', 'Arquivo inválido. Use um formato de imagem ou vídeo suportado.');
            }
        });
    }
}
