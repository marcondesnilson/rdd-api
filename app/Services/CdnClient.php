<?php

namespace App\Services;

use App\Exceptions\CdnException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;

class CdnClient
{
    public function __construct(private readonly HttpFactory $http) {}

    /**
     * @param  array<string, scalar|array<int, scalar>|null>  $payload
     * @return array<string, mixed>
     */
    public function postJson(string $path, array $payload = []): array
    {
        $response = $this->client()->post($this->resolvePath($path), $payload);

        return $this->decodeJsonResponse($response);
    }

    /**
     * @return array<string, mixed>
     */
    public function getJson(string $path): array
    {
        $response = $this->client()->get($this->resolvePath($path));

        return $this->decodeJsonResponse($response);
    }

    /**
     * @return array<string, mixed>
     */
    public function upload(UploadedFile $file, bool $isPublic = true, bool $convert = true): array
    {
        $stream = fopen($file->getRealPath(), 'rb');

        if ($stream === false) {
            throw new CdnException('Unable to open uploaded file stream.');
        }

        try {
            $response = $this->client()
                ->attach('file', $stream, $file->getClientOriginalName())
                ->post($this->uploadPath(), [
                    'public' => $isPublic ? 'true' : 'false',
                    'convert' => $convert ? 'true' : 'false',
                ]);
        } finally {
            fclose($stream);
        }

        return $this->decodeJsonResponse($response);
    }

    private function client(): PendingRequest
    {
        $apiKey = (string) config('services.cdn_upload.api_key');
        $baseUrl = rtrim((string) config('services.cdn_upload.base_url'), '/');

        if ($apiKey === '' || $baseUrl === '') {
            throw new CdnException('CDN configuration is incomplete.');
        }

        return $this->http
            ->baseUrl($baseUrl)
            ->timeout((int) config('services.cdn_upload.timeout', 20))
            ->connectTimeout((int) config('services.cdn_upload.connect_timeout', 10))
            ->acceptJson()
            ->withHeaders(['X-API-Key' => $apiKey]);
    }

    private function uploadPath(): string
    {
        return $this->resolvePath((string) config('services.cdn_upload.upload_path', '/upload'));
    }

    private function resolvePath(string $path): string
    {
        return '/'.ltrim($path, '/');
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonResponse(Response $response): array
    {
        if ($response->failed()) {
            throw new CdnException('CDN request failed.');
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new CdnException('CDN response is not valid JSON.');
        }

        if (Arr::has($payload, 'success') && Arr::get($payload, 'success') !== true) {
            throw new CdnException('CDN response reported failure.');
        }

        return $payload;
    }
}
