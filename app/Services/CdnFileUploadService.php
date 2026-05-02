<?php

namespace App\Services;

use App\Exceptions\CdnException;
use App\Models\File as StoredFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;

class CdnFileUploadService
{
    public function __construct(private readonly CdnClient $cdnClient) {}

    public function uploadAndStore(
        UploadedFile $file,
        bool $isPublic = true,
        bool $convert = true,
    ): StoredFile {
        $payload = $this->cdnClient->upload($file, $isPublic, $convert);

        if (! is_array($payload) || Arr::get($payload, 'success') !== true) {
            throw new CdnException('CDN upload response is invalid.');
        }

        $externalFileId = Arr::get($payload, 'file_id');
        $originalFilename = Arr::get($payload, 'original_filename');
        $publicUrl = Arr::get($payload, 'public_url');
        $mimeType = Arr::get($payload, 'mime_type');
        $size = Arr::get($payload, 'size');

        if (
            ! is_string($externalFileId) ||
            ! is_string($originalFilename) ||
            ! is_string($publicUrl) ||
            ! is_string($mimeType) ||
            ! is_numeric($size)
        ) {
            throw new CdnException('CDN upload response fields are invalid.');
        }

        return StoredFile::query()->create([
            'success' => true,
            'external_file_id' => $externalFileId,
            'original_filename' => $originalFilename,
            'public_url' => $publicUrl,
            'mime_type' => $mimeType,
            'size' => (int) $size,
            'is_public' => $isPublic,
            'is_converted' => $convert,
        ]);
    }
}
