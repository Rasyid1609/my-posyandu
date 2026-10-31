<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'registration_number',
    'full_name',
    'gender',
    'birth_date',
    'is_birth_date_estimated',
    'address',
    'rt',
    'rw',
    'residency_status',
    'revision',
    'household_id',
    'guardian_id',
    'family_relationship',
])]
#[Hidden([
    'national_id_number',
    'phone_number',
    'national_id_ciphertext',
    'phone_ciphertext',
    'national_id_lookup_hash',
])]
class Resident extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'birth_date' => 'date:Y-m-d',
            'is_birth_date_estimated' => 'boolean',
            'revision' => 'integer',
            'national_id_ciphertext' => 'encrypted',
            'phone_ciphertext' => 'encrypted',
        ];
    }

    public function targets(): HasMany
    {
        return $this->hasMany(TargetMembership::class);
    }

    public function maternalEpisodes(): HasMany
    {
        return $this->hasMany(MaternalEpisode::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(ResidentStatusHistory::class);
    }
}
