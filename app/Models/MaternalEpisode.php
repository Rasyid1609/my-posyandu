<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'resident_id',
    'episode_type',
    'start_date',
    'end_date',
    'source',
    'created_by',
    'is_active',
])]
class MaternalEpisode extends Model
{
    protected function casts(): array
    {
        return [
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'is_active' => 'boolean',
        ];
    }
}
