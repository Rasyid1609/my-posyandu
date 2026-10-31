<?php

namespace App\Http\Controllers;

use App\Models\ReportingPeriod;
use App\Services\AuditTrail;
use App\Services\GroupedReport;
use App\Services\OperationalReport;
use App\Services\PeriodWorkflow;
use App\Services\ReportData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function index(Request $request, ReportData $reportData): Response
    {
        Gate::authorize('aggregates.view');
        $data = $request->validate([
            'period_id' => ['nullable', 'integer', 'exists:reporting_periods,id'],
            'version' => ['nullable', 'integer', 'min:1'],
            'compare_id' => ['nullable', 'integer', 'exists:reporting_periods,id'],
        ]);
        $periods = ReportingPeriod::query()
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->get(['id', 'month', 'year', 'status']);
        $period = isset($data['period_id']) ? ReportingPeriod::findOrFail($data['period_id']) : ($periods->first() ? ReportingPeriod::find($periods->first()->id) : null);
        $snapshots = $period?->snapshots()->orderByDesc('version')->get([
            'id',
            'reporting_period_id',
            'version',
            'source_revision',
            'mapping_version',
            'source_hash',
            'created_at',
        ]);
        $dataset = $period ? $reportData->read($period, $data['version'] ?? null, $request->user()->can('sensitive.view')) : null;
        $comparison = isset($data['compare_id']) ? $reportData->read(
            ReportingPeriod::findOrFail($data['compare_id']),
            null,
            $request->user()->can('sensitive.view')
        ) : null;

        return Inertia::render('Reports/Index', [
            'periods' => $periods,
            'period' => $period,
            'metrics' => $dataset['metrics'] ?? null,
            'dataset' => $dataset,
            'comparison' => $comparison,
            'reportTypes' => GroupedReport::TYPES,
            'snapshots' => $snapshots ?? [],
            'mappingVersion' => OperationalReport::VERSION,
        ]);
    }

    public function update(Request $request, ReportingPeriod $period, PeriodWorkflow $workflow, OperationalReport $report, AuditTrail $audit): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['submit', 'approve', 'return', 'reopen'])],
            'revision' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);
        Gate::authorize('aggregates.view');
        Gate::authorize($data['action'] === 'submit' ? 'reports.submit' : 'reports.approve');
        $statuses = match ($data['action']) {
            'submit' => ['open', 'returned'],
            'approve', 'return' => ['submitted'],
            'reopen' => ['locked'],
        };
        $workflow->change($period, $data['revision'], function (ReportingPeriod $current) use ($data, $report, $audit) {
            $metrics = $report->calculate($current);
            if (in_array($data['action'], ['submit', 'approve'], true) && ($metrics['unconfirmed'] > 0 || $metrics['draft_visits'] > 0 || $metrics['draft_home_visits'] > 0)) {
                throw ValidationException::withMessages(['period' => 'Konfirmasikan semua kehadiran dan finalkan seluruh draft kunjungan sebelum review.']);
            }
            $current->status = match ($data['action']) {
                'submit' => 'submitted',
                'approve' => 'locked',
                'return' => 'returned',
                'reopen' => 'open',
            };
            if ($data['action'] === 'approve') {
                $sourceHash = hash(
                    'sha256',
                    json_encode(
                        [$current->id, $current->revision, OperationalReport::VERSION, $metrics],
                        JSON_THROW_ON_ERROR
                    )
                );
                $current->snapshots()->create([
                    'version' => ($current->snapshots()->max('version') ?? 0) + 1,
                    'source_revision' => $current->revision,
                    'mapping_version' => OperationalReport::VERSION,
                    'metrics' => $metrics,
                    'source_hash' => $sourceHash,
                    'approved_by' => auth()->id(),
                    'created_at' => now(),
                ]);
            }
            $audit->record('report.'.$data['action'], $current, $data['reason']);
        }, $statuses);

        return back()->with('success', 'Status rekap operasional diperbarui.');
    }
}
