<?php

namespace App\Services;

use App\Models\ReportingPeriod;
use App\Models\TargetMembership;

class GroupedReport
{
    public function __construct(private LegacyReports $legacy) {}

    public const VERSION = 'service-recap-v1';

    public const TYPES = [
        'children' => 'Bayi, balita & prasekolah',
        'youth' => 'Anak usia sekolah & remaja',
        'adults' => 'Dewasa & lansia',
        'maternal' => 'Ibu hamil, nifas & menyusui',
        'home' => 'Kunjungan rumah',
    ];

    public function calculate(ReportingPeriod $period): array
    {
        $targets = $period->targets()->where('is_excluded', false)->where('is_within_target', true)
            ->with([
                'visits' => fn ($q) => $q->where('record_status', 'finalized')->orderBy('visited_at')->orderBy('id'),
                'visits.examination',
                'visits.referral',
            ])->get();
        $result = [];
        foreach ([
            'children' => ['infant', 'toddler', 'preschool'],
            'youth' => ['school', 'adolescent'],
            'adults' => ['adult', 'elderly'],
            'maternal' => [],
        ] as $type => $groups) {
            $selected = $targets->filter(fn ($target) => $type === 'maternal'
                ? $target->is_pregnant_snapshot || $target->is_postpartum_snapshot || $target->is_breastfeeding_snapshot
                : in_array($target->category_code, $groups, true))->unique('resident_id');
            $rows = [];
            $total = $selected->count();
            foreach ([
                'targets' => 'Sasaran unik',
                'present' => 'Hadir unik',
                'absent' => 'Tidak hadir terkonfirmasi',
                'unconfirmed' => 'Belum dikonfirmasi',
            ] as $code => $label) {
                $count = $code === 'targets' ? $total : $selected->where('attendance_status', $code === 'targets' ? '' : $code)->count();
                $rows[] = $this->metric($code, $label, $count, $total);
            }
            foreach ($groups as $group) {
                $subset = $selected->where('category_code', $group);
                $rows[] = $this->metric(
                    'targets_'.$group,
                    'Sasaran — '.ClinicalFields::AGE_LABELS[$group],
                    $subset->count()
                );
                $rows[] = $this->metric(
                    'present_'.$group,
                    'Hadir — '.ClinicalFields::AGE_LABELS[$group],
                    $subset->where('attendance_status', 'present')->count(),
                    $subset->count()
                );
            }
            if ($type === 'children') {
                $baby = $selected->filter(fn ($t) => $t->age_in_months_snapshot !== null && $t->age_in_months_snapshot < 6);
                $older = $selected->filter(fn ($t) => $t->age_in_months_snapshot !== null && $t->age_in_months_snapshot >= 6);
                foreach (['under6' => $baby, 'from6' => $older] as $key => $subset) {
                    $rows[] = $this->metric(
                        'targets_'.$key,
                        $key === 'under6' ? 'Sasaran 0–5 bulan' : 'Sasaran ≥6 bulan dalam kelompok anak',
                        $subset->count()
                    );
                    $rows[] = $this->metric(
                        'present_'.$key,
                        $key === 'under6' ? 'Hadir 0–5 bulan' : 'Hadir ≥6 bulan dalam kelompok anak',
                        $subset->where('attendance_status', 'present')->count(),
                        $subset->count()
                    );
                }
            }
            if ($type === 'maternal') {
                foreach (['pregnant' => 'Hamil', 'postpartum' => 'Nifas', 'breastfeeding' => 'Menyusui'] as $code => $label) {
                    $subset = $selected->filter(fn ($t) => (bool) $t->{'is_'.$code.'_snapshot'});
                    $rows[] = $this->metric('targets_'.$code, 'Sasaran — '.$label, $subset->count());
                    $rows[] = $this->metric(
                        'present_'.$code,
                        'Hadir — '.$label,
                        $subset->where('attendance_status', 'present')->count(),
                        $subset->count()
                    );
                }
            }
            foreach (ClinicalFields::definitions() as $code => $definition) {
                if ($type === 'maternal' && ! $definition['maternal'] && ! array_intersect($definition['groups'], ['adult', 'elderly', 'adolescent'])) {
                    continue;
                }
                if ($definition['maternal'] ? $type !== 'maternal' : ($type !== 'maternal' && ! array_intersect($definition['groups'], $groups))) {
                    continue;
                }
                $values = [];
                foreach ($selected as $target) {
                    $value = $this->lastValue($target, $code);
                    if ($value !== null) {
                        $values[$target->resident_id] = $value;
                    }
                }
                $examined = count($values);
                $rows[] = $this->metric($code.'.recorded', $definition['label'].' — hasil tercatat', $examined);
                foreach (array_diff_key($definition['options'], ClinicalFields::MISSING) as $value => $label) {
                    $rows[] = $this->metric(
                        $code.'.'.$value,
                        $definition['label'].' — '.$label,
                        count(array_filter($values, fn ($v) => $v === $value)),
                        $examined
                    );
                }
            }
            foreach ([
                'weight' => 'Berat badan',
                'height' => 'Tinggi/panjang badan',
                'blood_pressure' => 'Tekanan darah',
                'glucose' => 'Gula darah',
                'head' => 'Lingkar kepala',
                'arm' => 'Lingkar lengan',
                'waist' => 'Lingkar perut',
            ] as $key => $label) {
                $count = $selected->filter(fn ($target) => $target->visits->contains(fn ($visit) => $visit->examination?->{$key.'_state'} === 'measured'))->count();
                $rows[] = $this->metric(
                    $key.'.measured',
                    $label.' — warga unik diperiksa',
                    $count,
                    $selected->where('attendance_status', 'present')->count()
                );
            }
            $result[$type] = [
                'title' => self::TYPES[$type],
                'mapping_version' => self::VERSION,
                'rows' => $rows,
            ];
        }
        $visits = $period->homeVisits()->where('record_status', 'finalized')->with('residents')->get();
        $rows = [
            $this->metric('household_targets', 'Keluarga sasaran', $period->householdTargets()->count()),
            $this->metric(
                'visited_households',
                'Keluarga unik dikunjungi',
                $visits->unique('household_id')->count(),
                $period->householdTargets()->count()
            ),
            $this->metric('home_visits', 'Kejadian kunjungan rumah', $visits->count()),
            $this->metric(
                'visited_residents',
                'Warga unik dikunjungi',
                $visits->flatMap->residents->unique('id')->count()
            ),
        ];
        $people = $visits->flatMap->residents;
        foreach ([
            'maternal' => 'Hamil/nifas/menyusui',
            'children' => 'Bayi/balita/prasekolah',
            'youth' => 'Sekolah/remaja',
            'adult' => 'Dewasa',
            'elderly' => 'Lansia',
            'general' => 'Usia belum diketahui',
        ] as $key => $label) {
            $subset = $people->filter(fn ($p) => match ($key) {
                'maternal' => $p->pivot->is_pregnant_snapshot || $p->pivot->is_postpartum_snapshot || $p->pivot->is_breastfeeding_snapshot,
                'children' => in_array($p->pivot->category_snapshot, ['infant', 'toddler', 'preschool']),
                'youth' => in_array($p->pivot->category_snapshot, ['school', 'adolescent']),
                default => $p->pivot->category_snapshot === $key,
            });
            $rows[] = $this->metric($key.'.visited', $label.' — warga unik', $subset->unique('id')->count());
            foreach ([
                'no_service' => 'Tidak mengakses pelayanan',
                'danger_sign' => 'Tanda bahaya',
                'tb_symptom' => 'Bergejala TBC',
                'irregular_medication' => 'Obat tidak teratur',
            ] as $field => $text) {
                $known = $subset->filter(fn ($p) => in_array($p->pivot->{$field}, ['yes', 'no'], true))
                    ->unique('id')
                    ->count();
                $yes = $subset->filter(fn ($p) => $p->pivot->{$field} === 'yes')->unique('id')->count();
                $rows[] = $this->metric($key.'.'.$field, $label.' — '.$text, $yes, $known);
            }
        }
        $result['home'] = [
            'title' => self::TYPES['home'],
            'mapping_version' => self::VERSION,
            'rows' => $rows,
        ];

        return $this->legacy->apply($period, $result);
    }

    private function lastValue(TargetMembership $target, string $code): ?string
    {
        $value = null;
        foreach ($target->visits as $visit) {
            $result = $visit->examination?->{$code};
            if ($result && ! array_key_exists($result, ClinicalFields::MISSING)) {
                $value = $result;
            }
        }

        return $value;
    }

    private function metric(string $code, string $label, int $value, ?int $denominator = null): array
    {
        return [
            'code' => $code,
            'label' => $label,
            'value' => $value,
            'denominator' => $denominator,
            'percentage' => $denominator ? round($value / $denominator * 100, 2) : null,
        ];
    }

    public function visible(array $reports, bool $sensitive): array
    {
        if ($sensitive) {
            return $reports;
        }
        $keys = array_keys(array_filter(ClinicalFields::definitions(), fn ($field) => $field['sensitive']));
        foreach ($reports as &$report) {
            $report['rows'] = array_values(array_filter($report['rows'], fn ($row) => ! in_array(explode('.', $row['code'])[0], $keys, true)));
        }

        return $reports;
    }
}
