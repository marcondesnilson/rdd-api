<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

#[Fillable([
    'user_id',
    'post_type',
    'content_type',
    'slug',
    'title',
    'excerpt',
    'content',
    'body',
    'tag',
    'cover_url',
    'media_url',
    'status',
    'search_engine_index',
    'likes_count',
    'comments_count',
    'published_at',
])]
class Publication extends Model implements AuditableContract
{
    use Auditable, HasUlids, SoftDeletes;

    protected function casts(): array
    {
        return [
            'search_engine_index' => 'boolean',
            'likes_count' => 'integer',
            'comments_count' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'publication_tag');
    }

    public function files(): HasMany
    {
        return $this->hasMany(PublicationFile::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(PublicationComment::class);
    }

    public function likes(): HasMany
    {
        return $this->hasMany(PublicationLike::class);
    }

    public function saves(): HasMany
    {
        return $this->hasMany(PublicationSave::class);
    }

    public function views(): HasMany
    {
        return $this->hasMany(PublicationView::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
