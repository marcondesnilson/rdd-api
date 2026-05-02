<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

#[Fillable([
    'success',
    'external_file_id',
    'original_filename',
    'public_url',
    'mime_type',
    'size',
    'is_public',
    'is_converted',
])]
class File extends Model implements AuditableContract
{
    use Auditable, HasUlids;

    protected function casts(): array
    {
        return [
            'success' => 'boolean',
            'size' => 'integer',
            'is_public' => 'boolean',
            'is_converted' => 'boolean',
        ];
    }
}
