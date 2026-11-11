<?php

namespace App\Http\Requests;

use App\Models\Resident;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $resident = $this->route('resident');

        return $resident ? $this->user()->can('update', $resident) : $this->user()->can('create', Resident::class);
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:150'],
            'national_id_number' => ['sometimes', 'nullable', 'string', 'regex:/^[0-9]{16}$/D'],
            'phone_number' => [
                'sometimes',
                'nullable',
                'string',
                'max:30',
                'regex:/^\+?[0-9][0-9 ()-]{5,28}[0-9]$/D',
            ],
            'gender' => ['required', Rule::in(['L', 'P'])],
            'birth_date' => [
                'nullable',
                'date_format:Y-m-d',
                'before_or_equal:'.now('Asia/Jakarta')->toDateString(),
            ],
            'is_birth_date_estimated' => ['required', 'boolean'],
            'address' => ['nullable', 'string', 'max:1000'],
            'household_id' => [
                'nullable',
                'integer',
                Rule::exists('households', 'id')->whereNull('deleted_at'),
            ],
            'guardian_id' => [
                'nullable',
                'integer',
                Rule::exists('residents', 'id')
                    ->whereNull('deleted_at')
                    ->where('household_id', $this->integer('household_id', $this->route('resident')?->household_id ?? 0)),
                Rule::notIn([$this->route('resident')?->id]),
            ],
            'family_relationship' => ['nullable', 'string', 'max:30'],
            'family_card_number' => ['sometimes', 'nullable', 'string', 'regex:/^[0-9]{16}$/D'],
            'is_head_of_household' => ['sometimes', 'boolean'],
            'rt' => ['nullable', 'string', 'max:10'],
            'rw' => ['nullable', 'string', 'max:10'],
            'residency_status' => ['required', Rule::in(['active', 'moved', 'deceased', 'inactive'])],
            'effective_date' => [
                'required',
                'date_format:Y-m-d',
                'before_or_equal:'.now('Asia/Jakarta')->toDateString(),
            ],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
            'revision' => [$this->route('resident') ? 'required' : 'nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'before_or_equal' => ':attribute tidak boleh di masa depan.',
            'date_format' => ':attribute harus berupa tanggal yang valid.',
            'min' => ':attribute terlalu singkat.',
            'national_id_number.regex' => 'NIK harus terdiri dari 16 digit, atau dikosongkan.',
            'family_card_number.regex' => 'Nomor KK harus terdiri dari 16 digit, atau dikosongkan.',
            'phone_number.regex' => 'Kontak harus berupa nomor telepon yang valid, atau dikosongkan.',
            'guardian_id.exists' => 'Pilih orang tua / wali dari keluarga yang dipilih.',
            'guardian_id.not_in' => 'Warga tidak dapat dipilih sebagai wali dirinya sendiri.',
        ];
    }

    public function attributes(): array
    {
        return [
            'full_name' => 'Nama lengkap',
            'gender' => 'Jenis kelamin',
            'birth_date' => 'Tanggal lahir',
            'reason' => 'Alasan pencatatan',
            'effective_date' => 'Tanggal efektif',
        ];
    }
}
