<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class HomeVisitResident extends Pivot
{
    public $incrementing = true;

    protected function casts(): array
    {
        return array_fill_keys(['no_service', 'danger_sign', 'tb_symptom', 'irregular_medication'], 'encrypted');
    }
}
