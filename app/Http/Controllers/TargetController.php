<?php

namespace App\Http\Controllers;

use App\Http\Requests\TargetRequest;
use App\Models\ReportingPeriod;
use App\Models\TargetMembership;
use App\Services\AuditTrail;
use App\Services\PeriodWorkflow;
use App\Services\ServiceRecording;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TargetController extends Controller
{
    public function refresh(Request $request, ReportingPeriod $period, TargetMembership $target, ServiceRecording $recording): RedirectResponse
    {
        Gate::authorize('targets.manage');
        Gate::authorize('residents.view');
        abort_unless($target->reporting_period_id === $period->id, 404);
        $data = $request->validate([
            'revision' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);
        $recording->refreshSnapshot($period, $target, $data);

        return back()->with(
            'success',
            'Snapshot diperbarui secara eksplisit dengan aturan periode. Tinjau kembali rekap.'
        );
    }

    public function generate(Request $request, ReportingPeriod $period, ServiceRecording $recording): RedirectResponse
    {
        Gate::authorize('targets.manage');
        Gate::authorize('residents.view');
        $data = $request->validate(['revision' => ['required', 'integer', 'min:1']]);
        $recording->generate($period, $data['revision']);

        return back()->with('success', 'Sasaran dibentuk tanpa duplikasi. Tinjau warga dan kehadirannya.');
    }

    public function store(TargetRequest $request, ReportingPeriod $period, ServiceRecording $recording): RedirectResponse
    {
        $recording->addTarget($period, $request->validated());

        return back()->with('success', 'Sasaran tersimpan tanpa duplikasi.');
    }

    public function update(Request $request, ReportingPeriod $period, TargetMembership $target, ServiceRecording $recording): RedirectResponse
    {
        Gate::authorize('targets.manage');
        Gate::authorize('residents.view');
        abort_unless($target->reporting_period_id === $period->id, 404);
        $data = $request->validate([
            'revision' => ['required', 'integer', 'min:1'],
            'attendance_status' => ['required', Rule::in(['unconfirmed', 'present', 'absent'])],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);
        $recording->attendance($period, $target, $data);

        return back()->with('success', 'Kehadiran diperbarui.');
    }

    public function destroy(Request $request, ReportingPeriod $period, TargetMembership $target, PeriodWorkflow $workflow, AuditTrail $audit): RedirectResponse
    {
        Gate::authorize('targets.manage');
        Gate::authorize('residents.view');
        abort_unless($target->reporting_period_id === $period->id, 404);
        $data = $request->validate([
            'revision' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);
        $workflow->change($period, $data['revision'], function () use ($target, $data, $audit) {
            if ($target->visits()->exists()) {
                throw ValidationException::withMessages(['period' => 'Sasaran yang memiliki kunjungan tidak dapat dikeluarkan.']);
            }
            $target->is_excluded = true;
            $target->save();
            $audit->record('target.excluded', $target, $data['reason']);
        });

        return back()->with('success', 'Sasaran dikeluarkan dengan riwayat tetap tersedia.');
    }
}
