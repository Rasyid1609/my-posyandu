<?php

namespace App\Http\Controllers;

use App\Models\AnalysisRun;
use App\Models\ReportingPeriod;
use App\Services\AggregateAnalysis;
use App\Services\AnalysisEvidence;
use App\Services\AuditTrail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AnalysisController extends Controller
{
    public function index(ReportingPeriod $period, AggregateAnalysis $analysis): Response
    {
        Gate::authorize('aggregates.view');
        Gate::authorize('analysis.manage');
        $runs = AnalysisRun::where('reporting_period_id', $period->id)
            ->with('comparisonPeriod:id,revision')
            ->latest('id')
            ->paginate(10);
        foreach ($runs as $run) {
            $run->is_stale = $run->is_stale || $run->source_revision !== $period->revision || ! $analysis->comparisonCurrent($run);
        }

        return Inertia::render('Reports/Analysis', [
            'period' => $period,
            'runs' => $runs,

            'periods' => ReportingPeriod::orderByDesc('year')->orderByDesc('month')->get(['id', 'month', 'year']),

            'scopes' => AnalysisEvidence::SCOPES,

            'aiAvailable' => config('analysis.enabled') && config('analysis.processing_approved') && (bool) config('analysis.api_key') && (bool) config('analysis.model'),
        ]);
    }

    public function store(Request $request, ReportingPeriod $period, AggregateAnalysis $analysis): RedirectResponse
    {
        Gate::authorize('aggregates.view');
        Gate::authorize('analysis.manage');
        $data = $request->validate([
            'use_ai' => ['required', 'boolean'],
            'report_scope' => ['sometimes', Rule::in(array_keys(AnalysisEvidence::SCOPES))],

            'compare_id' => [
                'nullable',
                'integer',
                'exists:reporting_periods,id',
                Rule::notIn([$period->id]),
            ],
        ]);
        $analysis->request(
            $period,
            $request->user(),
            $data['use_ai'],
            $data['report_scope'] ?? 'operational',
            isset($data['compare_id']) ? ReportingPeriod::findOrFail($data['compare_id']) : null
        );

        return back()->with(
            'success',
            $data['use_ai'] ? 'Analisis masuk antrean. Muat ulang untuk melihat hasil.' : 'Ringkasan lokal disimpan, bukan hasil AI.'
        );
    }

    public function review(Request $request, ReportingPeriod $period, AnalysisRun $run, AuditTrail $audit, AggregateAnalysis $analysis): RedirectResponse
    {
        Gate::authorize('analysis.manage');
        Gate::authorize('reports.approve');
        Gate::authorize('aggregates.view');
        abort_unless($run->reporting_period_id === $period->id, 404);
        $data = $request->validate([
            'review_status' => ['required', Rule::in(['approved', 'rejected'])],
            'review_note' => ['required', 'string', 'min:5', 'max:1000'],

            'edited_summary' => ['nullable', 'string', 'max:2000'],
            'edited_conclusion' => ['nullable', 'string', 'max:2000'],
        ]);
        DB::transaction(function () use ($run, $period, $data, $audit, $analysis) {
            $locks = ReportingPeriod::whereIn('id', array_filter([$period->id, $run->comparison_period_id]))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $period = $locks[$period->id];
            $run = AnalysisRun::lockForUpdate()->findOrFail($run->id);
            if ($run->status !== 'succeeded' || $run->source_revision !== $period->revision || $run->is_stale || ! $analysis->comparisonCurrent($run)) {
                throw ValidationException::withMessages(['analysis' => 'Hanya hasil berhasil dan terkini yang dapat ditinjau.']);
            }
            $edited = $run->result;
            foreach (['summary', 'conclusion'] as $key) {
                if (! empty($data['edited_'.$key])) {
                    $edited[$key] = $data['edited_'.$key];
                }
            }
            $run->update([
                'review_status' => $data['review_status'],
                'review_note' => $data['review_note'],
                'reviewed_by' => auth()->id(),
                'reviewed_result' => $edited,
            ]);
            $audit->record('analysis.reviewed', $run, $data['review_status']);
        }, 3);

        return back()->with('success', 'Review disimpan; hasil asli tetap tersedia.');
    }
}
