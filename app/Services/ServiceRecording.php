<?php

namespace App\Services;

use App\Models\ReportingPeriod;
use App\Models\Resident;
use App\Models\TargetMembership;
use App\Models\Visit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServiceRecording
{
    public function __construct(private PeriodWorkflow $workflow, private AuditTrail $audit, private AgeGrouping $grouping, private ClinicalCalculations $calculations) {}

    public function addTarget(ReportingPeriod $period, array $data): void
    {
        $this->workflow->change($period, $data['revision'], function (ReportingPeriod $current) use ($data) {
            $resident = Resident::find($data['resident_id']);
            if (! $resident || ! $this->grouping->activeOn($resident, $current->reference_date->toDateString())) {
                throw ValidationException::withMessages(['resident_id' => 'Warga harus aktif dan telah lahir pada tanggal acuan.']);
            }
            $this->select($current, $resident, $data['reason']);
        });
    }

    public function generate(ReportingPeriod $period, int $revision): void
    {
        $this->workflow->change($period, $revision, function (ReportingPeriod $current) {
            $current->load(['targets', 'ageRuleVersion']);
            Resident::query()->with(['maternalEpisodes', 'statusHistories'])->orderBy('id')->chunkById(200, function ($residents) use ($current) {
                foreach ($residents as $resident) {
                    if ($this->grouping->activeOn($resident, $current->reference_date->toDateString())) {
                        $this->select(
                            $current,
                            $resident,
                            'Pembentukan sasaran dari status efektif dan aturan periode.',
                            false
                        );
                    }
                }
            });
            $this->audit->record('targets.generated', $current);
        });
    }

    private function select(ReportingPeriod $period, Resident $resident, string $reason, bool $restore = true): void
    {
        $target = $period->relationLoaded('targets') ? $period->targets->firstWhere('resident_id', $resident->id) : $period->targets()->where('resident_id', $resident->id)->first();
        if (! $target) {
            $target = $period->targets()->create([
                'resident_id' => $resident->id,
                ...$this->grouping->snapshot($resident, $period),

                'attendance_status' => 'unconfirmed',
                'selection_reason' => $reason,
                'created_by' => auth()->id(),
            ]);
            $this->audit->record('target.selected', $target, $reason);
            if ($period->relationLoaded('targets')) {
                $period->targets->push($target);
            }
        } elseif ($restore && $target->is_excluded) {
            $target->is_excluded = false;
            $target->selection_reason = $reason;
            $target->save();
            $this->audit->record('target.restored', $target, $reason);
        }
    }

    public function attendance(ReportingPeriod $period, TargetMembership $target, array $data): void
    {
        $this->workflow->change($period, $data['revision'], function () use ($target, $data) {
            if ($target->is_excluded) {
                throw ValidationException::withMessages(['attendance_status' => 'Sasaran telah dikeluarkan.']);
            }
            if ($data['attendance_status'] !== 'present' && $target->visits()->exists()) {
                throw ValidationException::withMessages(['attendance_status' => 'Warga dengan catatan kunjungan harus berstatus hadir.']);
            }
            $target->attendance_status = $data['attendance_status'];
            $target->save();
            $this->audit->record('target.attendance_changed', $target, $data['reason']);
        });
    }

    public function refreshSnapshot(ReportingPeriod $period, TargetMembership $target, array $data): void
    {
        $this->workflow->change($period, $data['revision'], function (ReportingPeriod $current) use ($target, $data) {
            if ($target->is_excluded) {
                throw ValidationException::withMessages(['period' => 'Pulihkan sasaran terlebih dahulu.']);
            }
            $resident = $target->resident;
            if ($resident->birth_date && $resident->birth_date->gt($current->reference_date)) {
                throw ValidationException::withMessages(['period' => 'Warga belum lahir pada tanggal acuan.']);
            }
            $target->fill($this->grouping->snapshot($resident, $current))->save();
            $this->audit->record('target.snapshot_refreshed', $target, $data['reason']);
        });
    }

    public function recordVisit(ReportingPeriod $period, TargetMembership $target, array $data, ?Visit $visit = null): void
    {
        $this->workflow->change($period, $data['revision'], function (ReportingPeriod $current) use ($target, $data, $visit) {
            if ($visit && $visit->fresh()->record_status !== 'draft') {
                throw ValidationException::withMessages(['period' => 'Hanya draft pengukuran yang dapat diedit.']);
            }
            if ($target->is_excluded || ! $target->is_within_target) {
                throw ValidationException::withMessages(['period' => 'Kunjungan hanya dapat dicatat pada sasaran aktif.']);
            }
            $visitedAt = CarbonImmutable::createFromFormat('!Y-m-d\\TH:i', $data['visited_at'], 'Asia/Jakarta');
            if ($visitedAt->month !== $current->month || $visitedAt->year !== $current->year) {
                throw ValidationException::withMessages(['visited_at' => 'Tanggal kunjungan harus berada dalam periode yang dipilih.']);
            }
            $attributes = [
                'target_membership_id' => $target->id,
                'resident_id' => $target->resident_id,
                'visited_at' => $visitedAt->utc(),
                'record_status' => 'draft',
                'created_by' => auth()->id(),
            ];
            $attributes['activity_session_id'] = $data['activity_session_id'] ?? $visit?->activity_session_id;
            $existing = $visit !== null;
            if ($visit) {
                $visit->update(Arr::except($attributes, ['created_by']));
            } else {
                $visit = $current->visits()->create($attributes);
            }
            $exam = $visit->examination()->firstOrNew();
            $keys = [
                'weight_kg',
                'height_cm',
                'weight_state',
                'height_state',
                'blood_pressure_state',
                'glucose_state',
                'systolic_mmhg',
                'diastolic_mmhg',
                'glucose_mg_dl',
                'head_cm',
                'arm_cm',
                'waist_cm',
                'head_state',
                'arm_state',
                'waist_state',
                'assessment_source',
                'assessor_name',
                ...array_keys(ClinicalFields::definitions()),
            ];
            $exam->forceFill(['category_code' => $target->category_code, ...Arr::only($data, $keys)]);
            if (($data['assessment_mode'] ?? 'manual') === 'automatic') {
                $details = $this->calculations->calculate($target, $data, $visit->id);
                $allowed = ClinicalFields::forTarget(
                    $target->category_code,
                    $target->is_pregnant_snapshot || $target->is_postpartum_snapshot || $target->is_breastfeeding_snapshot,
                    true
                );
                $exam->forceFill(Arr::only($details['field_values'], array_keys($allowed)));
                $exam->calculation_details = $details;
            } else {
                $previousDetails = $exam->getOriginal('calculation_details');
                if (($previousDetails['mode'] ?? null) === 'automatic' && empty($data['calculation_override_reason'])) {
                    throw ValidationException::withMessages(['calculation_override_reason' => 'Cantumkan alasan mengganti hasil otomatis dengan hasil petugas.']);
                }
                $exam->calculation_details = [
                    'mode' => 'manual',
                    'inputs' => array_replace(
                        Arr::only($previousDetails['inputs'] ?? [], ClinicalCalculations::CONTEXT),
                        Arr::only($data, ClinicalCalculations::CONTEXT)
                    ),
                    'override_reason' => $data['calculation_override_reason'] ?? ($previousDetails['override_reason'] ?? null),

                    'previous_calculation' => ($previousDetails['mode'] ?? null) === 'automatic' ? $previousDetails : ($previousDetails['previous_calculation'] ?? null),
                ];
            }
            $visit->examination()->save($exam);
            if (($data['referral_result'] ?? null) === 'yes') {
                $visit->referral()->updateOrCreate(['visit_id' => $visit->id], [
                    'destination' => $data['referral_destination'],
                    'reason' => $data['referral_reason'],

                    'follow_up_date' => $data['follow_up_date'] ?? null,
                    'follow_up_status' => $data['follow_up_status'] ?? 'pending',

                    'follow_up_note' => $data['follow_up_note'] ?? null,
                ]);
            } elseif (isset($data['referral_result'])) {
                $visit->referral()->delete();
            }
            $target->attendance_status = 'present';
            $target->save();
            $this->audit->record($existing ? 'visit.draft_updated' : 'visit.drafted', $visit);
        });
    }

    public function correct(ReportingPeriod $period, Visit $visit, int $revision, string $reason): void
    {
        $this->workflow->change($period, $revision, function () use ($visit, $reason) {
            $visit->refresh();
            if ($visit->record_status !== 'finalized') {
                throw ValidationException::withMessages(['period' => 'Hanya kunjungan final yang memerlukan koreksi.']);
            }
            DB::table('visit_corrections')->insert([
                'visit_id' => $visit->id,
                'previous_record' => Crypt::encryptString(json_encode([
                    'visit' => $visit->getAttributes(),
                    'examination' => $visit->examination->makeVisible($visit->examination->getHidden())->attributesToArray(),
                    'referral' => $visit->referral?->attributesToArray(),
                ], JSON_THROW_ON_ERROR)),
                'reason' => $reason,
                'created_by' => auth()->id(),
                'created_at' => now(),
            ]);
            $visit->forceFill(['record_status' => 'draft', 'finalized_by' => null, 'finalized_at' => null])->save();
            $this->audit->record('visit.correction_opened', $visit, $reason);
        });
    }

    public function finalize(ReportingPeriod $period, Visit $visit, int $revision, bool $calculationReviewed = false): void
    {
        $this->workflow->change($period, $revision, function () use ($visit, $calculationReviewed) {
            $visit->refresh();
            if ($visit->record_status !== 'draft') {
                throw ValidationException::withMessages(['period' => 'Kunjungan sudah difinalkan.']);
            }
            $details = $visit->examination?->calculation_details;
            if (($details['mode'] ?? null) === 'automatic' && collect($details['results'])->contains(fn (array $result): bool => $result['status'] === 'calculated')) {
                if (! $calculationReviewed) {
                    throw ValidationException::withMessages(['calculation_reviewed' => 'Periksa angka dan hasil hitung, lalu konfirmasikan sebelum finalisasi.']);
                }
                $visit->examination->calculation_details = [
                    ...$details,
                    'reviewed_by' => auth()->id(),
                    'reviewed_at' => now()->toIso8601String(),
                ];
                $visit->examination->save();
            }
            $visit->forceFill([
                'record_status' => 'finalized',
                'finalized_by' => auth()->id(),
                'finalized_at' => now(),
            ])->save();
            $this->audit->record('visit.finalized', $visit);
        });
    }
}
