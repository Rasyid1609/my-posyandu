<?php

namespace Tests\Feature;

use App\Models\AgeRuleVersion;
use App\Models\AnalysisRun;
use App\Models\ReportingPeriod;
use App\Models\Resident;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AnalysisComparisonTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_grouped_local_analysis_has_both_period_evidence_and_becomes_stale_when_comparison_changes(): void
    {
        $this->actingAs($this->createSuperadmin());
        $current = ReportingPeriod::factory()->create();
        $compare = ReportingPeriod::factory()->create(['month' => 2, 'reference_date' => '2026-02-28']);
        foreach (Resident::factory()->count(2)->create() as $resident) {
            $current->targets()->create([
                'resident_id' => $resident->id,
                'category_code' => 'adult',
                'attendance_status' => 'present',
            ]);
            $compare->targets()->create([
                'resident_id' => $resident->id,
                'category_code' => 'adult',
                'attendance_status' => 'absent',
            ]);
        }
        $this->post(
            '/rekapitulasi/'.$current->id.'/analisis',
            ['use_ai' => false, 'report_scope' => 'adults', 'compare_id' => $compare->id]
        )
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $run = AnalysisRun::sole();
        $evidence = collect($run->evidence)->keyBy('id');
        $this->assertSame(2, $evidence['adults.present']['value']);
        $this->assertSame(0, $evidence['comparison.adults.present']['value']);
        $this->assertSame('adults', $run->report_scope);
        $this->assertSame($compare->id, $run->comparison_period_id);
        $this->assertCount(2, $run->result['findings']);
        $this->post('/sasaran/'.$compare->id.'/generate', ['revision' => 1])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertTrue($run->fresh()->is_stale);
        $this->patch('/rekapitulasi/'.$current->id.'/analisis/'.$run->id, [
            'review_status' => 'approved',
            'review_note' => 'Percobaan persetujuan sumber berubah',
        ])->assertSessionHasErrors('analysis');
    }

    public function test_incompatible_age_definitions_or_same_period_are_not_compared(): void
    {
        $this->actingAs($this->createSuperadmin());
        $current = ReportingPeriod::factory()->create();
        $rule = AgeRuleVersion::create([
            'version' => 'test-other-version',
            'source' => 'Konfigurasi sintetis berbeda',
            'is_provisional' => true,
            'boundaries' => [12, 60, 72, 180, 240, 720],
        ]);
        $compare = ReportingPeriod::factory()->create(['month' => 2, 'age_rule_version_id' => $rule->id]);
        $this->post(
            '/rekapitulasi/'.$current->id.'/analisis',
            ['use_ai' => false, 'report_scope' => 'all', 'compare_id' => $compare->id]
        )->assertSessionHasErrors('compare_id');
        $this->post(
            '/rekapitulasi/'.$current->id.'/analisis',
            ['use_ai' => false, 'report_scope' => 'adults', 'compare_id' => $current->id]
        )->assertSessionHasErrors('compare_id');
        $this->assertDatabaseCount('analysis_runs', 0);
    }

    public function test_all_groups_have_evidence_without_identifiers_and_scope_is_visible_in_ui(): void
    {
        $this->actingAs($this->createSuperadmin());
        $period = ReportingPeriod::factory()->create();
        $person = Resident::factory()->create(['full_name' => 'RAHASIA Sintetis']);
        $period->targets()->create([
            'resident_id' => $person->id,
            'category_code' => 'adult',
            'attendance_status' => 'absent',
        ]);
        $this->post('/rekapitulasi/'.$period->id.'/analisis', ['use_ai' => false, 'report_scope' => 'all'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $run = AnalysisRun::sole();
        $this->assertStringNotContainsString('RAHASIA', json_encode($run->evidence));
        $this->assertTrue(collect($run->evidence)->contains('id', 'adults.targets'));
        $this->assertTrue(collect($run->evidence)->contains('id', 'home.visited_residents'));
        $this->get('/rekapitulasi/'.$period->id.'/analisis')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('runs.data.0.report_scope', 'all')->has('scopes.children')->has('scopes.maternal'));
    }
}
