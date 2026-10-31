<?php

namespace Tests\Feature;

use App\Models\ReportingPeriod;
use App\Models\Resident;
use App\Services\GroupedReport;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ClinicalCalculationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function target(string $birth = '1990-01-15', string $gender = 'L', string $category = 'adult', array $residentAttributes = []): array
    {
        $period = ReportingPeriod::factory()->create();
        $resident = Resident::factory()->create(['birth_date' => $birth, 'gender' => $gender, ...$residentAttributes]);
        $target = $period->targets()->create([
            'resident_id' => $resident->id,
            'category_code' => $category,
            'gender_snapshot' => $gender,
        ]);

        return [$period, $target, '/sasaran/'.$period->id.'/anggota/'.$target->id];
    }

    private function measurements(array $overrides = []): array
    {
        return $this->visitPayload(1, [
            'assessment_mode' => 'automatic',
            'height_method' => 'standing',
            'weight_state' => 'measured',
            'weight_kg' => 60,
            'height_state' => 'measured',
            'height_cm' => 160,
            ...$overrides,
        ]);
    }

    public static function adultBoundaries(): array
    {
        return [
            'below 18.5' => [18.49, 100, 'underweight'],
            'at 18.5' => [18.5, 100, 'normal'],

            'below 23' => [22.99, 100, 'normal'],
            'at 23' => [23, 100, 'overweight'],
            'below 25' => [24.99, 100, 'overweight'],

            'at 25' => [25, 100, 'obese_1'],
            'below 30' => [29.99, 100, 'obese_1'],
            'at 30' => [30, 100, 'obese_2'],

            'category uses unrounded BMI' => [91.99, 200, 'normal'],
            'exact quotient boundary' => [33.12, 120, 'overweight'],
        ];
    }

    #[DataProvider('adultBoundaries')]
    public function test_adult_bmi_uses_current_asia_pacific_boundaries_before_display_rounding(float $weight, float $height, string $category): void
    {
        $this->actingAs($this->createStaff());
        [, , $url] = $this->target();

        $this->postJson(
            $url.'/perhitungan',
            $this->measurements(['weight_kg' => $weight, 'height_cm' => $height])
        )
            ->assertOk()->assertJsonPath('results.bmi.category', $category)->assertJsonPath('results.bmi.source.title', 'PNPK Obesitas Dewasa 509/2025');

        $this->assertDatabaseCount('visits', 0);
    }

    public static function childBoundaries(): array
    {
        return [
            'severe' => [12.09, 'severe'],
            'exact -3 SD' => [12.1, 'low'],
            'below -2 SD' => [12.99, 'low'],

            'exact -2 SD' => [13, 'normal'],
            'exact +1 SD' => [16.6, 'normal'],
            'above +1 SD' => [16.61, 'overweight'],

            'exact +2 SD' => [18.3, 'overweight'],
            'above +2 SD' => [18.31, 'obese'],
        ];
    }

    #[DataProvider('childBoundaries')]
    public function test_child_bmi_uses_sex_and_completed_months_instead_of_adult_cutoffs(float $weight, string $category): void
    {
        $this->actingAs($this->createStaff());
        [, , $url] = $this->target('2020-12-15', 'L', 'preschool');

        $this->postJson(
            $url.'/perhitungan',
            $this->measurements(['weight_kg' => $weight, 'height_cm' => 100])
        )
            ->assertOk()->assertJsonPath('age_months', 61)->assertJsonPath('results.bmi.category', $category);
    }

    public function test_child_height_position_is_corrected_without_changing_original_measurements(): void
    {
        $this->actingAs($this->createStaff());
        [, , $url] = $this->target('2025-01-15', 'L', 'toddler');

        $this->postJson($url.'/perhitungan', $this->measurements(['weight_kg' => 9.6, 'height_cm' => 75]))
            ->assertOk()->assertJsonPath('corrected_height_cm', 75.7)->assertJsonPath('inputs.height_cm', 75)
            ->assertJsonPath('results.weight_age.category', 'normal')->assertJsonPath('results.height_age.category', 'normal')
            ->assertJsonPath('results.weight_height.category', 'normal');
        $this->postJson(
            $url.'/perhitungan',
            $this->measurements(['weight_kg' => 9.6, 'height_cm' => 75.7, 'height_method' => 'recumbent'])
        )
            ->assertOk()->assertJsonPath('corrected_height_cm', 75.7)->assertJsonPath('results.height_age.category', 'normal');
    }

    public function test_sex_and_the_exact_calendar_day_change_the_correct_child_reference(): void
    {
        $this->actingAs($this->createStaff());
        [, $target, $url] = $this->target('2020-12-15', 'L', 'school');
        $this->postJson(
            $url.'/perhitungan',
            $this->measurements(['weight_kg' => 16.8, 'height_cm' => 100, 'visited_at' => '2026-01-14T09:00'])
        )
            ->assertOk()->assertJsonPath('age_months', 60);
        $this->postJson($url.'/perhitungan', $this->measurements(['weight_kg' => 16.8, 'height_cm' => 100]))
            ->assertOk()->assertJsonPath('age_months', 61)->assertJsonPath('results.bmi.category', 'overweight');
        $target->forceFill(['gender_snapshot' => 'P'])->save();
        $this->postJson($url.'/perhitungan', $this->measurements(['weight_kg' => 16.8, 'height_cm' => 100]))
            ->assertOk()->assertJsonPath('results.bmi.category', 'normal');
    }

    public function test_child_position_transition_uses_local_calendar_days(): void
    {
        $this->actingAs($this->createStaff());
        [, $target, $url] = $this->target('2024-01-15', 'P', 'toddler');
        $this->postJson(
            $url.'/perhitungan',
            $this->measurements(['height_cm' => 87.8, 'height_method' => 'recumbent'])
        )
            ->assertOk()->assertJsonPath('age_months', 24)->assertJsonPath('corrected_height_cm', 87.8);
        $this->postJson($url.'/perhitungan', $this->measurements([
            'height_cm' => 87.8,
            'height_method' => 'recumbent',
            'visited_at' => '2026-02-15T09:00',
        ]))
            ->assertOk()->assertJsonPath('age_months', 25)->assertJsonPath('corrected_height_cm', 87.1);
    }

    public function test_pregnancy_episode_dates_include_both_endpoints_in_the_local_calendar(): void
    {
        $this->actingAs($this->createStaff());
        [, $target, $url] = $this->target('1990-01-15', 'P');
        $target->resident->maternalEpisodes()->create([
            'episode_type' => 'pregnant',
            'start_date' => '2026-01-15',
            'end_date' => '2026-01-15',
            'source' => 'Fixture kalender sintetis',
        ]);
        $this->postJson($url.'/perhitungan', $this->measurements())
            ->assertOk()
            ->assertJsonPath('pregnant', true)
            ->assertJsonPath('results.bmi.status', 'unavailable');
        $this->postJson($url.'/perhitungan', $this->measurements(['visited_at' => '2026-01-16T09:00']))
            ->assertOk()
            ->assertJsonPath('pregnant', false);
    }

    public function test_missing_height_method_uncertain_birth_date_and_pregnancy_do_not_produce_false_normal_results(): void
    {
        $this->actingAs($this->createStaff());
        [, $target, $url] = $this->target('2025-01-15', 'P', 'toddler');
        $this->postJson(
            $url.'/perhitungan',
            $this->measurements(['weight_kg' => 8.9, 'height_cm' => 74, 'height_method' => 'unknown'])
        )
            ->assertOk()->assertJsonPath('results.bmi.status', 'unavailable')->assertJsonPath('results.height_age.status', 'unavailable');
        $target->resident->update(['is_birth_date_estimated' => true]);
        $this->postJson($url.'/perhitungan', $this->measurements())
            ->assertOk()
            ->assertJsonPath('age_months', null)
            ->assertJsonPath('results.bmi.category', null);
        $target->forceFill(['is_pregnant_snapshot' => true])->save();
        $this->postJson($url.'/perhitungan', $this->measurements())
            ->assertOk()
            ->assertJsonPath('results.bmi.mapped_value', 'not_applicable');
    }

    public function test_incomplete_official_table_row_and_ambiguous_height_intervals_are_not_guessed(): void
    {
        $this->actingAs($this->createStaff());
        [, , $url] = $this->target('2023-12-15', 'P', 'toddler');

        $this->postJson($url.'/perhitungan', $this->measurements(['weight_kg' => 8, 'height_cm' => 69.5]))
            ->assertOk()->assertJsonPath('results.weight_height.status', 'unavailable');
        $this->postJson(
            $url.'/perhitungan',
            $this->measurements(['weight_kg' => 7.05, 'height_cm' => 70.2])
        )
            ->assertOk()->assertJsonPath('results.weight_height.status', 'unavailable');
    }

    public function test_fasting_and_random_glucose_are_not_treated_as_the_same_examination(): void
    {
        $this->actingAs($this->createStaff());
        [, , $url] = $this->target();
        $payload = $this->measurements(['glucose_state' => 'measured', 'glucose_mg_dl' => 110]);
        $this->postJson($url.'/perhitungan', [...$payload, 'glucose_context' => 'fasting_plasma'])
            ->assertOk()
            ->assertJsonPath('results.glucose.category', 'risk');
        $this->postJson($url.'/perhitungan', [...$payload, 'glucose_context' => 'random_capillary'])
            ->assertOk()
            ->assertJsonPath('results.glucose.status', 'unavailable');
        $this->postJson($url.'/perhitungan', [...$payload, 'glucose_context' => 'unknown'])
            ->assertOk()
            ->assertJsonPath('results.glucose.status', 'unavailable');
        $this->postJson(
            $url.'/perhitungan',
            [...$payload, 'glucose_context' => 'fasting_plasma', 'glucose_mg_dl' => 69]
        )
            ->assertOk()
            ->assertJsonPath('results.glucose.category', 'low');
        $this->postJson(
            $url.'/perhitungan',
            [...$payload, 'glucose_context' => 'random_capillary', 'glucose_mg_dl' => 200]
        )
            ->assertOk()
            ->assertJsonPath('results.glucose.category', 'high');
    }

    public function test_pressure_requires_repeated_measurement_and_flags_either_high_component(): void
    {
        $this->actingAs($this->createStaff());
        [, , $url] = $this->target();
        $payload = $this->measurements([
            'blood_pressure_state' => 'measured',
            'systolic_mmhg' => 120,
            'diastolic_mmhg' => 90,
        ]);
        $this->postJson($url.'/perhitungan', $payload)
            ->assertOk()
            ->assertJsonPath('results.pressure.status', 'unavailable');
        $this->postJson($url.'/perhitungan', [...$payload, 'blood_pressure_confirmed' => true])
            ->assertOk()
            ->assertJsonPath('results.pressure.category', 'high');
        $this->postJson($url.'/perhitungan', [
            ...$payload,
            'blood_pressure_confirmed' => true,
            'systolic_mmhg' => 140,
            'diastolic_mmhg' => 80,
        ])
            ->assertOk()
            ->assertJsonPath('results.pressure.category', 'high');
    }

    public function test_saved_results_are_recomputed_encrypted_and_reviewed_before_entering_reports(): void
    {
        $this->actingAs($chair = $this->createSuperadmin());
        [$period, $target, $url] = $this->target();
        $this->post($url.'/kunjungan', $this->measurements([
            'weight_kg' => 60,
            'height_cm' => 160,
            'bmi_status' => 'normal',
            'calculation_details' => ['mode' => 'manual'],
        ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $visit = $target->visits()->sole();
        $exam = $visit->examination;
        $this->assertSame('overweight', $exam->bmi_status);
        $this->assertSame(23.44, $exam->calculation_details['results']['bmi']['value']);
        $this->assertSame('clinical-calculation-v1', $exam->calculation_details['version']);
        $this->assertStringNotContainsString('overweight', $exam->getRawOriginal('calculation_details'));
        $this->assertSame(
            'automatic',
            json_decode(Crypt::decryptString($exam->getRawOriginal('calculation_details')), true)['mode']
        );
        $finalize = '/sasaran/'.$period->id.'/kunjungan/'.$visit->id.'/finalisasi';
        $revision = $period->fresh()->revision;
        $this->post($finalize, ['revision' => $revision])->assertSessionHasErrors('calculation_reviewed');
        $this->assertSame('draft', $visit->fresh()->record_status);
        $this->assertSame($revision, $period->fresh()->revision);
        $this->post($finalize, ['revision' => $revision, 'calculation_reviewed' => true])
            ->assertSessionHasNoErrors()
            ->assertRedirect();
        $this->assertSame($chair->id, $visit->fresh()->examination->calculation_details['reviewed_by']);
        $rows = collect(app(GroupedReport::class)->calculate($period)['adults']['rows'])->keyBy('code');
        $this->assertSame(1, $rows['bmi_status.overweight']['value']);
        $period->update(['status' => 'locked']);
        $this->put(
            $url.'/kunjungan/'.$visit->id,
            $this->measurements(['revision' => $period->fresh()->revision, 'weight_kg' => 45])
        )->assertSessionHasErrors('period');
        $this->assertSame(
            23.44,
            $visit->fresh()->examination->calculation_details['results']['bmi']['value']
        );
    }

    public function test_editing_draft_recalculates_and_clearing_measurements_removes_old_categories(): void
    {
        $this->actingAs($this->createStaff());
        [$period, $target, $url] = $this->target();
        $this->post($url.'/kunjungan', $this->measurements())->assertSessionHasNoErrors();
        $visit = $target->visits()->sole();
        $this->put(
            $url.'/kunjungan/'.$visit->id,
            $this->measurements(['revision' => $period->fresh()->revision, 'weight_kg' => 50])
        )->assertSessionHasNoErrors();
        $this->assertSame('normal', $visit->fresh()->examination->bmi_status);
        $this->put($url.'/kunjungan/'.$visit->id, $this->measurements([
            'revision' => $period->fresh()->revision,
            'weight_state' => 'declined',
            'weight_kg' => null,
        ]))->assertSessionHasNoErrors();
        $this->assertNull($visit->fresh()->examination->calculation_details['results']['bmi']['value']);
        $this->assertSame('unknown', $visit->fresh()->examination->bmi_status);
    }

    public function test_manual_override_needs_provenance_and_reason_and_preserves_previous_calculation(): void
    {
        $this->actingAs($this->createStaff());
        [$period, $target, $url] = $this->target();
        $this->post($url.'/kunjungan', $this->measurements())->assertSessionHasNoErrors();
        $visit = $target->visits()->sole();
        $payload = $this->measurements([
            'revision' => $period->fresh()->revision,
            'assessment_mode' => 'manual',
            'bmi_status' => 'normal',
        ]);
        $this->put($url.'/kunjungan/'.$visit->id, $payload)->assertSessionHasErrors(['assessor_name', 'assessment_source', 'calculation_override_reason']);
        $sparse = $payload;
        unset($sparse['bmi_status']);
        $sparse['calculation_override_reason'] = 'Pengujian payload parsial';
        $this->put($url.'/kunjungan/'.$visit->id, $sparse)->assertSessionHasErrors(['assessor_name', 'assessment_source']);
        $this->put($url.'/kunjungan/'.$visit->id, [
            ...$payload,
            'assessor_name' => 'DEMO Petugas',
            'assessment_source' => 'Catatan pemeriksaan sintetis',
            'calculation_override_reason' => 'Koreksi berdasarkan penilaian petugas',
        ])->assertSessionHasNoErrors();
        $details = $visit->fresh()->examination->calculation_details;
        $this->assertSame('manual', $details['mode']);
        $this->assertSame('overweight', $details['previous_calculation']['results']['bmi']['category']);
        $this->assertSame('Koreksi berdasarkan penilaian petugas', $details['override_reason']);
        $this->put($url.'/kunjungan/'.$visit->id, [
            ...$payload,
            'revision' => $period->fresh()->revision,
            'assessor_name' => 'DEMO Petugas',
            'assessment_source' => 'Catatan pemeriksaan sintetis',
        ])->assertSessionHasNoErrors();
        $this->assertSame(
            'Koreksi berdasarkan penilaian petugas',
            $visit->fresh()->examination->calculation_details['override_reason']
        );
    }

    public function test_weight_trend_uses_only_earlier_final_records_and_a_verified_growth_interval(): void
    {
        $this->actingAs($this->createStaff());
        [$period, $target, $url] = $this->target('2025-01-15', 'L', 'toddler');
        $old = $period->visits()->create([
            'resident_id' => $target->resident_id,
            'target_membership_id' => $target->id,
            'visited_at' => '2026-01-15 02:00:00',
            'record_status' => 'finalized',
        ]);
        $old->examination()->create(['category_code' => 'toddler', 'weight_state' => 'measured', 'weight_kg' => 8]);
        $draft = $period->visits()->create([
            'resident_id' => $target->resident_id,
            'target_membership_id' => $target->id,
            'visited_at' => '2026-03-15 02:00:00',
            'record_status' => 'draft',
        ]);
        $draft->examination()->create(['category_code' => 'toddler', 'weight_state' => 'measured', 'weight_kg' => 50]);
        $this->postJson(
            $url.'/perhitungan',
            $this->measurements(['visited_at' => '2026-04-15T09:00', 'weight_kg' => 8.1])
        )
            ->assertOk()->assertJsonPath('results.weight_trend.previous.visit_id', $old->id)->assertJsonPath('results.weight_trend.minimum_gain_g', 88)
            ->assertJsonPath('results.weight_trend.category', 'adequate');
        $this->postJson(
            $url.'/perhitungan',
            $this->measurements(['visited_at' => '2026-04-15T09:00', 'weight_kg' => 8.08])
        )
            ->assertOk()->assertJsonPath('results.weight_trend.category', 'below_standard');
        $this->postJson(
            $url.'/perhitungan',
            $this->measurements(['visited_at' => '2026-02-15T09:00', 'weight_kg' => 8.05])
        )
            ->assertOk()->assertJsonPath('results.weight_trend.status', 'unavailable');
    }

    public function test_preview_enforces_auth_permissions_target_scope_and_measurement_validation(): void
    {
        [$period, $target, $url] = $this->target();
        $this->postJson($url.'/perhitungan', $this->measurements())->assertUnauthorized();
        $this->actingAs($this->createStaff('bendahara'));
        $this->postJson($url.'/perhitungan', $this->measurements())->assertForbidden();
        $this->actingAs($this->createStaff());
        $this->postJson($url.'/perhitungan', $this->measurements(['height_cm' => 0]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('height_cm');
        $other = ReportingPeriod::factory()->create(['month' => 2]);
        $this->postJson(
            '/sasaran/'.$other->id.'/anggota/'.$target->id.'/perhitungan',
            $this->measurements()
        )->assertNotFound();
        $this->postJson($url.'/perhitungan', $this->measurements(['visit_id' => 999999]))->assertNotFound();
        $this->assertSame(1, $period->fresh()->revision);
        $this->assertDatabaseCount('visits', 0);
    }

    public function test_legacy_manual_result_stays_manual_when_loaded_for_editing(): void
    {
        $this->actingAs($this->createStaff());
        [, $target, $url] = $this->target();
        $this->post($url.'/kunjungan', $this->visitPayload(1, [
            'bmi_status' => 'normal',
            'assessor_name' => 'DEMO Petugas',
            'assessment_source' => 'Dokumen hasil petugas',
        ]))->assertSessionHasNoErrors();
        $this->assertSame('normal', $target->visits()->sole()->examination->bmi_status);
        $this->get($url.'/kunjungan')->assertInertia(fn (Assert $page) => $page->where('visits.data.0.examination.calculation_details.mode', 'manual')->where(
            'visits.data.0.examination.bmi_status',
            'normal'
        ));
    }
}
