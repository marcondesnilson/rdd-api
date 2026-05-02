<?php

namespace App\Http\Resources;

use App\Models\Publication;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Publication
 */
class TimelinePostResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing(['user.profile', 'user.roleRecord']);

        return [
            'id' => $this->id,
            'body' => $this->body,
            'contentType' => $this->content_type,
            'mediaUrl' => $this->media_url,
            'visibility' => 'members',
            'likesCount' => $this->likes_count,
            'commentsCount' => $this->comments_count,
            'createdAt' => $this->created_at?->toISOString(),
            'author' => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
                'initials' => $this->user?->profile?->initials ?? 'RD',
                'headline' => $this->user?->profile?->headline,
                'role' => $this->user?->roleRecord?->role ?? 'membro',
            ],
        ];
    }
}
