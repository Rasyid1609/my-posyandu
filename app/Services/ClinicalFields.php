<?php

namespace App\Services;

class ClinicalFields
{
    public const MISSING = [
        'not_examined' => 'Tidak diperiksa',
        'unknown' => 'Belum diketahui',
        'not_applicable' => 'Tidak berlaku',
        'declined' => 'Menolak',
    ];

    public const AGE_CODES = [
        'infant',
        'toddler',
        'preschool',
        'school',
        'adolescent',
        'adult',
        'elderly',
        'general',
    ];

    public const AGE_LABELS = [
        'infant' => 'Bayi',
        'toddler' => 'Balita',
        'preschool' => 'Prasekolah',
        'school' => 'Usia sekolah',
        'adolescent' => 'Remaja',
        'adult' => 'Dewasa',
        'elderly' => 'Lansia',
        'general' => 'Usia belum diketahui',
    ];

    public static function definitions(): array
    {
        $all = self::AGE_CODES;
        $child = ['infant', 'toddler', 'preschool'];
        $older = ['school', 'adolescent', 'adult', 'elderly'];
        $defs = [];
        $add = function (string $code, string $label, array $options, array $groups, bool $sensitive = false, bool $maternal = false) use (&$defs): void {
            $defs[$code] = compact('label', 'groups', 'sensitive', 'maternal') + ['options' => self::MISSING + $options];
        };
        $normal = [
            'normal' => 'Normal (hasil petugas)',
            'low' => 'Rendah (hasil petugas)',
            'high' => 'Tinggi (hasil petugas)',
        ];
        $yesNo = ['yes' => 'Ya', 'no' => 'Tidak'];
        $nutrition = ['good' => 'Gizi baik', 'other' => 'Di luar gizi baik'];
        $add('bmi_status', 'Status IMT', [
            'very_thin' => 'Sangat kurus',
            'thin' => 'Kurus',
            'normal' => 'Normal',
            'overweight' => 'Berat badan lebih',
            'obese' => 'Obesitas',
        ], $older);
        $add('bp_status', 'Hasil tekanan darah', $normal, $older);
        $add('blood_sugar_status', 'Hasil gula darah', $normal, $older);
        $add(
            'anemia_status',
            'Anemia (remaja putri)',
            ['yes' => 'Anemia', 'no' => 'Tidak anemia'],
            ['adolescent', 'school']
        );
        $add('tb_symptom_status', 'Bergejala TBC (hasil skrining petugas)', $yesNo, $all);
        $add('heeadsss_status', 'HEEADSSS (hasil petugas)', $yesNo, ['school', 'adolescent'], true);
        $add(
            'mental_health_result',
            'Skrining kesehatan jiwa',
            ['normal' => 'Kelompok normal', 'risk' => 'Kelompok perlu tindak lanjut'],
            ['adult'],
            true
        );
        $add(
            'puma_result',
            'PUMA/PPOK (hasil petugas)',
            ['normal' => 'Normal', 'high' => 'Tinggi'],
            ['adult', 'elderly']
        );
        $add('dependency_result', 'AKS (hasil petugas)', [
            'independent' => 'A / Mandiri',
            'mild_moderate' => 'B / Ringan-sedang',
            'severe_total' => 'C / Berat-total',
        ], ['elderly']);
        foreach ([
            'cognition' => 'Kognitif',
            'mobility' => 'Gerak',
            'nutrition' => 'Malnutrisi',
            'hearing' => 'Pendengaran',
            'vision' => 'Penglihatan',
            'depression' => 'Depresi',
        ] as $key => $label) {
            $add(
                'skilas_'.$key,
                'SKILAS — '.$label,
                $yesNo,
                ['elderly'],
                in_array($key, ['cognition', 'depression'], true)
            );
        }
        $add(
            'development_check',
            'Ceklis perkembangan',
            ['complete' => 'Lengkap', 'incomplete' => 'Tidak lengkap'],
            $child
        );
        $add(
            'weight_trend',
            'Tren berat badan',
            ['up' => 'Naik', 'other' => 'Tidak naik / BGM / atas garis oranye'],
            $child
        );
        $add(
            'weight_age_result',
            'BB/U (hasil petugas)',
            ['good' => 'Berat badan normal', 'other' => 'Di luar berat badan normal'],
            $child
        );
        $add(
            'height_age_result',
            'PB/TB/U (hasil petugas)',
            ['normal' => 'Normal', 'other' => 'Di luar normal'],
            $child
        );
        $add('weight_height_result', 'BB/PB atau BB/TB (hasil petugas)', $nutrition, $child);
        $add(
            'head_result',
            'Lingkar kepala (hasil petugas)',
            ['normal' => 'Normal', 'other' => 'Di luar normal'],
            $child
        );
        $add(
            'arm_result',
            'Lingkar lengan atas anak (hasil petugas)',
            ['normal' => 'Normal', 'other' => 'Gizi kurang/buruk'],
            $child
        );
        foreach ([
            'exclusive_breastfeeding' => 'ASI eksklusif',
            'complementary_feeding' => 'MPASI sesuai',
            'immunization' => 'Imunisasi',
            'vitamin_a' => 'Vitamin A',
            'deworming' => 'Obat cacing',
            'local_food' => 'Makanan tambahan/pangan lokal',
            'child_illness' => 'Anak sakit',
        ] as $key => $label) {
            $add($key, $label, $yesNo, $child);
        }
        foreach ([
            'maternal_weight' => 'Berat badan ibu',
            'maternal_arm' => 'LILA ibu',
            'maternal_pressure' => 'Tekanan darah ibu',
        ] as $key => $label) {
            $add($key, $label.' (warna dari petugas)', ['green' => 'Hijau', 'red' => 'Merah'], [], false, true);
        }
        foreach ([
            'ttd_received' => 'Menerima TTD',
            'ttd_daily' => 'Konsumsi TTD setiap hari',
            'pmt_received' => 'Menerima PMT KEK',
            'pmt_daily' => 'Konsumsi PMT setiap hari',
            'pregnancy_class' => 'Kelas ibu hamil',
            'postpartum_vitamin' => 'Vitamin A nifas',
            'postpartum_contraception' => 'KB pascapersalinan',
        ] as $key => $label) {
            $add($key, $label, $yesNo, [], false, true);
        }
        $add('education_result', 'Mendapat edukasi', $yesNo, $all);
        $add('referral_result', 'Dirujuk oleh petugas', $yesNo, $all);

        return $defs;
    }

    public static function forTarget(string $category, bool $maternal, bool $sensitive): array
    {
        return array_filter(
            self::definitions(),
            fn (array $field) => (! $field['sensitive'] || $sensitive) && (($category === 'general' && ! $field['maternal']) || in_array($category, $field['groups'], true) || ($maternal && $field['maternal']))
        );
    }
}
