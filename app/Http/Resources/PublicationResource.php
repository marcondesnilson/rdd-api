<?php

namespace App\Http\Resources;

use App\Models\Publication;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
        $this->resource->loadMissing(['user.profile', 'user.roleRecord']);

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
            'coverUrl' => $this->cover_url,
            'mediaUrl' => $this->media_url,
            'status' => $this->status,
            'searchEngineIndex' => (bool) $this->search_engine_index,
            'likesCount' => $this->likes_count,
            'commentsCount' => $this->comments_count,
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
}
