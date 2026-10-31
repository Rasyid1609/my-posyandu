<?php

namespace App\Services;

use App\Models\ReportingPeriod;
use Illuminate\Support\Facades\DB;

class LegacyReports
{
    public function apply(ReportingPeriod $period, array $reports): array
    {
        $selections = DB::table('report_source_selections')
            ->where('reporting_period_id', $period->id)
            ->whereNotNull('import_batch_id')
            ->get();
        foreach ($selections as $selection) {
            if (! isset($reports[$selection->report_type])) {
                continue;
            }
            $values = DB::table('legacy_report_values')
                ->where('import_batch_id', $selection->import_batch_id)
                ->pluck('value', 'metric_code');
            $rows = [];
            foreach ($reports[$selection->report_type]['rows'] as $row) {
                $value = $values->get($row['code']);
                $denominator = null;
                if (in_array($row['code'], ['present', 'absent', 'unconfirmed'], true)) {
                    $denominator = $values->get('targets');
                } elseif (str_contains($row['code'], '.') && ! str_ends_with($row['code'], '.recorded')) {
                    $denominator = $values->get(explode('.', $row['code'])[0].'.recorded');
                }
                $rows[] = [
                    ...$row,
                    'value' => $value === null ? null : (int) $value,

                    'denominator' => $denominator === null ? null : (int) $denominator,

                    'percentage' => $denominator && $value !== null ? round($value / $denominator * 100, 2) : null,
                ];
            }
            $reports[$selection->report_type]['rows'] = $rows;
            $reports[$selection->report_type]['source'] = 'legacy_import';
            $reports[$selection->report_type]['import_batch_id'] = $selection->import_batch_id;
        }
        foreach ($reports as &$report) {
            $report['source'] ??= 'transactions';
        }

        return $reports;
    }
}
