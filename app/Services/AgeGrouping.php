<?php

namespace App\Services;

use App\Models\AgeRuleVersion;
use App\Models\ReportingPeriod;
use App\Models\Resident;
use Carbon\CarbonImmutable;

class AgeGrouping
{
    public function category(?int $months, ?AgeRuleVersion $version): string
    {
        if ($months === null || ! $version) {
            return 'general';
        }
        $index = 0;
        foreach ($version->boundaries as $boundary) {
            if ($months < $boundary) {
                break;
            }
            $index++;
        }

        return ClinicalFields::AGE_CODES[$index];
    }

    public function snapshot(Resident $resident, ReportingPeriod $period): array
    {
        $date = $period->reference_date;
        $age = $resident->birth_date && ! $resident->is_birth_date_estimated && $resident->birth_date->lte($date)
            ? (int) $resident->birth_date->diffInMonths($date) : null;
        $period->loadMissing('ageRuleVersion');
        $resident->loadMissing('maternalEpisodes');
        $flags = [];
        foreach (['pregnant', 'postpartum', 'breastfeeding'] as $type) {
            $flags['is_'.$type.'_snapshot'] = $resident->maternalEpisodes->contains(fn ($episode) => $episode->episode_type === $type
                && $episode->start_date->lte($date) && (! $episode->end_date || $episode->end_date->gte($date)));
        }

        return [
            'category_code' => $this->category($age, $period->ageRuleVersion),
            'age_in_months_snapshot' => $age,

            'age_in_years_snapshot' => $age === null ? null : intdiv($age, 12),
            'gender_snapshot' => $resident->gender,
            ...$flags,
        ];
    }

    public function activeOn(Resident $resident, string $date): bool
    {
        $point = CarbonImmutable::parse($date);
        if ($resident->birth_date && $resident->birth_date->gt($point)) {
            return false;
        }
        $resident->loadMissing('statusHistories');
        $history = $resident->statusHistories->filter(fn ($row) => $row->effective_date->lte($point))
            ->sort(fn ($a, $b) => [$b->effective_date->timestamp, $b->id] <=> [$a->effective_date->timestamp, $a->id])->first();

        return ($history?->status ?? $resident->residency_status) === 'active';
    }
}
