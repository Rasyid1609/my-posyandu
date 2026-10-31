<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'version',
    'source_revision',
    'mapping_version',
    'metrics',
    'source_hash',
    'approved_by',
    'created_at',
])]
class ReportSnapshot extends Model
{
    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new \LogicException('Snapshot terkunci tidak dapat diubah. Buat versi laporan baru.');
        });
        static::deleting(function (): never {
            throw new \LogicException('Snapshot terkunci tidak dapat dihapus.');
        });
    }

    protected function casts(): array
    {
        return ['metrics' => 'array', 'created_at' => 'datetime'];
    }
}
