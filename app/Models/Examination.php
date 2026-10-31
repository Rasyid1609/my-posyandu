<?php

namespace App\Models;

use App\Casts\EncryptedDecimal;
use App\Services\ClinicalFields;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'category_code',
    'weight_kg',
    'height_cm',
    'weight_state',
    'height_state',
    'blood_pressure_state',
    'glucose_state',
    'systolic_mmhg',
    'diastolic_mmhg',
    'glucose_mg_dl',
])]
class Examination extends Model
{
    protected $hidden = [
        'mental_health_result',
        'heeadsss_status',
        'skilas_cognition',
        'skilas_depression',
    ];

    public const STATES = ['not_examined', 'measured', 'unknown', 'not_applicable', 'declined'];

    public const MEASUREMENTS = [
        'weight_kg',
        'height_cm',
        'systolic_mmhg',
        'diastolic_mmhg',
        'glucose_mg_dl',
        'head_cm',
        'arm_cm',
        'waist_cm',
    ];

    public const MEASUREMENT_STATES = [
        'weight_state',
        'height_state',
        'blood_pressure_state',
        'glucose_state',
        'head_state',
        'arm_state',
        'waist_state',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $examination): void {
            foreach ([...self::MEASUREMENT_STATES, ...array_keys(ClinicalFields::definitions())] as $field) {
                if (! array_key_exists($field, $examination->getAttributes())) {
                    $examination->setAttribute($field, 'not_examined');
                }
            }
            foreach (['received_education', 'is_referred'] as $field) {
                if (! array_key_exists($field, $examination->getAttributes())) {
                    $examination->setAttribute($field, false);
                }
            }
        });
    }

    protected function casts(): array
    {
        return [
            ...array_fill_keys(self::MEASUREMENTS, EncryptedDecimal::class),
            ...array_fill_keys([
                ...self::MEASUREMENT_STATES,
                ...array_keys(ClinicalFields::definitions()),
                'assessment_source',
                'assessor_name',
            ], 'encrypted'),
            'received_education' => 'encrypted:array',
            'is_referred' => 'encrypted:array',
            'calculation_details' => 'encrypted:array',
        ];
    }
}
