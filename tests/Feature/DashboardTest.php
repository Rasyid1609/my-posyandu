<?php

namespace Tests\Feature;

use App\Models\ReportingPeriod;
use App\Models\Resident;
use App\Models\TargetMembership;
use App\Models\User;
use App\Models\Visit;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guests_and_inactive_accounts_cannot_open_dashboard(): void
    {
        $this->get('/dashboard')->assertRedirectToRoute('login');
        $this->actingAs(User::factory()->create(['is_active' => false]))
            ->get('/dashboard')->assertRedirectToRoute('login');
    }

    public function test_unassigned_accounts_receive_access_pending_without_dashboard_data(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('AccessPending')->missing('summary')->missing('tasks')->missing('periods'));
    }

    public function test_dashboard_has_an_empty_state_before_first_period(): void
    {
        $this->actingAs($this->createStaff());

        $this->get('/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')->has('periods', 0)->where('period', null)
            ->where('summary', null)->where('tasks', null));
    }

    public function test_dashboard_uses_current_month_and_excludes_out_of_scope_targets(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-01-15 09:00:00', 'Asia/Jakarta'));
        $this->actingAs($this->createStaff());
        $period = ReportingPeriod::factory()->create();
        $other = ReportingPeriod::factory()->create(['month' => 2, 'reference_date' => '2026-02-28']);
        $present = $this->target($period, 'present');
        $absent = $this->target($period, 'absent');
        $unconfirmed = $this->target($period);
        $excluded = $this->target($period);
        $excluded->forceFill(['is_excluded' => true])->save();
        $outside = $this->target($period);
        $outside->forceFill(['is_within_target' => false])->save();
        $this->visit($period, $present);
        $this->visit($period, $excluded);
        $this->visit($period, $outside);
        $this->target($other);

        $this->get('/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')->where('period.id', $period->id)
            ->where('summary.targets', 3)->where('summary.present', 1)
            ->where('summary.absent', 1)->where('summary.unconfirmed', 1)
            ->where('summary.draft_visits', 1)->where('taskCounts.attendance', 2)
            ->has('tasks.data', 2)->where('tasks.data.0.id', $absent->id)
            ->where('tasks.data.1.id', $unconfirmed->id)
            ->where('tasks.data.0.href', route('periods.show', ['period' => $period->id, 'target' => $absent->id])));
        $this->assertDatabaseHas('audit_logs', ['action' => 'dashboard.viewed', 'resource_id' => $period->id]);
    }

    public function test_selected_period_and_paginated_drafts_link_to_individual_service_history(): void
    {
        $this->actingAs($this->createStaff());
        $period = ReportingPeriod::factory()->create();
        ReportingPeriod::factory()->create(['month' => 2, 'reference_date' => '2026-02-28']);
        $target = $this->target($period, 'present');
        for ($index = 0; $index < 11; $index++) {
            $this->visit($period, $target);
        }
        $this->visit($period, $target, 'finalized');

        $this->get('/dashboard?period='.$period->id.'&task=drafts&page=2')
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('period.id', $period->id)->where('summary.draft_visits', 11)
            ->where('selectedTask', 'drafts')->where('tasks.total', 11)
            ->where('tasks.current_page', 2)->has('tasks.data', 1)
            ->where('tasks.data.0.href', route('visits.index', ['period' => $period->id, 'target' => $target->id])));
    }

    public function test_encrypted_referrals_are_prioritized_by_due_date_using_jakarta_time(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-01-14 17:05:00', 'UTC'));
        $this->actingAs($this->createStaff());
        $period = ReportingPeriod::factory()->create();
        $target = $this->target($period, 'present');
        $undated = $this->referral($period, $target, null);
        $today = $this->referral($period, $target, '2026-01-15');
        $overdue = $this->referral($period, $target, '2026-01-14');
        $scheduled = $this->referral($period, $target, '2026-01-20', 'scheduled');
        $this->referral($period, $target, '2026-01-01', 'completed');
        $this->referral($period, $target, '2026-01-01', 'declined');
        $this->referral($period, $target, '2026-01-01', 'pending', 'draft');
        $other = ReportingPeriod::factory()->create(['month' => 2, 'reference_date' => '2026-02-28']);
        $this->referral($other, $this->target($other), '2026-01-01');

        $response = $this->get('/dashboard?period='.$period->id.'&task=referrals');

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('summary.pending_referrals', 4)->where('summary.overdue_referrals', 1)
            ->has('tasks.data', 4)->where('tasks.data.0.id', $overdue->id)->where('tasks.data.0.overdue', true)
            ->where('tasks.data.1.id', $today->id)->where('tasks.data.1.overdue', false)
            ->where('tasks.data.2.id', $scheduled->id)->where('tasks.data.3.id', $undated->id)
            ->where('tasks.data.3.date', null)->where('tasks.data.3.overdue', false)
            ->missing('tasks.data.0.reason')->missing('tasks.data.0.destination'));
        $response->assertDontSee('RAHASIA ALASAN')->assertDontSee('RAHASIA TUJUAN');
    }

    public function test_aggregate_only_accounts_receive_counts_without_warga_or_task_details(): void
    {
        $this->actingAs($this->createStaff('bendahara'));
        $period = ReportingPeriod::factory()->create();
        $target = $this->target($period);
        $target->resident->update(['full_name' => 'RAHASIA WARGA']);
        $this->referral($period, $target, null);

        $response = $this->get('/dashboard');

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('summary.targets', 1)->where('summary.pending_referrals', 1)
            ->where('tasks', null)->where('taskCounts', null));
        $response->assertDontSee('RAHASIA WARGA')->assertDontSee($target->resident->registration_number);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'dashboard.viewed']);
        $this->get('/dashboard?task=referrals')->assertForbidden();
    }

    public function test_target_managers_without_visit_permission_cannot_receive_clinical_workloads(): void
    {
        $staff = $this->createStaff('bendahara');
        $staff->syncRoles([]);
        $staff->givePermissionTo(['residents.view', 'targets.manage']);
        $this->actingAs($staff);
        $period = ReportingPeriod::factory()->create();
        $this->target($period);

        $this->get('/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('summary', null)->where('taskCounts', ['attendance' => 1])->has('tasks.data', 1));
        $this->get('/dashboard?task=drafts')->assertForbidden();
        $this->get('/dashboard?task=referrals')->assertForbidden();
    }

    public function test_attendance_action_opens_only_the_selected_target_and_rejects_other_periods(): void
    {
        $this->actingAs($this->createStaff());
        $period = ReportingPeriod::factory()->create();
        $target = $this->target($period);
        $this->target($period);
        $other = ReportingPeriod::factory()->create(['month' => 2, 'reference_date' => '2026-02-28']);
        $foreign = $this->target($other);

        $this->get('/sasaran/'.$period->id.'?target='.$target->id)->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('targets.data', 1)
                ->where('targets.data.0.id', $target->id)->where('targetFilter', $target->id));
        $this->get('/sasaran/'.$period->id.'?target='.$foreign->id)->assertSessionHasErrors('target');
    }

    /** @return array<string, array{string, string}> */
    public static function invalidFilters(): array
    {
        return [
            'unknown period' => ['period=999999', 'period'],
            'invalid period' => ['period=abc', 'period'],
            'unknown task' => ['task=anything', 'task'],
            'invalid page' => ['page=0', 'page'],
        ];
    }

    #[DataProvider('invalidFilters')]
    public function test_dashboard_rejects_invalid_filters(string $query, string $field): void
    {
        $this->actingAs($this->createStaff());

        $this->get('/dashboard?'.$query)->assertSessionHasErrors($field);
    }

    private function target(ReportingPeriod $period, string $attendance = 'unconfirmed'): TargetMembership
    {
        return $period->targets()->create([
            'resident_id' => Resident::factory()->create()->id,
            'category_code' => 'adult',
            'attendance_status' => $attendance,
        ]);
    }

    private function visit(ReportingPeriod $period, TargetMembership $target, string $status = 'draft'): Visit
    {
        return $period->visits()->create([
            'target_membership_id' => $target->id,
            'resident_id' => $target->resident_id,
            'visited_at' => '2026-01-10 09:00:00',
            'record_status' => $status,
        ]);
    }

    private function referral(ReportingPeriod $period, TargetMembership $target, ?string $date, string $status = 'pending', string $recordStatus = 'finalized'): Visit
    {
        $visit = $this->visit($period, $target, $recordStatus);
        $visit->referral()->create([
            'destination' => 'RAHASIA TUJUAN',
            'reason' => 'RAHASIA ALASAN',
            'follow_up_date' => $date,
            'follow_up_status' => $status,
        ]);

        return $visit;
    }
}
