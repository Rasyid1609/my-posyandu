<?php

namespace App\Http\Requests;

use App\Models\Examination;
use App\Services\ClinicalCalculations;
use Illuminate\Support\Arr;

class ClinicalCalculationRequest extends VisitRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return Arr::only(parent::rules(), [
            'visited_at',
            ...Examination::MEASUREMENTS,
            ...Examination::MEASUREMENT_STATES,
            ...ClinicalCalculations::CONTEXT,
        ]) + ['visit_id' => ['nullable', 'integer', 'min:1']];
    }
}
