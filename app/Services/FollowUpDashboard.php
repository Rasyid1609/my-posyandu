<?php

namespace App\Services;

use App\Models\ReportingPeriod;
use App\Models\TargetMembership;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class FollowUpDashboard
{
    /**
     * @return array{summary: ?array<string, int>, taskCounts: ?array<string, int>, tasks: ?LengthAwarePaginator}
     */
    public function read(ReportingPeriod $period, bool $canViewAggregates, bool $canViewTargets, bool $canViewVisits, string $task, int $page): array
    {
        $attendance = $this->targets($period)->selectRaw('attendance_status, COUNT(*) as total')
            ->groupBy('attendance_status')->pluck('total', 'attendance_status');
        $present = (int) ($attendance['present'] ?? 0);
        $absent = (int) ($attendance['absent'] ?? 0);
        $unconfirmed = (int) ($attendance['unconfirmed'] ?? 0);
        $drafts = $this->visits($period)->where('record_status', 'draft')->count();
        $referrals = $canViewAggregates || $canViewVisits ? $this->pendingReferrals($period) : collect();

        $summary = $canViewAggregates ? [
            'targets' => $present + $absent + $unconfirmed,
            'present' => $present,
            'absent' => $absent,
            'unconfirmed' => $unconfirmed,
            'draft_visits' => $drafts,
            'pending_referrals' => $referrals->count(),
            'overdue_referrals' => $referrals->where('overdue', true)->count(),
        ] : null;
        $counts = $canViewTargets ? ['attendance' => $absent + $unconfirmed] : null;
        if ($canViewVisits) {
            $counts += ['drafts' => $drafts, 'referrals' => $referrals->count()];
        }

        $tasks = null;
        if ($canViewTargets && $task === 'attendance') {
            $tasks = $this->targets($period)->whereIn('attendance_status', ['absent', 'unconfirmed'])
                ->with('resident:id,full_name,registration_number')->orderBy('attendance_status')->orderBy('id')
                ->paginate(10, ['*'], 'page', $page)
                ->through(fn (TargetMembership $target): array => $this->taskRow($period, $target) + [
                    'id' => $target->id, 'status' => $target->attendance_status, 'date' => null, 'overdue' => false,
                ]);
        } elseif ($canViewVisits && $task === 'drafts') {
            $tasks = $this->visits($period)->where('record_status', 'draft')
                ->with('targetMembership.resident:id,full_name,registration_number')->orderBy('visited_at')->orderBy('id')
                ->paginate(10, ['*'], 'page', $page)
                ->through(fn (Visit $visit): array => $this->taskRow($period, $visit->targetMembership, true) + [
                    'id' => $visit->id, 'status' => 'draft', 'date' => $visit->visited_at->timezone('Asia/Jakarta')->toDateString(), 'overdue' => false,
                ]);
        } elseif ($canViewVisits && $task === 'referrals') {
            $items = $referrals->sortBy([
                fn (array $left, array $right): int => ($left['date'] ?? '9999-12-31') <=> ($right['date'] ?? '9999-12-31'),
                ['id', 'asc'],
            ])->values()->forPage($page, 10)->values();
            $targets = TargetMembership::query()->with('resident:id,full_name,registration_number')
                ->whereIn('id', $items->pluck('target_id'))->get()->keyBy('id');
            $tasks = new LengthAwarePaginator(
                $items->map(fn (array $item): array => $this->taskRow($period, $targets[$item['target_id']], true) + $item),
                $referrals->count(), 10, $page, ['path' => route('dashboard')]
            );
        }

        $tasks?->appends(['period' => $period->id, 'task' => $task]);

        return ['summary' => $summary, 'taskCounts' => $counts, 'tasks' => $tasks];
    }

    private function targets(ReportingPeriod $period): HasMany
    {
        return $period->targets()->where('is_excluded', false)->where('is_within_target', true);
    }

    private function visits(ReportingPeriod $period): HasMany
    {
        return $period->visits()->whereHas('targetMembership', fn (Builder $query): Builder => $query
            ->where('reporting_period_id', $period->id)->where('is_excluded', false)->where('is_within_target', true));
    }

    /** @return Collection<int, array{id: int, target_id: int, status: string, date: ?string, overdue: bool}> */
    private function pendingReferrals(ReportingPeriod $period): Collection
    {
        $referrals = collect();
        $today = now('Asia/Jakarta')->toDateString();
        $visits = $this->visits($period)->where('record_status', 'finalized')->whereHas('referral')
            ->with('referral:id,visit_id,follow_up_date,follow_up_status')->lazyById(200);

        foreach ($visits as $visit) {
            $referral = $visit->referral;
            if (! $referral || ! in_array($referral->follow_up_status, ['pending', 'scheduled'], true)) {
                continue;
            }
            $date = $referral->follow_up_date;
            $referrals->push([
                'id' => $visit->id,
                'target_id' => $visit->target_membership_id,
                'status' => $referral->follow_up_status,
                'date' => $date,
                'overdue' => $date !== null && $date < $today,
            ]);
        }

        return $referrals;
    }

    /** @return array{resident_name: string, registration_number: string, href: string} */
    private function taskRow(ReportingPeriod $period, TargetMembership $target, bool $isVisit = false): array
    {
        return [
            'resident_name' => $target->resident->full_name,
            'registration_number' => $target->resident->registration_number,
            'href' => $isVisit ? route('visits.index', ['period' => $period->id, 'target' => $target->id])
                : route('periods.show', ['period' => $period->id, 'target' => $target->id]),
        ];
    }
}
