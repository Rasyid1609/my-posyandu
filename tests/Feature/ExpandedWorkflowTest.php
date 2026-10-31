<?php

namespace Tests\Feature;

use App\Models\Household;
use App\Models\ReportingPeriod;
use App\Models\Resident;
use App\Models\Visit;
use App\Services\AgeGrouping;
use App\Services\GroupedReport;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ExpandedWorkflowTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_new_maternal_episode_can_be_applied_explicitly_only_to_open_target_snapshot(): void
    {
        $this->actingAs($this->createSuperadmin());
        $resident = Resident::factory()->create(['gender' => 'P']);
        $period = ReportingPeriod::factory()->create();
        $this->post('/sasaran/'.$period->id.'/generate', ['revision' => 1])->assertRedirect();
        $target = $period->targets()->sole();
        $this->assertFalse((bool) $target->is_pregnant_snapshot);
        $this->post('/masyarakat/'.$resident->id.'/episode', [
            'revision' => 1,
            'episode_type' => 'pregnant',
            'start_date' => '2026-01-05',
            'source' => 'Catatan bidan sintetis',
        ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertFalse((bool) $target->fresh()->is_pregnant_snapshot);
        $this->post('/sasaran/'.$period->id.'/anggota/'.$target->id.'/snapshot', [
            'revision' => $period->fresh()->revision,
            'reason' => 'Terapkan episode yang baru dicatat',
        ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertTrue((bool) $target->fresh()->is_pregnant_snapshot);
        $this->assertSame('unconfirmed', $target->fresh()->attendance_status);
        $period->update(['status' => 'locked']);
        $this->post('/sasaran/'.$period->id.'/anggota/'.$target->id.'/snapshot', [
            'revision' => $period->fresh()->revision,
            'reason' => 'Percobaan pembaruan terkunci',
        ])->assertSessionHasErrors('period');
    }

    public static function birthdays(): array
    {
        return [
            ['2025-02-01', 'infant'],
            ['2025-01-31', 'toddler'],
            ['2021-02-01', 'toddler'],
            ['2021-01-31', 'preschool'],

            ['2020-01-31', 'school'],
            ['2011-01-31', 'adolescent'],
            ['2007-01-31', 'adult'],
            ['1966-01-31', 'elderly'],
        ];
    }

    #[DataProvider('birthdays')]
    public function test_age_boundaries_do_not_overlap(string $birth, string $expected): void
    {
        $period = ReportingPeriod::factory()->create();
        $resident = Resident::factory()->create(['birth_date' => $birth]);
        $this->assertSame($expected, app(AgeGrouping::class)->snapshot($resident, $period)['category_code']);
    }

    public function test_rule_change_applies_only_to_new_periods_and_generate_does_not_duplicate(): void
    {
        $this->actingAs($this->createSuperadmin());
        Resident::factory()->create(['birth_date' => '2007-01-01']);
        $period = ReportingPeriod::factory()->create();
        $this->post('/sasaran/'.$period->id.'/generate', ['revision' => 1])
            ->assertSessionHasNoErrors()
            ->assertRedirect();
        $this->post('/pengaturan/aturan', [
            'version' => 'local-v2',
            'source' => 'Keputusan pengelola untuk uji sintetis',
            'is_provisional' => true,
            'boundaries' => [12, 60, 72, 180, 240, 720],
        ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();
        $this->post('/sasaran/'.$period->id.'/generate', ['revision' => $period->fresh()->revision])
            ->assertSessionHasNoErrors()
            ->assertRedirect();
        $this->assertSame('adult', $period->targets()->sole()->category_code);
        $this->assertDatabaseCount('target_memberships', 1);
        $new = ReportingPeriod::factory()->create(['month' => 2, 'reference_date' => '2026-02-28']);
        $this->post('/sasaran/'.$new->id.'/generate', ['revision' => 1])
            ->assertSessionHasNoErrors()
            ->assertRedirect();
        $this->assertSame('adolescent', $new->targets()->sole()->category_code);
        $this->assertNotSame($period->age_rule_version_id, $new->age_rule_version_id);
    }

    public function test_invalid_boundaries_and_unauthorized_settings_are_rejected(): void
    {
        $this->actingAs($this->createStaff());
        $this->post('/pengaturan/aturan', [])->assertForbidden();
        $this->actingAs($this->createSuperadmin());
        $this->post('/pengaturan/aturan', [
            'version' => 'invalid',
            'source' => 'Sumber konfigurasi sementara',
            'is_provisional' => true,
            'boundaries' => [12, 60, 60, 180, 228, 720],
        ])->assertSessionHasErrors('boundaries');
        $this->assertDatabaseCount('age_rule_versions', 1);
    }

    public function test_maternal_snapshot_is_not_changed_by_closing_episode_and_population_stays_unique(): void
    {
        $this->actingAs($this->createSuperadmin());
        $resident = Resident::factory()->create(['gender' => 'P', 'birth_date' => '1990-01-01']);
        $this->post('/masyarakat/'.$resident->id.'/episode', [
            'revision' => 1,
            'episode_type' => 'pregnant',
            'start_date' => '2026-01-01',
            'source' => 'Catatan bidan sintetis',
        ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();
        $period = ReportingPeriod::factory()->create();
        $this->post('/sasaran/'.$period->id.'/generate', ['revision' => 1])
            ->assertSessionHasNoErrors()
            ->assertRedirect();
        $episodeId = DB::table('maternal_episodes')->value('id');
        $this->patch(
            '/masyarakat/'.$resident->id.'/episode/'.$episodeId,
            ['revision' => $resident->fresh()->revision, 'end_date' => '2026-01-20']
        )
            ->assertSessionHasNoErrors()
            ->assertRedirect();
        $this->assertDatabaseCount('residents', 1);
        $this->assertTrue((bool) $period->targets()->sole()->is_pregnant_snapshot);
        $groups = app(GroupedReport::class)->calculate($period);
        $this->assertSame(1, $groups['maternal']['rows'][0]['value']);
        $this->assertSame(1, $groups['adults']['rows'][0]['value']);
    }

    public function test_manual_assessment_needs_provenance_and_sensitive_results_require_extra_grants(): void
    {
        $this->actingAs($this->createStaff());
        [$period, $target] = $this->adultTarget();
        $url = '/sasaran/'.$period->id.'/anggota/'.$target->id.'/kunjungan';
        $this->post($url, $this->visitPayload(1, ['blood_sugar_status' => 'normal']))->assertSessionHasErrors(['assessor_name', 'assessment_source']);
        $this->post($url, $this->visitPayload(1, [
            'mental_health_result' => 'normal',
            'assessor_name' => 'DEMO Petugas',
            'assessment_source' => 'Dokumen sintetis',
        ]))->assertSessionHasErrors('mental_health_result');
        $this->post($url, $this->visitPayload(1, [
            'blood_sugar_status' => 'normal',
            'assessor_name' => 'DEMO Petugas',
            'assessment_source' => 'Dokumen sintetis',
        ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();
        $exam = $target->visits()->sole()->examination;
        $this->assertSame('normal', $exam->blood_sugar_status);
        $this->assertSame('not_examined', $exam->mental_health_result);
        $this->get($url)->assertInertia(fn (Assert $page) => $page->missing('visits.data.0.examination.mental_health_result')->missing('clinicalFields.mental_health_result'));
    }

    public function test_repeated_results_use_last_known_field_and_denominator_is_examined_people(): void
    {
        $this->actingAs($this->createSuperadmin());
        [$period, $target] = $this->adultTarget();
        $url = '/sasaran/'.$period->id.'/anggota/'.$target->id.'/kunjungan';
        foreach (['low', 'normal'] as $index => $result) {
            $this->post($url, $this->visitPayload($period->fresh()->revision, [
                'visited_at' => '2026-01-'.(15 + $index).'T09:00',
                'blood_sugar_status' => $result,
                'assessor_name' => 'DEMO Petugas',
                'assessment_source' => 'Dokumen sintetis',
            ]))
                ->assertSessionHasNoErrors()
                ->assertRedirect();
            $visit = Visit::latest('id')->first();
            $this->post(
                '/sasaran/'.$period->id.'/kunjungan/'.$visit->id.'/finalisasi',
                ['revision' => $period->fresh()->revision]
            )
                ->assertSessionHasNoErrors()
                ->assertRedirect();
        }
        $rows = collect(app(GroupedReport::class)->calculate($period)['adults']['rows'])->keyBy('code');
        $this->assertSame(1, $rows['blood_sugar_status.normal']['value']);
        $this->assertSame(0, $rows['blood_sugar_status.low']['value']);
        $this->assertSame(1, $rows['blood_sugar_status.normal']['denominator']);
    }

    public function test_correction_preserves_encrypted_original_and_locked_period_refuses_correction(): void
    {
        $this->actingAs($this->createSuperadmin());
        [$period, $target] = $this->adultTarget();
        $this->post(
            '/sasaran/'.$period->id.'/anggota/'.$target->id.'/kunjungan',
            $this->visitPayload(1, ['weight_state' => 'measured', 'weight_kg' => 56])
        )
            ->assertSessionHasNoErrors()
            ->assertRedirect();
        $visit = Visit::sole();
        $this->post(
            '/sasaran/'.$period->id.'/kunjungan/'.$visit->id.'/finalisasi',
            ['revision' => $period->fresh()->revision]
        )
            ->assertSessionHasNoErrors()
            ->assertRedirect();
        $this->post('/sasaran/'.$period->id.'/kunjungan/'.$visit->id.'/koreksi', [
            'revision' => $period->fresh()->revision,
            'reason' => 'Koreksi satuan pengukuran',
        ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();
        $this->assertSame('draft', $visit->fresh()->record_status);
        $encrypted = DB::table('visit_corrections')->value('previous_record');
        $this->assertStringNotContainsString('weight_kg', $encrypted);
        $this->assertStringContainsString('56', Crypt::decryptString($encrypted));
        $period->update(['status' => 'locked']);
        $this->post('/sasaran/'.$period->id.'/kunjungan/'.$visit->id.'/koreksi', [
            'revision' => $period->fresh()->revision,
            'reason' => 'Percobaan koreksi terkunci',
        ])->assertSessionHasErrors('period');
        $this->assertDatabaseCount('visit_corrections', 1);
    }

    public function test_home_visits_count_family_people_events_and_staff_separately_and_guard_parentage(): void
    {
        $this->actingAs($user = $this->createSuperadmin());
        $family = Household::create(['head_of_household_name' => 'DEMO Keluarga', 'is_active' => true]);
        $people = Resident::factory()->count(2)->create(['household_id' => $family->id]);
        $outside = Resident::factory()->create();
        $period = ReportingPeriod::factory()->create();
        $payload = [
            'revision' => 1,
            'household_id' => $family->id,
            'visited_on' => '2026-01-15',
            'reason' => 'Kunjungan rumah sintetis',
            'staff_ids' => [$user->id],

            'residents' => $people->map(fn ($p) => [
                'resident_id' => $p->id,
                'no_service' => 'no',
                'danger_sign' => 'unknown',
                'tb_symptom' => 'no',
                'irregular_medication' => 'not_applicable',
            ])->all(),
        ];
        $bad = $payload;
        $bad['residents'][0]['resident_id'] = $outside->id;
        $this->post('/sasaran/'.$period->id.'/rumah', $bad)->assertSessionHasErrors('residents');
        for ($i = 0; $i < 2; $i++) {
            $payload['revision'] = $period->fresh()->revision;
            $this->post('/sasaran/'.$period->id.'/rumah', $payload)
                ->assertSessionHasNoErrors()
                ->assertRedirect();
            $home = $period->homeVisits()->latest('id')->first();
            $this->post(
                '/sasaran/'.$period->id.'/rumah/'.$home->id.'/finalisasi',
                ['revision' => $period->fresh()->revision]
            )
                ->assertSessionHasNoErrors()
                ->assertRedirect();
        }
        $rows = collect(app(GroupedReport::class)->calculate($period)['home']['rows'])->keyBy('code');
        $this->assertSame(1, $rows['visited_households']['value']);
        $this->assertSame(2, $rows['visited_residents']['value']);
        $this->assertSame(2, $rows['home_visits']['value']);
        $this->assertDatabaseCount('home_visit_staff', 2);
        $period->update(['status' => 'locked']);
        $payload['revision'] = $period->fresh()->revision;
        $this->post('/sasaran/'.$period->id.'/rumah', $payload)->assertSessionHasErrors('period');
        $this->assertDatabaseCount('home_visits', 2);
    }

    private function adultTarget(): array
    {
        $period = ReportingPeriod::factory()->create();
        $resident = Resident::factory()->create(['birth_date' => '1990-01-01']);
        $target = $period->targets()->create([
            'resident_id' => $resident->id,
            'category_code' => 'adult',
            'gender_snapshot' => 'P',
            'attendance_status' => 'present',
        ]);

        return [$period, $target];
    }
}
