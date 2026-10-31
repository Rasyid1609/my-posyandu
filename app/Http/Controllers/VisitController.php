<?php

namespace App\Http\Controllers;

use App\Http\Requests\VisitRequest;
use App\Models\ReportingPeriod;
use App\Models\TargetMembership;
use App\Models\Visit;
use App\Services\AuditTrail;
use App\Services\ClinicalCalculations;
use App\Services\ClinicalFields;
use App\Services\ServiceRecording;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class VisitController extends Controller
{
    public function index(ReportingPeriod $period, TargetMembership $target, AuditTrail $audit, Request $request): Response
    {
        Gate::authorize('residents.view');
        Gate::authorize('visits.view');
        abort_unless($target->reporting_period_id === $period->id, 404);
        $audit->record('visit.history_viewed', $target);

        $visits = $target->visits()
            ->with(['examination', 'referral'])
            ->orderByDesc('visited_at')
            ->orderByDesc('id')
            ->paginate(10);
        if ($request->user()->can('sensitive.view')) {
            foreach ($visits as $visit) {
                $visit->examination?->makeVisible([
                    'mental_health_result',
                    'heeadsss_status',
                    'skilas_cognition',
                    'skilas_depression',
                ]);
            }
        }

        $fields = ClinicalFields::forTarget(
            $target->category_code,
            $target->is_pregnant_snapshot || $target->is_postpartum_snapshot || $target->is_breastfeeding_snapshot,
            $request->user()->can('sensitive.view') && $request->user()->can('sensitive.record')
        );
        if ($target->gender_snapshot === 'L') {
            unset($fields['anemia_status']);
        }

        return Inertia::render('Visits/Index', [
            'period' => $period,
            'activities' => $period->activities()
                ->orderBy('scheduled_on')
                ->orderBy('id')
                ->get(['id', 'name', 'scheduled_on', 'location']),
            'target' => $target->load('resident:id,full_name,registration_number'),
            'visits' => $visits,
            'clinicalFields' => $fields,
            'automaticFields' => ClinicalCalculations::FIELDS,
            'categoryLabels' => ClinicalFields::AGE_LABELS,
            'localNow' => now('Asia/Jakarta')->format('Y-m-d\\TH:i'),
        ]);
    }

    public function correct(Request $request, ReportingPeriod $period, Visit $visit, ServiceRecording $recording): RedirectResponse
    {
        Gate::authorize('visits.correct');
        Gate::authorize('visits.view');
        Gate::authorize('residents.view');
        abort_unless($visit->reporting_period_id === $period->id, 404);
        $data = $request->validate([
            'revision' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);
        $recording->correct($period, $visit, $data['revision'], $data['reason']);

        return back()->with(
            'success',
            'Koreksi dibuka sebagai draft; versi final sebelumnya disimpan terenkripsi.'
        );
    }

    public function store(VisitRequest $request, ReportingPeriod $period, TargetMembership $target, ServiceRecording $recording): RedirectResponse
    {
        abort_unless($target->reporting_period_id === $period->id, 404);
        $recording->recordVisit($period, $target, $request->validated());

        return back()->with('success', 'Pengukuran tersimpan sebagai draft. Periksa hasil sebelum finalisasi.');
    }

    public function update(VisitRequest $request, ReportingPeriod $period, TargetMembership $target, Visit $visit, ServiceRecording $recording): RedirectResponse
    {
        abort_unless(
            $target->reporting_period_id === $period->id && $visit->target_membership_id === $target->id && $visit->reporting_period_id === $period->id,
            404
        );
        $recording->recordVisit($period, $target, $request->validated(), $visit);

        return back()->with('success', 'Draft pengukuran diperbarui.');
    }

    public function finalize(Request $request, ReportingPeriod $period, Visit $visit, ServiceRecording $recording): RedirectResponse
    {
        Gate::authorize('visits.finalize');
        Gate::authorize('visits.view');
        Gate::authorize('residents.view');
        abort_unless($visit->reporting_period_id === $period->id, 404);
        $data = $request->validate([
            'revision' => ['required', 'integer', 'min:1'],
            'calculation_reviewed' => ['sometimes', 'boolean'],
        ]);
        $recording->finalize($period, $visit, $data['revision'], $data['calculation_reviewed'] ?? false);

        return back()->with('success', 'Pengukuran difinalkan secara administratif.');
    }
}
