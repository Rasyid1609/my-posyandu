<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['version', 'source', 'is_provisional', 'boundaries', 'created_by'])]
class AgeRuleVersion extends Model
{
    protected function casts(): array
    {
        return ['boundaries' => 'array', 'is_provisional' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => abort(409, 'Versi aturan tidak dapat diubah. Buat versi baru.'));
        static::deleting(fn () => abort(409, 'Versi aturan dipertahankan untuk histori.'));
    }
}
