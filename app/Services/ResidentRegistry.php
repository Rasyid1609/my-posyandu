<?php

namespace App\Services;

use App\Models\Resident;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ResidentRegistry
{
    public function __construct(private AuditTrail $audit, private IdentityDetails $identities) {}

    public function save(array $data, ?Resident $resident = null): Resident
    {
        return DB::transaction(function () use ($data, $resident) {
            if ($resident) {
                $resident = Resident::query()->lockForUpdate()->findOrFail($resident->id);
                if ($resident->revision !== (int) $data['revision']) {
                    abort(409, 'Data warga telah berubah. Muat ulang sebelum menyimpan.');
                }
            }
            $duplicate = Resident::withTrashed()
                ->whereRaw('LOWER(full_name) = ?', [Str::lower(trim($data['full_name']))])
                ->where('gender', $data['gender'])
                ->where('birth_date', $data['birth_date'] ?? null)
                ->when($resident, fn ($query) => $query->whereKeyNot($resident->id))
                ->exists();
            if ($duplicate) {
                throw ValidationException::withMessages(['full_name' => 'Kemungkinan warga duplikat. Periksa nama, tanggal lahir, dan jenis kelamin pada data yang ada.']);
            }
            $previousStatus = $resident?->residency_status;
            $resident ??= new Resident(['registration_number' => 'PJ-'.Str::upper(Str::ulid())]);
            $resident->fill(Arr::only($data, [
                'full_name',
                'gender',
                'birth_date',
                'is_birth_date_estimated',
                'address',
                'rt',
                'rw',
                'residency_status',
                'household_id',
                'guardian_id',
                'family_relationship',
            ]));
            if ($resident->guardian_id && (! $resident->household_id || ! Resident::whereKey($resident->guardian_id)->where('household_id', $resident->household_id)->exists())) {
                throw ValidationException::withMessages(['guardian_id' => 'Pilih orang tua / wali dari keluarga yang dipilih.']);
            }
            $guardianId = $resident->guardian_id;
            $seen = $resident->exists ? [$resident->id] : [];
            while ($guardianId) {
                if (in_array($guardianId, $seen) || count($seen) > 50) {
                    throw ValidationException::withMessages(['guardian_id' => 'Relasi wali tidak boleh membentuk lingkaran.']);
                }
                $seen[] = $guardianId;
                $guardianId = Resident::whereKey($guardianId)->value('guardian_id');
            }
            $resident->full_name = trim($resident->full_name);
            $resident->revision = ($resident->revision ?? 0) + 1;
            $this->identities->resident($resident, $data);
            $wasExisting = $resident->exists;
            try {
                $resident->save();
            } catch (UniqueConstraintViolationException $exception) {
                throw ValidationException::withMessages(['national_id_number' => 'Identitas sudah tercatat. Muat ulang dan periksa data warga.']);
            }
            if ($previousStatus !== $resident->residency_status) {
                DB::table('resident_status_histories')->insert([
                    'resident_id' => $resident->id,
                    'status' => $resident->residency_status,
                    'effective_date' => $data['effective_date'],
                    'reason' => $data['reason'],
                    'created_by' => auth()->id(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $this->audit->record(
                $wasExisting ? 'resident.updated' : 'resident.created',
                $resident,
                $data['reason']
            );

            return $resident;
        });
    }
}
