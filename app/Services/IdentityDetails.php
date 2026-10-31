<?php

namespace App\Services;

use App\Models\Household;
use App\Models\Resident;
use Illuminate\Validation\ValidationException;

class IdentityDetails
{
    public static function lookup(string $kind, ?string $value): ?string
    {
        return $value === null || $value === '' ? null : hash_hmac('sha256', $kind.'|'.$value, config('app.key'));
    }

    public function resident(Resident $resident, array $data): void
    {
        if (array_key_exists('national_id_number', $data)) {
            $value = $data['national_id_number'];
            $hash = self::lookup('national_id', $value);
            if ($hash && Resident::withTrashed()
                ->where('national_id_lookup_hash', $hash)
                ->when($resident->exists, fn ($q) => $q->whereKeyNot($resident->id))
                ->exists()) {
                throw ValidationException::withMessages(['national_id_number' => 'NIK sudah tercatat. Periksa data warga, termasuk arsip.']);
            }
            $resident->forceFill([
                'national_id_ciphertext' => $value,
                'national_id_lookup_hash' => $hash,
                'national_id_number' => null,
            ]);
        }
        if (array_key_exists('phone_number', $data)) {
            $resident->forceFill(['phone_ciphertext' => $data['phone_number'], 'phone_number' => null]);
        }
    }

    public function household(Household $household, array $data): void
    {
        if (! array_key_exists('family_card_number', $data)) {
            return;
        }
        $value = $data['family_card_number'];
        $hash = self::lookup('family_card', $value);
        if ($hash && Household::withTrashed()
            ->where('family_card_lookup_hash', $hash)
            ->when($household->exists, fn ($q) => $q->whereKeyNot($household->id))
            ->exists()) {
            throw ValidationException::withMessages(['family_card_number' => 'Nomor KK sudah tercatat. Gunakan keluarga yang ada atau periksa arsip.']);
        }
        $household->forceFill([
            'family_card_ciphertext' => $value,
            'family_card_lookup_hash' => $hash,
            'family_card_number' => null,
        ]);
    }

    public function display(?string $value, bool $reveal): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $reveal ? $value : str_repeat('*', max(4, strlen($value) - 4)).substr($value, -4);
    }
}
