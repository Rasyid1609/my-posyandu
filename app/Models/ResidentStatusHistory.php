<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResidentStatusHistory extends Model
{
    protected function casts(): array
    {
        return ['effective_date' => 'date:Y-m-d'];
    }
}
