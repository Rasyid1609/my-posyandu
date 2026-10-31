<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TargetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('targets.manage') && $this->user()->can('residents.view');
    }

    public function rules(): array
    {
        return [
            'resident_id' => ['required', 'integer', 'exists:residents,id'],
            'revision' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Alasan pemilihan sasaran wajib diisi.',
            'resident_id.exists' => 'Warga tidak ditemukan.',
        ];
    }
}
