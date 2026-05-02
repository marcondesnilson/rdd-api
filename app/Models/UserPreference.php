<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

#[Fillable([
    'user_id',
    'public_profile',
    'show_email',
    'search_engine_index',
    'allow_messages',
    'show_activity',
])]
class UserPreference extends Model implements AuditableContract
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'public_profile' => 'boolean',
            'show_email' => 'boolean',
            'search_engine_index' => 'boolean',
            'allow_messages' => 'boolean',
            'show_activity' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
