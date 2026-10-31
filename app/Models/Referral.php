<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'visit_id',
    'destination',
    'reason',
    'follow_up_date',
    'follow_up_status',
    'follow_up_note',
])]
class Referral extends Model
{
    protected static function booted(): void
    {
        static::creating(function (self $referral): void {
            if (! array_key_exists('follow_up_status', $referral->getAttributes())) {
                $referral->follow_up_status = 'pending';
            }
        });
    }

    protected function casts(): array
    {
        return array_fill_keys([
            'destination',
            'reason',
            'follow_up_date',
            'follow_up_status',
            'follow_up_note',
        ], 'encrypted');
    }
}
