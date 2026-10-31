<?php

namespace App\Http\Controllers;

use App\Models\AgeRuleVersion;
use App\Models\ReportingPeriod;
use App\Models\Resident;
use App\Services\AuditTrail;
use App\Services\ClinicalFields;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PeriodController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless(
            $request->user()->can('targets.manage') || $request->user()->can('visits.view') || $request->user()->can('aggregates.view'),
            403
        );

        return Inertia::render('Targets/Index', [
            'periods' => ReportingPeriod::query()->orderByDesc('year')->orderByDesc('month')->paginate(12),
            'currentMonth' => now('Asia/Jakarta')->month,
            'currentYear' => now('Asia/Jakarta')->year,
        ]);
    }

    public function store(Request $request, AuditTrail $audit): RedirectResponse
    {
        Gate::authorize('targets.manage');
        $data = $request->validate([
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => [
                'required',
                'integer',
                'between:1900,2200',
                Rule::unique('reporting_periods')->where('month', $request->integer('month')),
            ],
        ]);
        $period = DB::transaction(function () use ($data, $audit) {
            $period = ReportingPeriod::create([
                ...$data,
                'reference_date' => CarbonImmutable::create($data['year'], $data['month'], 1)->endOfMonth()->toDateString(),
                'status' => 'open',
            ]);
            $audit->record('period.created', $period);

            return $period;
        });

        return redirect()->route('periods.show', $period)->with(
            'success',
            'Periode dibuat. Tambahkan sasaran secara manual.'
        );
    }

    public function show(ReportingPeriod $period, Request $request): Response
    {
        Gate::authorize('residents.view');
        abort_unless($request->user()->can('targets.manage') || $request->user()->can('visits.view'), 403);
        $search = $request->validate(['q' => ['nullable', 'string', 'max:100']])['q'] ?? '';

        return Inertia::render('Targets/Show', [
            'period' => $period,
            'activities' => $period->activities()->orderBy('scheduled_on')->orderBy('id')->get(),
            'targets' => $period->targets()->with('resident:id,full_name,registration_number')
                ->withCount('visits')->orderBy('id')->paginate(15)->withQueryString(),
            'candidates' => $request->user()->can('targets.manage') ? Resident::query()
                ->where('residency_status', 'active')->whereRaw('LOWER(full_name) LIKE ?', ['%'.mb_strtolower($search).'%'])
                ->orderBy('full_name')->orderBy('id')->limit(30)->get(['id', 'full_name', 'registration_number']) : [],
            'search' => $search,
            'categoryLabels' => ClinicalFields::AGE_LABELS,
            'ageRule' => $period->age_rule_version_id ? AgeRuleVersion::find($period->age_rule_version_id) : null,
        ]);
    }
}
