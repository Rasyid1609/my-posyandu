<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['month', 'year', 'reference_date', 'status', 'revision', 'age_rule_version_id'])]
class ReportingPeriod extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (ReportingPeriod $period) {
            $period->age_rule_version_id ??= AgeRuleVersion::query()->latest('id')->value('id');
        });
    }

    public function homeVisits(): HasMany
    {
        return $this->hasMany(HomeVisit::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(ActivitySession::class);
    }

    public function ageRuleVersion(): BelongsTo
    {
        return $this->belongsTo(AgeRuleVersion::class);
    }

    public function householdTargets(): HasMany
    {
        return $this->hasMany(HouseholdTarget::class);
    }

    protected function casts(): array
    {
        return [
            'reference_date' => 'date:Y-m-d',
            'month' => 'integer',
            'year' => 'integer',
            'revision' => 'integer',
        ];
    }

    public function targets(): HasMany
    {
        return $this->hasMany(TargetMembership::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(ReportSnapshot::class);
    }
}
