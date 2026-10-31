<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'household_id',
    'visited_on',
    'reason',
    'follow_up',
    'record_status',
    'created_by',
    'finalized_by',
    'finalized_at',
])]
class HomeVisit extends Model
{
    protected function casts(): array
    {
        return ['reason' => 'encrypted', 'follow_up' => 'encrypted'];
    }

    public function reportingPeriod(): BelongsTo
    {
        return $this->belongsTo(ReportingPeriod::class);
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class)->withTrashed();
    }

    public function residents(): BelongsToMany
    {
        return $this->belongsToMany(Resident::class, 'home_visit_residents')
            ->using(HomeVisitResident::class)
            ->withTrashed()
            ->withPivot([
                'category_snapshot',
                'is_pregnant_snapshot',
                'is_postpartum_snapshot',
                'is_breastfeeding_snapshot',
                'no_service',
                'danger_sign',
                'tb_symptom',
                'irregular_medication',
            ]);
    }

    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'home_visit_staff');
    }
}
