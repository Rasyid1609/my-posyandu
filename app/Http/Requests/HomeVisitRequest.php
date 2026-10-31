<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HomeVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('residents.view') && $this->user()->can('visits.record');
    }

    public function rules(): array
    {
        $rules = [
            'revision' => ['required', 'integer', 'min:1'],
            'household_id' => ['required', 'integer', 'exists:households,id'],

            'visited_on' => [
                'required',
                'date_format:Y-m-d',
                'before_or_equal:'.now('Asia/Jakarta')->toDateString(),
            ],

            'reason' => ['required', 'string', 'min:5', 'max:500'],
            'follow_up' => ['nullable', 'string', 'max:1000'],

            'staff_ids' => ['required', 'array', 'min:1', 'max:20'],
            'staff_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('users', 'id')->where('is_active', true),
            ],

            'residents' => ['required', 'array', 'min:1', 'max:100'],

            'residents.*.resident_id' => ['required', 'integer', 'distinct', 'exists:residents,id'],
        ];
        foreach (['no_service', 'danger_sign', 'tb_symptom', 'irregular_medication'] as $field) {
            $rules['residents.*.'.$field] = ['required', Rule::in(['yes', 'no', 'unknown', 'not_applicable', 'declined'])];
        }

        return $rules;
    }
}
