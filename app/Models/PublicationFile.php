<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

#[Fillable([
    'publication_id',
    'file_id',
    'kind',
    'sort_order',
])]
class PublicationFile extends Model implements AuditableContract
{
    use Auditable, HasUlids, SoftDeletes;

    protected $table = 'publication_files';

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function publication(): BelongsTo
    {
        return $this->belongsTo(Publication::class);
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }
}
