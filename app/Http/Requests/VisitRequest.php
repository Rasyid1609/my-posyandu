<?php

namespace App\Http\Requests;

use App\Models\Examination;
use App\Services\ClinicalCalculations;
use App\Services\ClinicalFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('visits.record') && $this->user()->can('visits.view') && $this->user()->can('residents.view');
    }

    public function rules(): array
    {
        $rules = ['revision' => ['required', 'integer', 'min:1'],
            'assessment_mode' => ['sometimes', Rule::in(['automatic', 'manual'])],
            'height_method' => ['nullable', Rule::in(['unknown', 'standing', 'recumbent'])],
            'glucose_context' => [
                'nullable',
                Rule::in(['unknown', 'fasting_plasma', 'ttgo_2h_plasma', 'random_capillary']),
            ],
            'blood_pressure_confirmed' => ['sometimes', 'boolean'],
            'calculation_override_reason' => ['nullable', 'string', 'min:5', 'max:500'],
            'visited_at' => [
                'required',
                'date_format:Y-m-d\\TH:i',
                'before_or_equal:'.now('Asia/Jakarta')->format('Y-m-d\\TH:i'),
            ]];
        foreach (['weight', 'height', 'blood_pressure', 'glucose', 'head', 'arm', 'waist'] as $measurement) {
            $rules[$measurement.'_state'] = [
                in_array($measurement, ['head', 'arm', 'waist']) ? 'sometimes' : 'required',
                Rule::in(Examination::STATES),
            ];
        }
        foreach ([
            'weight_kg' => ['weight', 999.99],
            'height_cm' => ['height', 999.99],
            'systolic_mmhg' => ['blood_pressure', 9999.99],
            'diastolic_mmhg' => ['blood_pressure', 9999.99],
            'glucose_mg_dl' => ['glucose', 99999.99],
            'head_cm' => ['head', 9999.99],
            'arm_cm' => ['arm', 9999.99],
            'waist_cm' => ['waist', 9999.99],
        ] as $field => [$measurement, $maximum]) {
            $rules[$field] = [
                'nullable',
                'required_if:'.$measurement.'_state,measured',
                'prohibited_unless:'.$measurement.'_state,measured',
                'numeric',
                'gt:0',
                'max:'.$maximum,
                'decimal:0,2',
            ];
        }
        $target = $this->route('target');
        $maternal = $target && ($target->is_pregnant_snapshot || $target->is_postpartum_snapshot || $target->is_breastfeeding_snapshot);
        $allowed = ClinicalFields::forTarget($target?->category_code ?? 'general', $maternal, true);
        $rules['activity_session_id'] = [
            'nullable',
            'integer',
            Rule::exists('activity_sessions', 'id')->where('reporting_period_id', $this->route('period')?->id),
        ];
        $hasAssessment = false;
        foreach (ClinicalFields::definitions() as $code => $field) {
            if ($this->input('assessment_mode') === 'automatic' && in_array($code, ClinicalCalculations::FIELDS, true)) {
                $rules[$code] = ['exclude'];

                continue;
            }
            $values = array_keys(isset($allowed[$code]) ? $field['options'] : ClinicalFields::MISSING);
            if ($code === 'anemia_status' && $target?->gender_snapshot === 'L') {
                $values = array_keys(ClinicalFields::MISSING);
            }
            $rules[$code] = $field['sensitive'] && ! $this->user()->can('sensitive.record') ? ['prohibited'] : ['sometimes', Rule::in($values)];
            if ($this->filled($code) && is_string($this->input($code)) && ! array_key_exists($this->input($code), ClinicalFields::MISSING)) {
                $hasAssessment = true;
            }
        }
        $rules['assessor_name'] = [$hasAssessment ? 'required' : 'nullable', 'string', 'max:150'];
        $rules['assessment_source'] = [$hasAssessment ? 'required' : 'nullable', 'string', 'max:500'];
        foreach (['referral_destination' => 200, 'referral_reason' => 1000] as $field => $maximum) {
            $rules[$field] = [
                'nullable',
                'required_if:referral_result,yes',
                'prohibited_unless:referral_result,yes',
                'string',
                'max:'.$maximum,
            ];
        }
        $rules['follow_up_date'] = ['nullable', 'prohibited_unless:referral_result,yes', 'date_format:Y-m-d'];
        $rules['follow_up_status'] = [
            'nullable',
            'prohibited_unless:referral_result,yes',
            Rule::in(['pending', 'scheduled', 'completed', 'declined']),
        ];
        $rules['follow_up_note'] = ['nullable', 'prohibited_unless:referral_result,yes', 'string', 'max:1000'];
        $visit = $this->route('visit');
        if ($visit && ($visit->examination?->calculation_details['mode'] ?? null) === 'automatic' && $this->input('assessment_mode', 'manual') !== 'automatic') {
            $rules['calculation_override_reason'][] = 'required';
            $rules['assessor_name'][] = 'required';
            $rules['assessment_source'][] = 'required';
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'calculation_override_reason.required' => 'Cantumkan alasan mengganti hasil otomatis dengan hasil petugas.',
            'required_if' => 'Nilai wajib diisi saat pemeriksaan berstatus diperiksa.',
            'prohibited_unless' => 'Kosongkan nilai jika pemeriksaan tidak berstatus diperiksa.',
            'gt' => 'Nilai pengukuran harus lebih dari nol.',
            'max' => 'Nilai melebihi kapasitas penyimpanan; periksa satuan.',
            'decimal' => 'Gunakan paling banyak dua angka desimal.',
            'visited_at.before_or_equal' => 'Waktu kunjungan tidak boleh di masa depan.',
            'visited_at.date_format' => 'Waktu kunjungan tidak valid.',
            'assessor_name.required' => 'Cantumkan petugas yang memberikan hasil pelayanan/penilaian.',
            'assessment_source.required' => 'Cantumkan sumber hasil atau catatan pemeriksaan petugas.',
            'referral_destination.required_if' => 'Tujuan rujukan wajib diisi.',
            'referral_reason.required_if' => 'Alasan rujukan wajib diisi.',
        ];
    }
}
