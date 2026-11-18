<?php

namespace App\Http\Controllers;

use App\Models\ReportingPeriod;
use App\Services\AuditTrail;
use App\Services\FollowUpDashboard;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request, FollowUpDashboard $dashboard, AuditTrail $audit): Response
    {
        $user = $request->user();
        $canViewAggregates = $user->can('aggregates.view');
        $canViewTargets = $user->can('residents.view') && ($user->can('targets.manage') || $user->can('visits.view'));
        $canViewVisits = $user->can('residents.view') && $user->can('visits.view');

        if (! $canViewAggregates && ! $canViewTargets) {
            return Inertia::render('AccessPending');
        }

        $filters = $request->validate([
            'period' => ['nullable', 'integer', Rule::exists('reporting_periods', 'id')],
            'task' => ['nullable', Rule::in(['attendance', 'drafts', 'referrals'])],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000000'],
        ]);
        $task = $filters['task'] ?? 'attendance';
        if (isset($filters['task'])) {
            abort_unless($task === 'attendance' ? $canViewTargets : $canViewVisits, 403);
        }

        $periods = ReportingPeriod::query()->orderByDesc('year')->orderByDesc('month')
            ->get(['id', 'month', 'year', 'status', 'revision', 'reference_date']);
        $today = now('Asia/Jakarta');
        $period = isset($filters['period']) ? $periods->firstWhere('id', (int) $filters['period'])
            : ($periods->first(fn (ReportingPeriod $period): bool => $period->month === $today->month && $period->year === $today->year) ?? $periods->first());

        $data = $period ? $dashboard->read($period, $canViewAggregates, $canViewTargets, $canViewVisits, $task, (int) ($filters['page'] ?? 1))
            : ['summary' => null, 'taskCounts' => null, 'tasks' => null];

        if ($period && $canViewTargets) {
            $audit->record('dashboard.viewed', $period);
        }

        return Inertia::render('Dashboard', [
            'periods' => $periods,
            'period' => $period,
            'selectedTask' => $task,
            ...$data,
        ]);
    }
}
