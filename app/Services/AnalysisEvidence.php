<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class AnalysisEvidence
{
    public const SCOPES = [
        'operational' => 'Operasional umum',
        'all' => 'Ringkasan semua kelompok',
        ...GroupedReport::TYPES,
    ];

    public function rows(array $metrics, string $scope): array
    {
        $result = [];
        if ($scope === 'operational') {
            foreach ([
                'targets' => 'Sasaran unik',
                'present' => 'Hadir unik',
                'absent' => 'Tidak hadir terkonfirmasi',
                'unconfirmed' => 'Belum dikonfirmasi',
                'finalized_visits' => 'Kunjungan final',
                'draft_visits' => 'Kunjungan draft',
                'weight_measured' => 'Warga diukur berat badan',
                'blood_sugar_measured' => 'Warga diperiksa gula darah',
            ] as $code => $label) {
                if (isset($metrics[$code])) {
                    $result[] = ['id' => $code, 'label' => $label, 'value' => $metrics[$code]];
                }
            }

            return $result;
        }
        $reports = $metrics['_reports'] ?? [];
        if ($scope !== 'all' && ! isset($reports[$scope])) {
            throw ValidationException::withMessages(['analysis' => 'Mapping kelompok tidak tersedia pada arsip ini.']);
        }
        if ($scope === 'all') {
            foreach ([
                'targets' => 'Sasaran unik seluruh warga',
                'unclassified_targets' => 'Sasaran usia belum diketahui',
            ] as $code => $label) {
                if (isset($metrics[$code])) {
                    $result[] = ['id' => 'global.'.$code, 'label' => $label, 'value' => $metrics[$code]];
                }
            }
        }
        foreach ($reports as $type => $report) {
            if ($scope !== 'all' && $scope !== $type) {
                continue;
            }
            foreach ($report['rows'] as $row) {
                if ($row['value'] === null) {
                    continue;
                }
                if ($scope === 'all' && ! in_array($row['code'], [
                    'targets',
                    'present',
                    'absent',
                    'unconfirmed',
                    'household_targets',
                    'visited_households',
                    'home_visits',
                    'visited_residents',
                ]) && ! str_ends_with($row['code'], '.recorded')) {
                    continue;
                }
                $result[] = [
                    'id' => $type.'.'.$row['code'],
                    'label' => $report['title'].' — '.$row['label'],
                    'value' => $row['value'],
                ];
            }
        }

        return $result;
    }

    public function compatible(array $current, array $comparison, string $scope): bool
    {
        if (($current['_age_rule_version_id'] ?? null) !== ($comparison['_age_rule_version_id'] ?? null) || ($current['_group_mapping'] ?? null) !== ($comparison['_group_mapping'] ?? null)) {
            return false;
        }
        foreach ($current['_reports'] ?? [] as $type => $report) {
            if ($scope === 'operational' || ($scope !== 'all' && $scope !== $type)) {
                continue;
            }
            if (($comparison['_reports'][$type]['source'] ?? null) !== $report['source']) {
                return false;
            }
        }

        return true;
    }

    public function population(array $metrics, string $scope): int
    {
        if (in_array($scope, ['operational', 'all'])) {
            return $metrics['targets'] ?? 0;
        }
        $code = $scope === 'home' ? 'visited_residents' : 'targets';
        foreach ($metrics['_reports'][$scope]['rows'] ?? [] as $row) {
            if ($row['code'] === $code) {
                return $row['value'] ?? 0;
            }
        }

        return 0;
    }
}
