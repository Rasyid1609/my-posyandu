<?php

namespace App\Http\Controllers;

use App\Http\Requests\HomeVisitRequest;
use App\Models\HomeVisit;
use App\Models\Household;
use App\Models\ReportingPeriod;
use App\Models\User;
use App\Services\AuditTrail;
use App\Services\HomeVisitRecording;
use App\Services\PeriodWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class HomeVisitController extends Controller
{
    public function correct(Request $request, ReportingPeriod $period, HomeVisit $homeVisit, HomeVisitRecording $recording): RedirectResponse
    {
        Gate::authorize('visits.correct');
        Gate::authorize('visits.view');
        Gate::authorize('residents.view');
        abort_unless($homeVisit->reporting_period_id === $period->id, 404);
        $data = $request->validate([
            'revision' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);
        $recording->correct($period, $homeVisit, $data['revision'], $data['reason']);

        return back()->with('success', 'Koreksi kunjungan rumah dibuka; versi lama disimpan terenkripsi.');
    }

    public function index(Request $request, ReportingPeriod $period, AuditTrail $audit): Response
    {
        Gate::authorize('visits.view');
        Gate::authorize('residents.view');
        $data = $request->validate([
            'household_id' => ['nullable', 'integer', 'exists:households,id'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $household = isset($data['household_id']) ? Household::with('residents:id,household_id,full_name')->findOrFail($data['household_id']) : null;
        $audit->record('home_visits.viewed', $period);

        return Inertia::render('Targets/HomeVisits', [
            'period' => $period,
            'household' => $household,

            'households' => Household::where('is_active', true)
                ->when($data['q'] ?? null, fn ($q, $text) => $q->whereRaw('LOWER(head_of_household_name) LIKE ?', ['%'.mb_strtolower($text).'%']))
                ->orderBy('head_of_household_name')
                ->limit(50)
                ->get(['id', 'head_of_household_name']),

            'visits' => $period->homeVisits()
                ->with([
                    'household:id,head_of_household_name',
                    'residents:id,full_name',
                    'staff:id,name',
                ])
                ->latest('id')
                ->paginate(15)
                ->withQueryString(),

            'staff' => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),

            'targetCount' => $period->householdTargets()->count(),
            'today' => now('Asia/Jakarta')->toDateString(),
        ]);
    }

    public function store(HomeVisitRequest $request, ReportingPeriod $period, HomeVisitRecording $recording): RedirectResponse
    {
        $recording->save($period, $request->validated());

        return back()->with('success', 'Kunjungan rumah disimpan sebagai draft.');
    }

    public function update(HomeVisitRequest $request, ReportingPeriod $period, HomeVisit $homeVisit, HomeVisitRecording $recording): RedirectResponse
    {
        abort_unless($homeVisit->reporting_period_id === $period->id, 404);
        $recording->save($period, $request->validated(), $homeVisit);

        return back()->with('success', 'Draft kunjungan rumah diperbarui.');
    }

    public function finalize(Request $request, ReportingPeriod $period, HomeVisit $homeVisit, HomeVisitRecording $recording): RedirectResponse
    {
        Gate::authorize('visits.finalize');
        Gate::authorize('residents.view');
        Gate::authorize('visits.view');
        abort_unless($homeVisit->reporting_period_id === $period->id, 404);
        $data = $request->validate(['revision' => ['required', 'integer', 'min:1']]);
        $recording->finalize($period, $homeVisit, $data['revision']);

        return back()->with('success', 'Kunjungan rumah difinalkan administratif.');
    }

    public function targets(Request $request, ReportingPeriod $period, PeriodWorkflow $workflow, AuditTrail $audit): RedirectResponse
    {
        Gate::authorize('targets.manage');
        Gate::authorize('residents.view');
        $data = $request->validate(['revision' => ['required', 'integer', 'min:1']]);
        $workflow->change($period, $data['revision'], function ($current) use ($audit) {
            Household::where('is_active', true)->orderBy('id')->chunkById(200, function ($families) use ($current) {
                foreach ($families as $family) {
                    $current->householdTargets()->firstOrCreate(['household_id' => $family->id]);
                }
            });
            $audit->record('household_targets.generated', $current);
        });

        return back()->with('success', 'Sasaran keluarga dibentuk tanpa duplikasi.');
    }
}
