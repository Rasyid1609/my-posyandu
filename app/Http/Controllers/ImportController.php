<?php

namespace App\Http\Controllers;

use App\Jobs\ExtractReportImage;
use App\Models\ImportBatch;
use App\Models\ReportingPeriod;
use App\Services\AuditTrail;
use App\Services\GroupedReport;
use App\Services\ImportFiles;
use App\Services\ImportWorkflow;
use App\Services\PeriodWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ImportController extends Controller
{
    private function authorizeBatch(ReportingPeriod $period, ImportBatch $batch): void
    {
        Gate::authorize('imports.manage');
        Gate::authorize('aggregates.view');
        abort_unless($batch->reporting_period_id === $period->id, 404);
        if ($batch->kind === 'residents') {
            Gate::authorize('residents.view');
            Gate::authorize('residents.manage');
        }
    }

    public function index(Request $request, ReportingPeriod $period, GroupedReport $reports, AuditTrail $audit): Response
    {
        Gate::authorize('imports.manage');
        Gate::authorize('aggregates.view');
        $data = $request->validate(['batch_id' => ['nullable', 'integer']]);
        $batch = isset($data['batch_id']) ? ImportBatch::findOrFail($data['batch_id']) : null;
        if ($batch) {
            $this->authorizeBatch($period, $batch);
            $audit->record('import.viewed', $batch);
        }
        $catalog = [];
        foreach ($reports->calculate($period) as $type => $report) {
            $catalog[$type] = array_map(fn ($row) => ['code' => $row['code'], 'label' => $row['label']], $report['rows']);
        }

        return Inertia::render('Reports/Import', [
            'period' => $period,
            'batch' => $batch,

            'batches' => ImportBatch::where('reporting_period_id', $period->id)->when(
                ! $request->user()->can('residents.view'),
                fn ($q) => $q->where('kind', 'legacy')
            )
                ->latest('id')->paginate(15, ['id', 'kind', 'report_type', 'status', 'source_name', 'revision', 'ocr_status']),

            'catalog' => $catalog,
            'reportTypes' => GroupedReport::TYPES,

            'sourceUrl' => $batch?->file_path ? URL::temporarySignedRoute(
                'imports.source',
                now()->addMinutes(15),
                ['period' => $period->id, 'batch' => $batch->id]
            ) : null,

            'sourceIsImage' => $batch && str_starts_with($batch->mime_type ?? '', 'image/'),

            'selections' => DB::table('report_source_selections')
                ->where('reporting_period_id', $period->id)
                ->get(['report_type', 'import_batch_id']),

            'ocrAvailable' => config('analysis.ocr_enabled') && config('analysis.processing_approved') && (bool) config('analysis.api_key') && (bool) config('analysis.ocr_model'),
        ]);
    }

    public function store(Request $request, ReportingPeriod $period, ImportFiles $files, PeriodWorkflow $workflow, AuditTrail $audit): RedirectResponse
    {
        Gate::authorize('imports.manage');
        Gate::authorize('aggregates.view');
        $data = $request->validate([
            'kind' => ['required', Rule::in(['residents', 'legacy'])],

            'report_type' => [
                'nullable',
                'required_if:kind,legacy',
                Rule::in(array_keys(GroupedReport::TYPES)),
            ],

            'source_name' => ['required', 'string', 'max:150'],
            'period_revision' => ['required', 'integer', 'min:1'],

            'file' => [
                'nullable',
                'file',
                'max:8192',
                'mimes:csv,txt,xlsx,jpg,jpeg,png,webp',
                'extensions:csv,xlsx,jpg,jpeg,png,webp',
            ],
        ]);
        if ($data['kind'] === 'residents') {
            Gate::authorize('residents.view');
            Gate::authorize('residents.manage');
        }
        $file = $request->file('file');
        $mime = $file?->getMimeType();
        $image = $mime && str_starts_with($mime, 'image/');
        if ($image && ($data['kind'] !== 'legacy' || ! ($size = getimagesize($file->getRealPath())) || $size[0] * $size[1] > 25000000)) {
            throw ValidationException::withMessages(['file' => 'Foto hanya untuk import agregat historis; maksimal 25 megapiksel.']);
        }
        $rows = $file && ! $image ? $files->rows($file, $data['kind']) : [];
        $checksum = $file ? hash_file('sha256', $file->getRealPath()) : hash('sha256', $data['kind'].'|'.($data['report_type'] ?? '').'|'.$data['source_name']);
        $path = null;
        $batch = null;
        try {
            $workflow->change($period, $data['period_revision'], function ($current) use ($file, $data, $rows, $checksum, $mime, &$path, &$batch, $audit) {
                $batch = ImportBatch::where('reporting_period_id', $current->id)
                    ->where('kind', $data['kind'])
                    ->where('checksum', $checksum)
                    ->first();
                if ($batch) {
                    return;
                }
                if ($file) {
                    $path = $file->store('imports', 'local');
                    if (! $path) {
                        throw new \RuntimeException('Penyimpanan privat gagal.');
                    }
                }
                $batch = ImportBatch::create([
                    'reporting_period_id' => $current->id,
                    'created_by' => auth()->id(),
                    'kind' => $data['kind'],

                    'report_type' => $data['kind'] === 'legacy' ? $data['report_type'] : null,
                    'checksum' => $checksum,

                    'source_name' => $data['source_name'],
                    'file_path' => $path,
                    'mime_type' => $mime,
                    'draft_rows' => $rows,
                    'revision' => 1,
                ]);
                $audit->record('import.drafted', $batch);
            });
        } catch (\Throwable $exception) {
            if ($path) {
                Storage::disk('local')->delete($path);
            } throw $exception;
        }

        return redirect()->route('imports.index', ['period' => $period->id, 'batch_id' => $batch->id])->with(
            'success',
            'Draft import tersedia. Periksa sumber, periode dan setiap baris sebelum commit.'
        );
    }

    public function update(Request $request, ReportingPeriod $period, ImportBatch $batch, ImportWorkflow $imports): RedirectResponse
    {
        $this->authorizeBatch($period, $batch);
        $data = $request->validate([
            'revision' => ['required', 'integer', 'min:1'],
            'period_revision' => ['required', 'integer', 'min:1'],

            'rows' => ['present', 'array', 'max:500'],
            'rows.*' => ['array'],
        ]);
        $imports->saveDraft($period, $batch, $data);

        return back()->with('success', 'Perubahan draft tersimpan; belum menjadi data final.');
    }

    public function commit(Request $request, ReportingPeriod $period, ImportBatch $batch, ImportWorkflow $imports): RedirectResponse
    {
        $this->authorizeBatch($period, $batch);
        $data = $request->validate([
            'revision' => ['required', 'integer', 'min:1'],
            'period_revision' => ['required', 'integer', 'min:1'],
            'confirmed' => ['accepted'],
        ]);
        $imports->commit($period, $batch, $data);

        return back()->with(
            'success',
            'Import terverifikasi di-commit. Untuk historis, pilih sumber laporan melalui rekonsiliasi.'
        );
    }

    public function selectSource(Request $request, ReportingPeriod $period, ImportWorkflow $imports): RedirectResponse
    {
        Gate::authorize('imports.manage');
        Gate::authorize('reports.approve');
        Gate::authorize('aggregates.view');
        $data = $request->validate([
            'period_revision' => ['required', 'integer', 'min:1'],
            'report_type' => ['required', Rule::in(array_keys(GroupedReport::TYPES))],

            'import_batch_id' => ['present', 'nullable', 'integer'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);
        $imports->selectSource($period, $data);

        return back()->with(
            'success',
            'Sumber resmi periode/jenis dipilih. Transaksi dan historis tidak dijumlahkan.'
        );
    }

    public function source(Request $request, ReportingPeriod $period, ImportBatch $batch, AuditTrail $audit): \Symfony\Component\HttpFoundation\Response
    {
        $this->authorizeBatch($period, $batch);
        abort_unless($batch->file_path && Storage::disk('local')->exists($batch->file_path), 404);
        $audit->record('import.source_viewed', $batch);
        $image = str_starts_with($batch->mime_type ?? '', 'image/');

        return Storage::disk('local')->response($batch->file_path, 'sumber-import-'.$batch->id, [
            'Content-Type' => $batch->mime_type ?? 'application/octet-stream',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ], $image ? 'inline' : 'attachment');
    }

    public function ocr(Request $request, ReportingPeriod $period, ImportBatch $batch, PeriodWorkflow $workflow, AuditTrail $audit): RedirectResponse
    {
        $this->authorizeBatch($period, $batch);
        $data = $request->validate([
            'revision' => ['required', 'integer', 'min:1'],
            'period_revision' => ['required', 'integer', 'min:1'],
            'consent' => ['accepted'],
        ]);
        if (! config('analysis.ocr_enabled') || ! config('analysis.processing_approved') || ! config('analysis.api_key') || ! config('analysis.ocr_model')) {
            throw ValidationException::withMessages(['ocr' => 'OCR provider belum dikonfigurasi; isi dan review draft secara manual.']);
        }
        $workflow->change($period, $data['period_revision'], function () use ($batch, $data, $audit) {
            $batch->refresh();
            abort_unless($batch->revision === $data['revision'], 409);
            abort_unless(
                $batch->kind === 'legacy' && $batch->status === 'draft' && str_starts_with($batch->mime_type ?? '', 'image/'),
                422
            );
            if ($batch->ocr_status !== 'not_requested') {
                throw ValidationException::withMessages(['ocr' => 'OCR untuk berkas ini sudah diminta. Tinjau draft atau buat sumber baru.']);
            }
            $batch->update(['ocr_status' => 'queued', 'revision' => $batch->revision + 1]);
            ExtractReportImage::dispatch($batch->id, $batch->revision, auth()->id())->afterCommit();
            $audit->record('import.ocr_requested', $batch);
        });

        return back()->with('success', 'Foto masuk antrean OCR. Hasil tetap draft dan memerlukan koreksi manusia.');
    }

    public function template(Request $request, ReportingPeriod $period, GroupedReport $reports): \Symfony\Component\HttpFoundation\Response
    {
        Gate::authorize('imports.manage');
        Gate::authorize('aggregates.view');
        $data = $request->validate([
            'kind' => ['required', Rule::in(['residents', 'legacy'])],
            'report_type' => [
                'nullable',
                'required_if:kind,legacy',
                Rule::in(array_keys(GroupedReport::TYPES)),
            ],
        ]);
        if ($data['kind'] === 'residents') {
            Gate::authorize('residents.manage');
            Gate::authorize('residents.view');
        }
        $rows = $data['kind'] === 'residents' ? [['full_name', 'gender', 'birth_date', 'address', 'rt', 'rw']]
            : [
                ['metric_code', 'value', 'source_cell'],
                ...array_map(
                    fn ($row) => [$row['code'], '', ''],
                    $reports->calculate($period)[$data['report_type']]['rows']
                ),
            ];

        return response()->streamDownload(function () use ($rows) {
            $stream = fopen('php://output', 'wb');
            foreach ($rows as $row) {
                fputcsv($stream, $row, ',', '"', '');
            }
            fclose($stream);
        }, 'template-'.$data['kind'].'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
