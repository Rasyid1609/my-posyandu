<?php

namespace App\Http\Controllers;

use App\Exports\AggregateExport;
use App\Models\ReportingPeriod;
use App\Services\AuditTrail;
use App\Services\GroupedReport;
use App\Services\ReportData;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

class ExportController extends Controller
{
    public function show(Request $request, ReportingPeriod $period, ReportData $reports, AuditTrail $audit): Response
    {
        Gate::authorize('aggregates.view');
        Gate::authorize('exports.aggregate');
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(GroupedReport::TYPES))],

            'format' => ['required', Rule::in(['xlsx', 'pdf'])],
            'version' => ['nullable', 'integer', 'min:1'],
        ]);
        $dataset = $reports->read($period, $data['version'] ?? null, $request->user()->can('sensitive.view'));
        $report = $dataset['metrics']['_reports'][$data['type']] ?? null;
        abort_unless($report, 422, 'Versi arsip ini belum mempunyai mapping lima rekap.');
        $metadata = [
            'Jenis laporan' => $report['title'],
            'Periode' => sprintf('%02d/%d', $period->month, $period->year),

            'Status' => $dataset['status'],
            'Versi' => $dataset['version'] ?? 'Draft',
            'Revisi sumber' => $dataset['source_revision'],

            'Pemetaan' => $report['mapping_version'],
            'Aturan usia' => $dataset['metrics']['_age_rule_version_id'] ?? 'Belum ditetapkan',

            'Sumber' => ($report['source'] === 'legacy_import' ? 'Import historis #'.$report['import_batch_id'] : 'Transaksi aplikasi; hasil petugas').'. Konfigurasi sementara, bukan formulir resmi disahkan.',

            'Diekspor (WIB)' => now('Asia/Jakarta')->format('Y-m-d H:i'),
            'Pengekspor' => $request->user()->name,
        ];
        $audit->record(
            'report.exported',
            $period,
            $data['type'].' / '.$data['format'].' / versi '.($dataset['version'] ?? 'draft')
        );
        $filename = 'jeruk-'.$period->year.'-'.$period->month.'-'.$data['type'];
        if ($data['format'] === 'xlsx') {
            return Excel::download(
                new AggregateExport($metadata, $report['rows']),
                $filename.'.xlsx',
                null,
                ['Cache-Control' => 'private, no-store']
            );
        }

        return Pdf::loadView('reports.aggregate', ['metadata' => $metadata, 'rows' => $report['rows']])
            ->setPaper('a4', 'landscape')->setOptions(['isRemoteEnabled' => false, 'defaultFont' => 'DejaVu Sans'])
            ->download($filename.'.pdf')->header('Cache-Control', 'private, no-store');
    }
}
