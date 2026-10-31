<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['head_of_household_name', 'address', 'rt', 'rw', 'is_active', 'revision'])]
#[Hidden(['family_card_number', 'family_card_ciphertext', 'family_card_lookup_hash'])]
class Household extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'revision' => 'integer',
            'family_card_ciphertext' => 'encrypted',
        ];
    }

    public function residents(): HasMany
    {
        return $this->hasMany(Resident::class);
    }

    public function homeVisits(): HasMany
    {
        return $this->hasMany(HomeVisit::class);
    }
}
