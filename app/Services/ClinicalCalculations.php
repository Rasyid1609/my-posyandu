<?php

namespace App\Services;

use App\Models\Examination;
use App\Models\TargetMembership;
use App\Models\Visit;
use Carbon\CarbonImmutable;

class ClinicalCalculations
{
    public const VERSION = 'clinical-calculation-v1';

    public const FIELDS = [
        'bmi_status',
        'weight_age_result',
        'height_age_result',
        'weight_height_result',
        'weight_trend',
        'bp_status',
        'blood_sugar_status',
    ];

    public const CONTEXT = ['height_method', 'glucose_context', 'blood_pressure_confirmed'];

    private const CHILD_SOURCE = [
        'title' => 'Permenkes 2/2020',
        'url' => 'https://jdih.kemkes.go.id/storage/documents/pdfs/2020permenkes002.pdf',
        'section' => 'Lampiran, tabel 1–19',
    ];

    private const ADULT_SOURCE = [
        'title' => 'PNPK Obesitas Dewasa 509/2025',
        'url' => 'https://jdih.kemkes.go.id/storage/documents/pdfs/2025kepmenkes509.pdf',
        'section' => 'Tabel 3.1, halaman 13',
    ];

    private const PRESSURE_SOURCE = [
        'title' => 'PNPK Hipertensi Dewasa 303/2026',
        'url' => 'https://keslan.kemkes.go.id/unduhan/fileunduhan1780387327_362636.pdf',
        'section' => 'Skrining dan pengukuran tekanan darah',
    ];

    private const GLUCOSE_SOURCE = [
        'title' => 'PNPK Diabetes Melitus Tipe 2 Dewasa 302/2026',
        'url' => 'https://jdih.kemkes.go.id/storage/documents/pdfs/2026kepmenkes302.pdf',
        'section' => 'Kriteria pemeriksaan glukosa, halaman 10–12; hipoglikemia',
    ];

    /** @var array<string, mixed> */
    private array $reference;

    public function __construct()
    {
        $this->reference = json_decode(
            file_get_contents(resource_path('anthropometry-2020.json')),
            true,
            flags: JSON_THROW_ON_ERROR
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{mode: string, version: string, reference_version: string, reference_hash: string, calculated_at: string, reference_date: string, age_months: int|null, gender: string, pregnant: bool, inputs: array<string, mixed>, corrected_height_cm: float|null, results: array<string, array<string, mixed>>, field_values: array<string, string>}
     */
    public function calculate(TargetMembership $target, array $data, ?int $editingVisitId = null): array
    {
        $target->loadMissing('resident.maternalEpisodes');
        $resident = $target->resident;
        $date = CarbonImmutable::parse($data['visited_at'], 'Asia/Jakarta');
        $day = $date->startOfDay();
        $birth = $resident->birth_date ? CarbonImmutable::parse($resident->birth_date->toDateString(), 'Asia/Jakarta') : null;
        $age = $birth && ! $resident->is_birth_date_estimated && $birth->lte($day) ? (int) $birth->diffInMonths($day) : null;
        $gender = $target->gender_snapshot ?: $resident->gender;
        $pregnant = (bool) $target->is_pregnant_snapshot || $resident->maternalEpisodes->contains(
            fn ($episode) => $episode->episode_type === 'pregnant' && $episode->start_date->toDateString() <= $day->toDateString() && (! $episode->end_date || $episode->end_date->toDateString() >= $day->toDateString())
        );
        $weight = $this->measurement($data, 'weight', 'weight_kg');
        $height = $this->measurement($data, 'height', 'height_cm');
        $method = $data['height_method'] ?? 'unknown';
        $recumbent = $age !== null && ($age < 24 || ($age === 24 && $method === 'recumbent'));
        $correctedHeight = $height;
        if ($height !== null && $age !== null && $age <= 60) {
            $correctedHeight = match ($method) {
                'recumbent' => $recumbent ? $height : $height - 0.7,
                'standing' => $recumbent ? $height + 0.7 : $height,
                default => null,
            };
        }
        $correctedHeight = $correctedHeight !== null && $correctedHeight > 0 ? round($correctedHeight, 2) : null;
        $offset = $gender === 'P' ? 7 : 0;
        $ageReason = $age === null ? 'Tanggal lahir belum pasti; kategori menurut usia belum dapat dihitung.' : null;
        $results = [];
        $bmi = $weight !== null && $correctedHeight !== null && $correctedHeight > 0 ? $weight / (($correctedHeight / 100) ** 2) : null;
        $bmiReason = $pregnant ? 'IMT dari berat saat hamil tidak dikategorikan otomatis.' : $ageReason;
        $bmiReason ??= $bmi === null ? 'Lengkapi berat dan tinggi yang diperiksa; pada anak, catat juga posisi ukur.' : null;
        if ($bmiReason !== null) {
            $results['bmi'] = $this->unavailable(
                'IMT / IMT menurut umur',
                $bmiReason,
                $pregnant ? 'not_applicable' : 'unknown',
                $bmi === null ? null : round($bmi, 2)
            );
        } elseif ($age >= 228) {
            [$category, $label, $mapped] = match (true) {
                $this->compareBmi($weight, $correctedHeight, 18.5) < 0 => ['underweight', 'Berat badan kurang', 'thin'],
                $this->compareBmi($weight, $correctedHeight, 23) < 0 => ['normal', 'Normal', 'normal'],
                $this->compareBmi($weight, $correctedHeight, 25) < 0 => ['overweight', 'Berat badan lebih', 'overweight'],
                $this->compareBmi($weight, $correctedHeight, 30) < 0 => ['obese_1', 'Obesitas I (kategori IMT)', 'obese'],
                default => ['obese_2', 'Obesitas II (kategori IMT)', 'obese'],
            };
            $results['bmi'] = $this->result('IMT dewasa', $category, $label, $mapped, self::ADULT_SOURCE, round($bmi, 2));
        } else {
            $table = $age <= 60 ? (($recumbent ? 6 : 7) + $offset) : ($gender === 'P' ? 16 : 15);
            $row = $this->row($table, $age);
            $results['bmi'] = $row ? $this->nutrition(
                'IMT menurut umur',
                $bmi,
                $row,
                $age <= 60,
                round($bmi, 2),
                [$weight, $correctedHeight]
            )
                : $this->unavailable('IMT menurut umur', 'Usia di luar tabel acuan yang tersedia.');
        }

        $child = $age !== null ? $age <= 60 : in_array($target->category_code, ['infant', 'toddler', 'preschool'], true);
        if ($child) {
            $results['weight_age'] = $this->childIndex(
                'Berat badan menurut umur (BB/U)',
                $weight,
                $ageReason,
                $this->row(1 + $offset, $age),
                'weight'
            );
            $results['height_age'] = $this->childIndex(
                'Panjang/tinggi menurut umur (PB/TB/U)',
                $correctedHeight,
                $ageReason,
                $this->row(($recumbent ? 2 : 3) + $offset, $age),
                'height'
            );
            $results['weight_height'] = $this->weightHeight($weight, $correctedHeight, ($recumbent ? 4 : 5) + $offset, $ageReason);
            $results['weight_trend'] = $this->trend($target, $date, $age, $gender, $weight, $editingVisitId);
        }

        $adultReason = $pregnant ? 'Pemeriksaan kehamilan memerlukan penilaian petugas.' : ($ageReason ?? ($age < 228 ? 'Acuan otomatis ini hanya untuk dewasa dan lansia; hasil anak dinilai petugas.' : null));
        if (in_array($target->category_code, ['school', 'adolescent', 'adult', 'elderly', 'general'], true)) {
            $systolic = $this->measurement($data, 'blood_pressure', 'systolic_mmhg');
            $diastolic = $this->measurement($data, 'blood_pressure', 'diastolic_mmhg');
            $pressureReason = $adultReason ?? ($systolic === null || $diastolic === null ? 'Lengkapi tekanan darah yang diperiksa.' : null);
            $pressureReason ??= ! ($data['blood_pressure_confirmed'] ?? false) ? 'Konfirmasikan bahwa nilai merupakan hasil pengukuran ulang sesuai prosedur.' : null;
            if ($pressureReason !== null) {
                $results['pressure'] = $this->unavailable('Tekanan darah', $pressureReason);
            } elseif ($systolic <= $diastolic) {
                $results['pressure'] = $this->unavailable('Tekanan darah', 'Periksa angka sistolik dan diastolik.');
            } elseif ($systolic >= 140 || $diastolic >= 90) {
                $results['pressure'] = $this->result(
                    'Tekanan darah',
                    'high',
                    'Mencapai ambang tekanan darah tinggi; perlu penilaian petugas',
                    'high',
                    self::PRESSURE_SOURCE
                );
            } elseif ($systolic < 90 || $diastolic < 60) {
                $results['pressure'] = $this->result('Tekanan darah', 'low', 'Tekanan darah rendah; perlu penilaian petugas', 'low', [
                    'title' => 'Kemenkes — Hipotensi',
                    'url' => 'https://keslan.kemkes.go.id/view_artikel/2324/hipotensi',
                    'section' => 'Batas 90/60 mmHg',
                ]);
            } else {
                $results['pressure'] = $this->result(
                    'Tekanan darah',
                    'below_threshold',
                    'Tidak mencapai ambang tekanan darah tinggi (140/90 mmHg)',
                    'normal',
                    self::PRESSURE_SOURCE
                );
            }
            $results['glucose'] = $this->glucose(
                $this->measurement($data, 'glucose', 'glucose_mg_dl'),
                $data['glucose_context'] ?? 'unknown',
                $adultReason
            );
            $waist = $this->measurement($data, 'waist', 'waist_cm');
            if ($waist !== null) {
                $results['waist'] = $adultReason ? $this->unavailable('Lingkar perut', $adultReason) : $this->result(
                    'Lingkar perut',
                    $waist > ($gender === 'L' ? 90 : 80) ? 'risk' : 'normal',
                    $waist > ($gender === 'L' ? 90 : 80) ? 'Di atas batas lingkar perut; perlu penilaian petugas' : 'Tidak melebihi batas lingkar perut',
                    null,
                    self::ADULT_SOURCE,
                    $waist
                );
            }
        }

        $map = [
            'bmi' => 'bmi_status',
            'weight_age' => 'weight_age_result',
            'height_age' => 'height_age_result',
            'weight_height' => 'weight_height_result',
            'weight_trend' => 'weight_trend',
            'pressure' => 'bp_status',
            'glucose' => 'blood_sugar_status',
        ];
        $fields = array_fill_keys(self::FIELDS, 'not_applicable');
        foreach ($map as $key => $field) {
            if (isset($results[$key])) {
                $fields[$field] = $results[$key]['mapped_value'] ?? 'unknown';
            }
        }
        if ($age !== null && $age <= 60) {
            $fields['bmi_status'] = 'not_applicable';
        }

        return [
            'mode' => 'automatic',
            'version' => self::VERSION,
            'reference_version' => $this->reference['version'],
            'reference_hash' => $this->reference['source']['sha256'],
            'calculated_at' => now()->toIso8601String(),
            'reference_date' => $day->toDateString(),
            'age_months' => $age,
            'gender' => $gender,
            'pregnant' => $pregnant,

            'inputs' => array_intersect_key($data, array_flip([
                ...Examination::MEASUREMENTS,
                ...Examination::MEASUREMENT_STATES,
                ...self::CONTEXT,
            ])),

            'corrected_height_cm' => $correctedHeight === null ? null : round($correctedHeight, 2),
            'results' => $results,
            'field_values' => $fields,
        ];
    }

    /** @param array<string, mixed> $data */
    private function measurement(array $data, string $state, string $field): ?float
    {
        $value = $data[$field] ?? null;

        return ($data[$state.'_state'] ?? null) === 'measured' && is_numeric($value) && is_finite((float) $value) && (float) $value > 0 ? (float) $value : null;
    }

    /** @return list<float>|null */
    private function row(int $table, int|float|null $key): ?array
    {
        return $key === null ? null : ($this->reference['tables'][(string) $table][(string) $key] ?? null);
    }

    /** @param list<float>|null $row @return array<string, mixed> */
    private function childIndex(string $title, ?float $value, ?string $reason, ?array $row, string $type): array
    {
        if ($reason || $value === null || ! $row) {
            return $this->unavailable(
                $title,
                $reason ?? 'Lengkapi pengukuran dan cara mengukur yang sesuai dengan tabel usia anak.'
            );
        }
        [$category, $label] = match (true) {
            $value < $row[0] => ['very_low', $type === 'weight' ? 'Berat badan sangat kurang' : 'Sangat pendek'],
            $value < $row[1] => ['low', $type === 'weight' ? 'Berat badan kurang' : 'Pendek'],
            $value <= $row[$type === 'weight' ? 4 : 6] => ['normal', $type === 'weight' ? 'Berat badan normal' : 'Normal'],
            default => ['high', $type === 'weight' ? 'Risiko berat badan lebih' : 'Tinggi'],
        };

        return $this->result(
            $title,
            $category,
            $label,
            $category === 'normal' ? ($type === 'weight' ? 'good' : 'normal') : 'other',
            self::CHILD_SOURCE,
            $value
        );
    }

    /** @param list<float> $row @param array{float, float}|null $bmiMeasurements @return array<string, mixed> */
    private function nutrition(string $title, float $value, array $row, bool $underFive, ?float $display = null, ?array $bmiMeasurements = null): array
    {
        $compare = fn (float $threshold): int => $bmiMeasurements === null ? $value <=> $threshold : $this->compareBmi($bmiMeasurements[0], $bmiMeasurements[1], $threshold);
        [$category, $label, $mapped] = match (true) {
            $compare($row[0]) < 0 => ['severe', 'Gizi buruk', 'very_thin'],
            $compare($row[1]) < 0 => ['low', 'Gizi kurang', 'thin'],
            $compare($row[4]) <= 0 => ['normal', 'Gizi baik', 'normal'],
            $underFive && $compare($row[5]) <= 0 => ['risk', 'Berisiko gizi lebih', 'overweight'],
            $compare($row[$underFive ? 6 : 5]) <= 0 => ['overweight', 'Gizi lebih', 'overweight'],
            default => ['obese', 'Obesitas (kategori antropometri)', 'obese'],
        };

        return $this->result($title, $category, $label, $mapped, self::CHILD_SOURCE, $display ?? $value);
    }

    /**
     * Compare hundredth-unit measurements by integer cross multiplication to preserve exact cutoffs.
     */
    private function compareBmi(float $weight, float $height, float $threshold): int
    {
        $weightUnits = (int) round($weight * 100);
        $heightUnits = (int) round($height * 100);
        $thresholdUnits = (int) round($threshold * 100);

        return $weightUnits * 100_000_000 <=> $thresholdUnits * $heightUnits * $heightUnits;
    }

    /** @return array<string, mixed> */
    private function weightHeight(?float $weight, ?float $height, int $table, ?string $reason): array
    {
        $title = 'Berat menurut panjang/tinggi (BB/PB atau BB/TB)';
        if ($reason || $weight === null || $height === null) {
            return $this->unavailable($title, $reason ?? 'Lengkapi berat, panjang/tinggi, dan cara pengukuran anak.');
        }
        $lower = $this->row($table, floor(round($height, 2) * 2) / 2);
        $upper = $this->row($table, ceil(round($height, 2) * 2) / 2);
        if (! $lower || ! $upper) {
            return $this->unavailable(
                $title,
                'Panjang/tinggi di luar tabel atau baris sumber tidak lengkap; perlu penilaian petugas.'
            );
        }
        $lowResult = $this->nutrition($title, $weight, $lower, true);
        $highResult = $this->nutrition($title, $weight, $upper, true);
        if ($lowResult['category'] !== $highResult['category']) {
            return $this->unavailable(
                $title,
                'Nilai berada dekat batas kategori di antara dua baris tabel; perlu penilaian petugas.'
            );
        }
        $lowResult['mapped_value'] = $lowResult['category'] === 'normal' ? 'good' : 'other';
        $lowResult['table_height_bounds_cm'] = [floor(round($height, 2) * 2) / 2, ceil(round($height, 2) * 2) / 2];

        return $lowResult;
    }

    /** @return array<string, mixed> */
    private function glucose(?float $value, string $context, ?string $reason): array
    {
        $title = 'Gula darah';
        if ($reason || $value === null || ! in_array($context, ['fasting_plasma', 'ttgo_2h_plasma', 'random_capillary'], true)) {
            return $this->unavailable($title, $reason ?? 'Lengkapi nilai dan jenis pemeriksaan gula darah.');
        }
        if ($value < 70) {
            return $this->result(
                $title,
                'low',
                'Gula darah rendah; perlu penilaian petugas',
                'low',
                self::GLUCOSE_SOURCE,
                $value
            );
        }
        if ($context === 'random_capillary') {
            return $value >= 200 ? $this->result(
                $title,
                'high',
                'Mencapai ambang skrining sewaktu; perlu pemeriksaan konfirmasi',
                'high',
                self::GLUCOSE_SOURCE,
                $value
            )
                : $this->unavailable(
                    $title,
                    'Satu hasil gula darah sewaktu di bawah 200 tidak menetapkan status normal; perlu pemeriksaan sesuai petugas.',
                    'unknown',
                    $value
                );
        }
        $normalLimit = $context === 'fasting_plasma' ? 100 : 140;
        $highLimit = $context === 'fasting_plasma' ? 126 : 200;

        return match (true) {
            $value < $normalLimit => $this->result(
                $title,
                'normal',
                'Dalam rentang rujukan pemeriksaan ini',
                'normal',
                self::GLUCOSE_SOURCE,
                $value
            ),
            $value < $highLimit => $this->result(
                $title,
                'risk',
                'Di atas rentang normal; perlu pemeriksaan konfirmasi',
                'high',
                self::GLUCOSE_SOURCE,
                $value
            ),
            default => $this->result(
                $title,
                'high',
                'Mencapai ambang skrining tinggi; perlu pemeriksaan konfirmasi',
                'high',
                self::GLUCOSE_SOURCE,
                $value
            ),
        };
    }

    /** @return array<string, mixed> */
    private function trend(TargetMembership $target, CarbonImmutable $date, ?int $age, string $gender, ?float $weight, ?int $editingVisitId): array
    {
        $title = 'Perubahan berat badan';
        if ($age === null || $weight === null) {
            return $this->unavailable($title, 'Lengkapi tanggal lahir pasti dan berat badan yang diperiksa.');
        }
        $previous = Visit::query()->where('resident_id', $target->resident_id)->where('record_status', 'finalized')
            ->where('visited_at', '<', $date->utc())->when($editingVisitId, fn ($query) => $query->whereKeyNot($editingVisitId))
            ->with('examination:id,visit_id,weight_state,weight_kg')->orderByDesc('visited_at')->orderByDesc('id')->get()
            ->first(fn (Visit $visit) => $visit->examination?->weight_state === 'measured' && (float) $visit->examination->weight_kg > 0);
        if (! $previous) {
            return $this->unavailable($title, 'Belum ada pengukuran berat terdahulu yang sudah final.');
        }
        $oldDate = CarbonImmutable::instance($previous->visited_at)->setTimezone('Asia/Jakarta')->startOfDay();
        $delta = round($weight - (float) $previous->examination->weight_kg, 2);
        $interval = (int) $oldDate->diffInMonths($date->startOfDay());
        $row = $age <= 24 && in_array($interval, [3, 4, 6], true) && $oldDate->addMonthsNoOverflow($interval)->isSameDay($date)
            ? ($this->reference['tables'][(string) ([3 => 17, 4 => 18, 6 => 19][$interval])][($age - $interval).'-'.$age] ?? null) : null;
        $result = $this->unavailable(
            $title,
            'Perubahan angka ditampilkan; kategori naik sesuai standar memerlukan interval 3, 4, atau 6 bulan pada usia 0–24 bulan.',
            'unknown',
            $delta
        );
        if ($row) {
            $minimum = $row[$gender === 'L' ? 0 : 1];
            $adequate = round($delta * 1000) >= $minimum;
            $result = $this->result(
                $title,
                $adequate ? 'adequate' : 'below_standard',
                $adequate ? 'Kenaikan memenuhi standar interval '.$interval.' bulan' : 'Kenaikan di bawah standar interval '.$interval.' bulan; perlu penilaian petugas',
                $adequate ? 'up' : 'other',
                self::CHILD_SOURCE,
                $delta
            );
            $result['minimum_gain_g'] = $minimum;
        }
        $result['previous'] = [
            'visit_id' => $previous->id,
            'date' => $oldDate->toDateString(),
            'weight_kg' => $previous->examination->weight_kg,
        ];

        return $result;
    }

    /** @param array<string, string> $source @return array<string, mixed> */
    private function result(string $title, string $category, string $label, ?string $mapped, array $source, ?float $value = null): array
    {
        return [
            'title' => $title,
            'status' => 'calculated',
            'category' => $category,
            'label' => $label,
            'mapped_value' => $mapped,
            'value' => $value,
            'source' => $source,
        ];
    }

    /** @return array<string, mixed> */
    private function unavailable(string $title, string $reason, string $mapped = 'unknown', ?float $value = null): array
    {
        return [
            'title' => $title,
            'status' => 'unavailable',
            'category' => null,
            'label' => 'Belum dapat dihitung',
            'reason' => $reason,
            'mapped_value' => $mapped,
            'value' => $value,
            'source' => null,
        ];
    }
}
