<?php

namespace App\Http\Controllers;

use App\Models\ReportingPeriod;
use App\Services\AuditTrail;
use App\Services\PeriodWorkflow;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ActivityController extends Controller
{
    public function store(Request $request, ReportingPeriod $period, PeriodWorkflow $workflow, AuditTrail $audit): RedirectResponse
    {
        Gate::authorize('targets.manage');
        Gate::authorize('residents.view');
        $data = $request->validate([
            'revision' => ['required', 'integer', 'min:1'],
            'name' => ['required', 'string', 'max:150'],

            'scheduled_on' => ['required', 'date_format:Y-m-d'],
            'location' => ['nullable', 'string', 'max:300'],
        ]);
        $workflow->change($period, $data['revision'], function ($current) use ($data, $audit) {
            $date = CarbonImmutable::parse($data['scheduled_on']);
            if ($date->month !== $current->month || $date->year !== $current->year) {
                throw ValidationException::withMessages(['scheduled_on' => 'Kegiatan harus berada dalam periode yang dipilih.']);
            }
            $session = $current->activities()->create([
                ...Arr::only($data, ['name', 'scheduled_on', 'location']),
                'created_by' => auth()->id(),
            ]);
            $audit->record('activity.created', $session);
        });

        return back()->with('success', 'Kegiatan Posyandu ditambahkan.');
    }
}
