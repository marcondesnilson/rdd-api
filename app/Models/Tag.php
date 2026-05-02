<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

#[Fillable(['name', 'slug'])]
class Tag extends Model implements AuditableContract
{
    use Auditable, HasUlids, SoftDeletes;

    public function publications(): BelongsToMany
    {
        return $this->belongsToMany(Publication::class, 'publication_tag');
    }
}
