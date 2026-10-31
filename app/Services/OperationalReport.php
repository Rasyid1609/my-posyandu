<?php

namespace App\Services;

use App\Models\ReportingPeriod;

class OperationalReport
{
    public const VERSION = 'operational-v1';

    public function __construct(private GroupedReport $groups) {}

    public function calculate(ReportingPeriod $period): array
    {
        $targets = $period->targets()->where('is_excluded', false)->where('is_within_target', true);
        $total = (clone $targets)->distinct()->count('resident_id');
        $present = (clone $targets)->where('attendance_status', 'present')->distinct()->count('resident_id');
        $metrics = [
            'targets' => $total,
            'present' => $present,
            'absent' => (clone $targets)->where('attendance_status', 'absent')->distinct()->count('resident_id'),
            'unconfirmed' => (clone $targets)->where('attendance_status', 'unconfirmed')->distinct()->count('resident_id'),
            'attendance_coverage' => $total === 0 ? null : round($present / $total * 100, 2),
            'finalized_visits' => $period->visits()->where('record_status', 'finalized')->count(),
            'draft_visits' => $period->visits()->where('record_status', 'draft')->count(),
            'draft_home_visits' => $period->homeVisits()->where('record_status', 'draft')->count(),
        ];
        $examinedTargets = (clone $targets)->with([
            'visits' => fn ($query) => $query->where('record_status', 'finalized'),
            'visits.examination:id,visit_id,weight_state,glucose_state',
        ])->get();
        foreach (['weight_measured' => 'weight_state', 'blood_sugar_measured' => 'glucose_state'] as $code => $field) {
            $metrics[$code] = $examinedTargets->filter(fn ($target) => $target->visits->contains(
                fn ($visit) => $visit->examination?->{$field} === 'measured'
            ))->unique('resident_id')->count();
        }

        $metrics['_reports'] = $this->groups->calculate($period);
        $metrics['_age_rule_version_id'] = $period->age_rule_version_id;
        $metrics['_group_mapping'] = GroupedReport::VERSION;
        $metrics['unclassified_targets'] = (clone $targets)->where('category_code', 'general')->count();

        return $metrics;
    }
}
