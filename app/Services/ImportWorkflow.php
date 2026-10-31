<?php

namespace App\Services;

use App\Models\ImportBatch;
use App\Models\ReportingPeriod;
use App\Models\Resident;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ImportWorkflow
{
    public function __construct(private PeriodWorkflow $workflow, private ResidentRegistry $residents, private GroupedReport $reports, private AuditTrail $audit) {}

    public function validateRows(array $rows, string $kind, ReportingPeriod $period, ?string $type): array
    {
        Validator::make(
            ['rows' => $rows],
            ['rows' => ['required', 'array', 'min:1', 'max:500'], 'rows.*' => ['array']]
        )->validate();
        $valid = [];
        $codes = $kind === 'legacy' ? array_column($this->reports->calculate($period)[$type]['rows'], 'code') : [];
        $seen = [];
        foreach ($rows as $index => $row) {
            if ($kind === 'legacy') {
                $data = Validator::make($row, [
                    'metric_code' => ['required', Rule::in($codes)],
                    'value' => ['present', 'nullable', 'integer', 'between:0,10000000'],
                    'source_cell' => ['nullable', 'string', 'max:150'],
                ])->validate();
                if (isset($seen[$data['metric_code']])) {
                    throw ValidationException::withMessages(['rows' => 'Kode indikator duplikat di baris '.($index + 1)]);
                }
                $seen[$data['metric_code']] = true;
            } else {
                $data = Validator::make($row, [
                    'full_name' => ['required', 'string', 'max:150'],
                    'gender' => ['required', Rule::in(['L', 'P'])],

                    'birth_date' => [
                        'nullable',
                        'date_format:Y-m-d',
                        'before_or_equal:'.now('Asia/Jakarta')->toDateString(),
                    ],

                    'address' => ['nullable', 'string', 'max:1000'],
                    'rt' => ['nullable', 'string', 'max:10'],
                    'rw' => ['nullable', 'string', 'max:10'],
                ])->validate();
                $key = mb_strtolower(trim($data['full_name'])).'|'.$data['gender'].'|'.($data['birth_date'] ?? '');
                if (isset($seen[$key]) || Resident::withTrashed()
                    ->whereRaw('LOWER(full_name) = ?', [mb_strtolower(trim($data['full_name']))])
                    ->where('gender', $data['gender'])
                    ->where('birth_date', $data['birth_date'] ?? null)
                    ->exists()) {
                    throw ValidationException::withMessages(['rows' => 'Kemungkinan warga duplikat pada baris '.($index + 1).'. Periksa dan koreksi sebelum commit.']);
                }
                $seen[$key] = true;
            }
            $valid[] = $data;
        }

        return $valid;
    }

    public function saveDraft(ReportingPeriod $period, ImportBatch $batch, array $data): void
    {
        $this->workflow->change($period, $data['period_revision'], function () use ($batch, $data) {
            $batch = ImportBatch::lockForUpdate()->findOrFail($batch->id);
            abort_unless($batch->revision === $data['revision'], 409, 'Draft import telah berubah.');
            if ($batch->status !== 'draft' || $batch->ocr_status === 'processing' || $batch->ocr_status === 'queued') {
                throw ValidationException::withMessages(['import' => 'Draft tidak dapat diubah saat ini.']);
            }
            $batch->update(['draft_rows' => $data['rows'], 'revision' => $batch->revision + 1]);
            $this->audit->record('import.draft_updated', $batch);
        });
    }

    public function commit(ReportingPeriod $period, ImportBatch $batch, array $data): void
    {
        $this->workflow->change($period, $data['period_revision'], function (ReportingPeriod $current) use ($batch, $data) {
            $batch = ImportBatch::lockForUpdate()->findOrFail($batch->id);
            abort_unless($batch->revision === $data['revision'], 409, 'Draft import telah berubah.');
            if ($batch->status !== 'draft' || in_array($batch->ocr_status, ['queued', 'processing'])) {
                throw ValidationException::withMessages(['import' => 'Hanya draft hasil review yang dapat di-commit.']);
            }
            $rows = $this->validateRows($batch->draft_rows, $batch->kind, $current, $batch->report_type);
            foreach ($rows as $index => $row) {
                if ($batch->kind === 'residents') {
                    $resident = $this->residents->save([
                        ...$row,
                        'is_birth_date_estimated' => false,
                        'residency_status' => 'active',

                        'effective_date' => now('Asia/Jakarta')->toDateString(),
                        'reason' => 'Import terverifikasi batch #'.$batch->id,
                    ]);
                    DB::table('import_resident_links')->insert([
                        'import_batch_id' => $batch->id,
                        'resident_id' => $resident->id,
                        'row_number' => $index + 1,
                    ]);
                } else {
                    DB::table('legacy_report_values')->insert([
                        'reporting_period_id' => $current->id,
                        'import_batch_id' => $batch->id,

                        'report_type' => $batch->report_type,
                        ...$row,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
            $batch->update([
                'status' => 'committed',
                'revision' => $batch->revision + 1,
                'committed_by' => auth()->id(),
                'committed_at' => now(),
            ]);
            $this->audit->record('import.committed', $batch);
        });
    }

    public function selectSource(ReportingPeriod $period, array $data): void
    {
        $this->workflow->change($period, $data['period_revision'], function (ReportingPeriod $current) use ($data) {
            if ($data['import_batch_id']) {
                $batch = ImportBatch::where('reporting_period_id', $current->id)
                    ->where('report_type', $data['report_type'])
                    ->where('kind', 'legacy')
                    ->where('status', 'committed')
                    ->find($data['import_batch_id']);
                if (! $batch) {
                    throw ValidationException::withMessages(['import_batch_id' => 'Sumber harus merupakan import historis terverifikasi pada periode dan jenis yang sama.']);
                }
            }
            DB::table('report_source_selections')->updateOrInsert(['reporting_period_id' => $current->id, 'report_type' => $data['report_type']],
                [
                    'import_batch_id' => $data['import_batch_id'],
                    'reason' => $data['reason'],
                    'selected_by' => auth()->id(),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]);
            $this->audit->record('report.source_selected', $current, $data['reason']);
        });
    }
}
