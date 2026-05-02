<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

#[Fillable([
    'user_id',
    'role',
])]
class UserRole extends Model implements AuditableContract
{
    use Auditable;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
