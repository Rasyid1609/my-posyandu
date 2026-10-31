<?php

namespace Tests\Feature;

use App\Models\ReportingPeriod;
use App\Models\Resident;
use App\Models\Visit;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CoreWorkflowTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_login_resident_target_measurement_and_review_work_end_to_end(): void
    {
        $user = $this->createSuperadmin();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirectToRoute('residents.index');
        $this->assertAuthenticatedAs($user);
        $this->post('/masyarakat', $this->residentPayload())
            ->assertSessionHasNoErrors()
            ->assertRedirectToRoute('residents.index');
        $resident = Resident::sole();
        $this->post('/sasaran', ['month' => 1, 'year' => 2026])->assertSessionHasNoErrors();
        $period = ReportingPeriod::sole();
        $this->post('/sasaran/'.$period->id.'/anggota', [
            'resident_id' => $resident->id,
            'revision' => $period->revision,
            'reason' => 'Sasaran kegiatan demo',
        ])->assertSessionHasNoErrors();
        $target = $period->targets()->sole();
        $this->assertSame('unconfirmed', $target->attendance_status);
        $this->post(
            '/sasaran/'.$period->id.'/anggota/'.$target->id.'/kunjungan',
            $this->visitPayload(
                $period->fresh()->revision,
                ['weight_state' => 'measured', 'weight_kg' => 50.25]
            )
        )->assertSessionHasNoErrors();
        $visit = Visit::sole();
        $this->assertSame('draft', $visit->record_status);
        $this->assertSame('50.25', $visit->examination->weight_kg);
        $this->post(
            '/sasaran/'.$period->id.'/kunjungan/'.$visit->id.'/finalisasi',
            ['revision' => $period->fresh()->revision]
        )->assertSessionHasNoErrors();
        $this->patch('/rekapitulasi/'.$period->id, [
            'action' => 'submit',
            'revision' => $period->fresh()->revision,
            'reason' => 'Kelengkapan sudah diperiksa',
        ])->assertSessionHasNoErrors();
        $this->patch('/rekapitulasi/'.$period->id, [
            'action' => 'approve',
            'revision' => $period->fresh()->revision,
            'reason' => 'Disetujui administratif demo',
        ])->assertSessionHasNoErrors();

        $this->assertSame('locked', $period->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'report.approve', 'resource_id' => $period->id]);
        $this->get('/rekapitulasi?period_id='.$period->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Reports/Index')
                ->where('metrics.present', 1)
                ->where('metrics.finalized_visits', 1)
                ->where('metrics.attendance_coverage', 100)
                ->has('snapshots', 1));
    }

    public function test_missing_birth_date_is_not_fabricated_and_identity_is_single(): void
    {
        $this->actingAs($this->createSuperadmin());

        $this->post('/masyarakat', $this->residentPayload(['birth_date' => null]))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('residents', ['full_name' => 'DEMO Warga Uji', 'birth_date' => null]);
        $this->assertDatabaseCount('residents', 1);
    }

    public function test_duplicate_identity_is_reported_without_creating_second_resident(): void
    {
        $this->actingAs($this->createSuperadmin());
        Resident::factory()->create(['full_name' => 'DEMO Warga Uji', 'gender' => 'P']);

        $this->post('/masyarakat', $this->residentPayload(['full_name' => 'demo warga uji']))->assertSessionHasErrors(['full_name' => 'Kemungkinan warga duplikat. Periksa nama, tanggal lahir, dan jenis kelamin pada data yang ada.']);

        $this->assertDatabaseCount('residents', 1);
    }

    public function test_stale_resident_edit_returns_409_without_overwriting(): void
    {
        $this->actingAs($this->createSuperadmin());
        $resident = Resident::factory()->create(['revision' => 2]);

        $this->put(
            '/masyarakat/'.$resident->id,
            $this->residentPayload(['full_name' => 'Perubahan basi', 'revision' => 1])
        )->assertConflict();

        $this->assertSame(2, $resident->fresh()->revision);
        $this->assertDatabaseMissing('residents', ['full_name' => 'Perubahan basi']);
    }

    public function test_archiving_keeps_identity_targets_and_visits(): void
    {
        $this->actingAs($this->createSuperadmin());
        $resident = Resident::factory()->create();
        $period = ReportingPeriod::factory()->create();
        $target = $period->targets()->create([
            'resident_id' => $resident->id,
            'category_code' => 'general',
            'attendance_status' => 'present',
        ]);
        $period->visits()->create([
            'target_membership_id' => $target->id,
            'resident_id' => $resident->id,
            'visited_at' => '2026-01-15 02:00:00',
        ]);

        $this->put('/masyarakat/'.$resident->id, $this->residentPayload([
            'full_name' => $resident->full_name,
            'gender' => $resident->gender,
            'residency_status' => 'inactive',
            'revision' => 1,
        ]))->assertSessionHasNoErrors();

        $this->assertDatabaseCount('residents', 1);
        $this->assertDatabaseCount('visits', 1);
        $this->assertDatabaseCount('target_memberships', 1);
        $this->assertDatabaseHas(
            'resident_status_histories',
            ['resident_id' => $resident->id, 'status' => 'inactive']
        );
    }

    public function test_reselecting_target_does_not_duplicate_or_refresh_snapshot(): void
    {
        $this->actingAs($this->createSuperadmin());
        $resident = Resident::factory()->create(['birth_date' => '2000-02-01']);
        $period = ReportingPeriod::factory()->create();

        $this->post('/sasaran/'.$period->id.'/anggota', [
            'resident_id' => $resident->id,
            'revision' => 1,
            'reason' => 'Pemilihan sasaran awal',
        ])->assertSessionHasNoErrors();
        $this->post('/sasaran/'.$period->id.'/anggota', [
            'resident_id' => $resident->id,
            'revision' => $period->fresh()->revision,
            'reason' => 'Pemilihan sasaran ulang',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('target_memberships', 1);
        $this->assertSame(25, $period->targets()->sole()->age_in_years_snapshot);
    }

    public function test_measurement_status_requires_value_and_rejects_zero_or_hidden_values(): void
    {
        $this->actingAs($this->createSuperadmin());
        $period = ReportingPeriod::factory()->create();
        $target = $period->targets()->create([
            'resident_id' => Resident::factory()->create()->id,
            'category_code' => 'general',
        ]);
        $url = '/sasaran/'.$period->id.'/anggota/'.$target->id.'/kunjungan';

        $this->post($url, $this->visitPayload(1, ['glucose_state' => 'measured']))->assertSessionHasErrors(['glucose_mg_dl' => 'Nilai wajib diisi saat pemeriksaan berstatus diperiksa.']);
        $this->post($url, $this->visitPayload(1, ['glucose_state' => 'measured', 'glucose_mg_dl' => 0]))->assertSessionHasErrors('glucose_mg_dl');
        $this->post($url, $this->visitPayload(1, ['glucose_state' => 'declined', 'glucose_mg_dl' => 90]))->assertSessionHasErrors(['glucose_mg_dl' => 'Kosongkan nilai jika pemeriksaan tidak berstatus diperiksa.']);

        $this->assertDatabaseCount('visits', 0);
        $this->assertSame(1, $period->fresh()->revision);
    }

    public function test_not_examined_unknown_declined_and_not_applicable_preserve_null_values(): void
    {
        $this->actingAs($this->createSuperadmin());
        $period = ReportingPeriod::factory()->create();
        $target = $period->targets()->create([
            'resident_id' => Resident::factory()->create()->id,
            'category_code' => 'general',
        ]);

        $this->post('/sasaran/'.$period->id.'/anggota/'.$target->id.'/kunjungan', $this->visitPayload(1, [
            'height_state' => 'unknown',
            'glucose_state' => 'declined',
            'blood_pressure_state' => 'not_applicable',
        ]))->assertSessionHasNoErrors();

        $exam = $target->visits()->sole()->examination;
        $this->assertSame('not_examined', $exam->weight_state);
        $this->assertNull($exam->weight_kg);
        $this->assertSame('unknown', $exam->height_state);
        $this->assertNull($exam->height_cm);
        $this->assertSame('declined', $exam->glucose_state);
        $this->assertNull($exam->glucose_mg_dl);
        $this->assertSame('not_applicable', $exam->blood_pressure_state);
    }

    public function test_draft_can_be_corrected_but_finalized_measurement_cannot_be_edited(): void
    {
        $this->actingAs($this->createSuperadmin());
        $period = ReportingPeriod::factory()->create();
        $target = $period->targets()->create([
            'resident_id' => Resident::factory()->create()->id,
            'category_code' => 'general',
        ]);
        $url = '/sasaran/'.$period->id.'/anggota/'.$target->id.'/kunjungan';
        $this->post($url, $this->visitPayload(1, ['weight_state' => 'measured', 'weight_kg' => 50]))->assertSessionHasNoErrors();
        $visit = Visit::sole();

        $this->put(
            $url.'/'.$visit->id,
            $this->visitPayload($period->fresh()->revision, ['weight_state' => 'measured', 'weight_kg' => 51])
        )->assertSessionHasNoErrors();
        $this->assertDatabaseCount('visits', 1);
        $this->assertSame('51.00', $visit->examination()->sole()->weight_kg);
        $this->post(
            '/sasaran/'.$period->id.'/kunjungan/'.$visit->id.'/finalisasi',
            ['revision' => $period->fresh()->revision]
        )->assertSessionHasNoErrors();
        $this->put(
            $url.'/'.$visit->id,
            $this->visitPayload($period->fresh()->revision, ['weight_state' => 'measured', 'weight_kg' => 99])
        )->assertSessionHasErrors('period');

        $this->assertSame('51.00', $visit->examination()->sole()->weight_kg);
    }

    public function test_nested_target_from_another_period_returns_404(): void
    {
        $this->actingAs($this->createSuperadmin());
        $period = ReportingPeriod::factory()->create();
        $other = ReportingPeriod::factory()->create(['month' => 2, 'reference_date' => '2026-02-28']);
        $target = $other->targets()->create([
            'resident_id' => Resident::factory()->create()->id,
            'category_code' => 'general',
        ]);

        $this->post('/sasaran/'.$period->id.'/anggota/'.$target->id.'/kunjungan', $this->visitPayload(1))->assertNotFound();

        $this->assertDatabaseCount('visits', 0);
    }

    public function test_calendar_dates_use_jakarta_day_across_utc_midnight(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-14 18:00:00', 'UTC'));
        $this->actingAs($this->createSuperadmin());

        $this->post(
            '/masyarakat',
            $this->residentPayload(['birth_date' => '2026-10-15', 'effective_date' => '2026-10-15'])
        )->assertSessionHasNoErrors();

        $this->assertDatabaseHas('residents', ['birth_date' => '2026-10-15']);
    }

    public function test_visit_outside_selected_period_is_rejected_without_changing_attendance(): void
    {
        $this->actingAs($this->createSuperadmin());
        $period = ReportingPeriod::factory()->create();
        $target = $period->targets()->create([
            'resident_id' => Resident::factory()->create()->id,
            'category_code' => 'general',
        ]);

        $this->post(
            '/sasaran/'.$period->id.'/anggota/'.$target->id.'/kunjungan',
            $this->visitPayload(1, ['visited_at' => '2026-02-01T00:00'])
        )->assertSessionHasErrors('visited_at');

        $this->assertDatabaseCount('visits', 0);
        $this->assertSame('unconfirmed', $target->fresh()->attendance_status);
    }
}
