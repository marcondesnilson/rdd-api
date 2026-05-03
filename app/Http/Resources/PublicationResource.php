<?php

namespace App\Http\Resources;

use App\Models\Publication;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

/**
 * @mixin Publication
 */
class PublicationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing(['user.profile', 'user.roleRecord', 'files.file']);
        $user = $request->user() ?? auth('sanctum')->user();
        $coverUrl = $this->resolveCoverUrl();

        return [
            'id' => $this->id,
            'postType' => $this->post_type,
            'contentType' => $this->content_type,
            'slug' => $this->slug,
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'content' => $this->content,
            'body' => $this->body,
            'tag' => $this->tag,
            'coverUrl' => $coverUrl,
            'mediaUrl' => is_string($this->media_url) ? $this->normalizeUrl($this->media_url) : $this->media_url,
            'status' => $this->status,
            'searchEngineIndex' => (bool) $this->search_engine_index,
            'likesCount' => $this->likes_count,
            'commentsCount' => $this->comments_count,
            'liked' => $user !== null ? $this->likes()->where('user_id', $user->id)->exists() : false,
            'saved' => $user !== null ? $this->saves()->where('user_id', $user->id)->exists() : false,
            'followingAuthor' => $user !== null && $this->user !== null
                ? DB::table('user_follows')
                    ->where('follower_id', $user->id)
                    ->where('followee_id', $this->user->id)
                    ->whereNull('deleted_at')
                    ->exists()
                : false,
            'publishedAt' => $this->published_at?->toISOString(),
            'createdAt' => $this->created_at?->toISOString(),
            'author' => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
                'initials' => $this->user?->profile?->initials ?? 'RD',
                'avatarUrl' => $this->user?->profile?->avatar_url,
                'headline' => $this->user?->profile?->headline,
                'role' => $this->user?->roleRecord?->role ?? 'membro',
            ],
        ];
    }

    private function resolveCoverUrl(): ?string
    {
        if (is_string($this->cover_url) && trim($this->cover_url) !== '') {
            return $this->normalizeUrl($this->cover_url);
        }

        $firstImageFile = $this->files
            ->sortBy('sort_order')
            ->first(function ($publicationFile): bool {
                $mimeType = (string) ($publicationFile->file?->mime_type ?? '');
                return $publicationFile->kind === 'image' || str_starts_with($mimeType, 'image/');
            });

        $publicUrl = $firstImageFile?->file?->public_url;
        if (! is_string($publicUrl) || trim($publicUrl) === '') {
            return null;
        }

        return $this->normalizeUrl($publicUrl);
    }

    private function normalizeUrl(string $url): string
    {
        if ($url === '') {
            return $url;
        }

        if (Str::startsWith($url, ['http://', 'https://'])) {
            return $url;
        }

        if (str_starts_with($url, '//')) {
            return 'https:'.$url;
        }

        $baseUrl = rtrim((string) config('services.cdn_upload.base_url'), '/');
        if ($baseUrl === '') {
            return $url;
        }

        return $baseUrl.'/'.ltrim($url, '/');
    }
}
