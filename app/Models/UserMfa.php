<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

#[Fillable([
    'user_id',
    'method',
    'enabled',
    'totp_secret',
    'credential_id',
    'verified_at',
    'last_used_at',
])]
class UserMfa extends Model implements AuditableContract
{
    use Auditable;

    protected $table = 'user_mfa';

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'verified_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
