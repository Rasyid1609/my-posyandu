<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'target_membership_id',
    'resident_id',
    'visited_at',
    'record_status',
    'created_by',
    'finalized_by',
    'finalized_at',
    'activity_session_id',
])]
class Visit extends Model
{
    protected function casts(): array
    {
        return ['visited_at' => 'datetime', 'finalized_at' => 'datetime'];
    }

    public function examination(): HasOne
    {
        return $this->hasOne(Examination::class);
    }

    public function referral(): HasOne
    {
        return $this->hasOne(Referral::class);
    }

    public function targetMembership(): BelongsTo
    {
        return $this->belongsTo(TargetMembership::class);
    }
}
