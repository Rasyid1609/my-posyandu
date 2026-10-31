<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole('superadmin') && $this->user()->can('accounts.manage');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users')->ignore($this->route('user')),
            ],
            'password' => [
                $this->route('user') ? 'nullable' : 'required',
                'string',
                Password::min(12),
                'max:255',
            ],
            'role' => ['required', Rule::in(array_keys(config('posyandu.roles')))],
            'is_active' => ['required', 'boolean'],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', Rule::in(array_keys(config('posyandu.permissions')))],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'Email sudah digunakan.',
            'password.min' => 'Kata sandi minimal 12 karakter.',
            'required' => ':attribute wajib diisi.',
        ];
    }
}
