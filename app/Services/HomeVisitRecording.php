<?php

namespace App\Services;

use App\Models\HomeVisit;
use App\Models\Household;
use App\Models\ReportingPeriod;
use App\Models\Resident;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HomeVisitRecording
{
    public function __construct(private PeriodWorkflow $workflow, private AgeGrouping $grouping, private AuditTrail $audit) {}

    public function save(ReportingPeriod $period, array $data, ?HomeVisit $visit = null): void
    {
        $this->workflow->change($period, $data['revision'], function (ReportingPeriod $current) use ($data, $visit) {
            $family = Household::query()->where('is_active', true)->find($data['household_id']);
            if (! $family) {
                throw ValidationException::withMessages(['household_id' => 'Keluarga harus aktif.']);
            }
            $date = CarbonImmutable::parse($data['visited_on']);
            if ($date->month !== $current->month || $date->year !== $current->year) {
                throw ValidationException::withMessages(['visited_on' => 'Tanggal harus berada di bulan kegiatan.']);
            }
            if ($visit && $visit->fresh()->record_status !== 'draft') {
                throw ValidationException::withMessages(['period' => 'Hanya draft kunjungan rumah dapat diedit.']);
            }
            $residents = Resident::query()
                ->with('maternalEpisodes')
                ->where('household_id', $family->id)
                ->whereIn('id', array_column($data['residents'], 'resident_id'))
                ->get()
                ->keyBy('id');
            if ($residents->count() !== count($data['residents'])) {
                throw ValidationException::withMessages(['residents' => 'Seluruh warga harus merupakan anggota keluarga yang dipilih.']);
            }
            $current->householdTargets()->firstOrCreate(['household_id' => $family->id]);
            $visit ??= $current->homeVisits()->make(['created_by' => auth()->id()]);
            $visit->fill(Arr::only($data, ['household_id', 'visited_on', 'reason', 'follow_up']));
            $visit->save();
            $rows = [];
            foreach ($data['residents'] as $row) {
                $resident = $residents[$row['resident_id']];
                if ($resident->birth_date && $resident->birth_date->gt($date)) {
                    throw ValidationException::withMessages(['residents' => 'Warga belum lahir pada tanggal kunjungan.']);
                }
                $snapshot = $this->grouping->snapshot($resident, $current);
                $rows[$resident->id] = [
                    'category_snapshot' => $snapshot['category_code'],
                    ...Arr::only(
                        $snapshot,
                        ['is_pregnant_snapshot', 'is_postpartum_snapshot', 'is_breastfeeding_snapshot']
                    ),
                    ...Arr::only($row, ['no_service', 'danger_sign', 'tb_symptom', 'irregular_medication']),
                ];
            }
            $visit->residents()->sync($rows);
            $visit->staff()->sync($data['staff_ids']);
            $this->audit->record('home_visit.drafted', $visit);
        });
    }

    public function finalize(ReportingPeriod $period, HomeVisit $visit, int $revision): void
    {
        $this->workflow->change($period, $revision, function () use ($visit) {
            if ($visit->fresh()->record_status !== 'draft') {
                throw ValidationException::withMessages(['period' => 'Kunjungan sudah final.']);
            }
            $visit->update([
                'record_status' => 'finalized',
                'finalized_by' => auth()->id(),
                'finalized_at' => now(),
            ]);
            $this->audit->record('home_visit.finalized', $visit);
        });
    }

    public function correct(ReportingPeriod $period, HomeVisit $visit, int $revision, string $reason): void
    {
        $this->workflow->change($period, $revision, function () use ($visit, $reason) {
            $visit->refresh();
            if ($visit->record_status !== 'finalized') {
                throw ValidationException::withMessages(['period' => 'Hanya kunjungan rumah final yang memerlukan koreksi.']);
            }
            DB::table('home_visit_corrections')->insert([
                'home_visit_id' => $visit->id,

                'previous_record' => Crypt::encryptString(json_encode([
                    'visit' => $visit->getAttributes(),
                    'residents' => $visit->residents->toArray(),
                    'staff_ids' => $visit->staff->modelKeys(),
                ], JSON_THROW_ON_ERROR)),

                'reason' => $reason,
                'created_by' => auth()->id(),
                'created_at' => now(),
            ]);
            $visit->update(['record_status' => 'draft', 'finalized_by' => null, 'finalized_at' => null]);
            $this->audit->record('home_visit.correction_opened', $visit, $reason);
        });
    }
}
