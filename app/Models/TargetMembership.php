<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'resident_id',
    'category_code',
    'age_in_months_snapshot',
    'age_in_years_snapshot',
    'attendance_status',
    'selection_reason',
    'created_by',
    'gender_snapshot',
    'is_pregnant_snapshot',
    'is_postpartum_snapshot',
    'is_breastfeeding_snapshot',
])]
class TargetMembership extends Model
{
    public function resident(): BelongsTo
    {
        return $this->belongsTo(Resident::class)->withTrashed();
    }

    public function reportingPeriod(): BelongsTo
    {
        return $this->belongsTo(ReportingPeriod::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }
}
