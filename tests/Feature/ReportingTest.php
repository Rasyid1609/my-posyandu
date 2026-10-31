<?php

namespace Tests\Feature;

use App\Models\ReportingPeriod;
use App\Models\Resident;
use App\Services\OperationalReport;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReportingTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_zero_denominator_returns_null(): void
    {
        $period = ReportingPeriod::factory()->create();

        $metrics = app(OperationalReport::class)->calculate($period);

        $this->assertNull($metrics['attendance_coverage']);
        $this->assertSame(0, $metrics['targets']);
    }

    public function test_repeated_visits_do_not_double_count_people_and_partial_measurements_are_not_normal(): void
    {
        $period = ReportingPeriod::factory()->create();
        for ($person = 0; $person < 10; $person++) {
            $resident = Resident::factory()->create();
            $target = $period->targets()->create([
                'resident_id' => $resident->id,
                'category_code' => 'general',
                'attendance_status' => 'present',
            ]);
            $visit = $period->visits()->create([
                'target_membership_id' => $target->id,
                'resident_id' => $resident->id,
                'visited_at' => '2026-01-15 02:00:00',
                'record_status' => 'finalized',
            ]);
            $visit->examination()->create([
                'category_code' => 'general',
                'glucose_state' => $person < 6 ? 'measured' : 'not_examined',
                'glucose_mg_dl' => $person < 6 ? 90 : null,
            ]);
            if ($person === 0) {
                $repeat = $period->visits()->create([
                    'target_membership_id' => $target->id,
                    'resident_id' => $resident->id,
                    'visited_at' => '2026-01-16 02:00:00',
                    'record_status' => 'finalized',
                ]);
                $repeat->examination()->create([
                    'category_code' => 'general',
                    'glucose_state' => 'measured',
                    'glucose_mg_dl' => 95,
                ]);
            }
        }

        $metrics = app(OperationalReport::class)->calculate($period);

        $this->assertSame(10, $metrics['present']);
        $this->assertSame(11, $metrics['finalized_visits']);
        $this->assertSame(6, $metrics['blood_sugar_measured']);
        $this->assertSame(100.0, $metrics['attendance_coverage']);
    }

    public function test_unconfirmed_attendance_and_draft_visits_block_submission(): void
    {
        $this->actingAs($this->createSuperadmin());
        $period = ReportingPeriod::factory()->create();
        $period->targets()->create([
            'resident_id' => Resident::factory()->create()->id,
            'category_code' => 'general',
        ]);

        $this->patch(
            '/rekapitulasi/'.$period->id,
            ['revision' => 1, 'action' => 'submit', 'reason' => 'Pengajuan belum lengkap']
        )->assertSessionHasErrors('period');

        $this->assertSame('open', $period->fresh()->status);
        $this->assertSame(1, $period->fresh()->revision);
        $this->assertDatabaseCount('report_snapshots', 0);
    }

    public function test_locked_and_submitted_periods_reject_direct_source_mutations(): void
    {
        $this->actingAs($this->createSuperadmin());
        $period = ReportingPeriod::factory()->create(['status' => 'locked']);
        $resident = Resident::factory()->create();
        $target = $period->targets()->create(['resident_id' => $resident->id, 'category_code' => 'general']);
        $visit = $period->visits()->create([
            'target_membership_id' => $target->id,
            'resident_id' => $resident->id,
            'visited_at' => '2026-01-15 02:00:00',
        ]);
        $url = '/sasaran/'.$period->id;

        $this->post($url.'/anggota', [
            'revision' => 1,
            'resident_id' => $resident->id,
            'reason' => 'Percobaan ubah terkunci',
        ])->assertSessionHasErrors('period');
        $this->patch($url.'/anggota/'.$target->id, [
            'revision' => 1,
            'attendance_status' => 'absent',
            'reason' => 'Percobaan ubah terkunci',
        ])->assertSessionHasErrors('period');
        $this->delete(
            $url.'/anggota/'.$target->id,
            ['revision' => 1, 'reason' => 'Percobaan hapus terkunci']
        )->assertSessionHasErrors('period');
        $this->post($url.'/anggota/'.$target->id.'/kunjungan', $this->visitPayload(1))->assertSessionHasErrors('period');
        $this->put($url.'/anggota/'.$target->id.'/kunjungan/'.$visit->id, $this->visitPayload(1))->assertSessionHasErrors('period');
        $this->post($url.'/kunjungan/'.$visit->id.'/finalisasi', ['revision' => 1])->assertSessionHasErrors('period');

        $this->assertSame(1, $period->fresh()->revision);
        $this->assertDatabaseCount('visits', 1);
        $this->assertSame('draft', $visit->fresh()->record_status);
    }

    public function test_snapshot_survives_birthday_archiving_and_reopening_with_new_version(): void
    {
        $this->actingAs($this->createSuperadmin());
        $period = ReportingPeriod::factory()->create(['status' => 'submitted']);
        $resident = Resident::factory()->create(['birth_date' => '1966-01-15']);
        $period->targets()->create([
            'resident_id' => $resident->id,
            'category_code' => 'general',
            'attendance_status' => 'absent',
            'age_in_years_snapshot' => 60,
        ]);
        $this->patch(
            '/rekapitulasi/'.$period->id,
            ['revision' => 1, 'action' => 'approve', 'reason' => 'Penguncian versi pertama']
        )->assertSessionHasNoErrors();
        $first = $period->snapshots()->sole();
        $resident->update(['residency_status' => 'inactive', 'birth_date' => '1960-01-15']);

        $this->get('/rekapitulasi?period_id='.$period->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('metrics.targets', 1)->where('metrics.absent', 1));
        $this->patch('/rekapitulasi/'.$period->id, [
            'revision' => $period->fresh()->revision,
            'action' => 'reopen',
            'reason' => 'Peninjauan administratif ulang',
        ])->assertSessionHasNoErrors();
        $this->patch('/rekapitulasi/'.$period->id, [
            'revision' => $period->fresh()->revision,
            'action' => 'submit',
            'reason' => 'Pengajuan versi berikutnya',
        ])->assertSessionHasNoErrors();
        $this->patch('/rekapitulasi/'.$period->id, [
            'revision' => $period->fresh()->revision,
            'action' => 'approve',
            'reason' => 'Penguncian versi berikutnya',
        ])->assertSessionHasNoErrors();

        $this->assertSame($first->metrics, $first->fresh()->metrics);
        $this->assertSame($first->source_hash, $first->fresh()->source_hash);
        $this->assertSame(60, $period->targets()->sole()->age_in_years_snapshot);
        $this->assertSame(2, $period->snapshots()->count());
    }

    public function test_stale_period_revision_returns_409_without_adding_target(): void
    {
        $this->actingAs($this->createSuperadmin());
        $period = ReportingPeriod::factory()->create(['revision' => 3]);

        $this->post('/sasaran/'.$period->id.'/anggota', [
            'revision' => 2,
            'resident_id' => Resident::factory()->create()->id,
            'reason' => 'Formulir revisi lama',
        ])->assertConflict();

        $this->assertDatabaseCount('target_memberships', 0);
        $this->assertSame(3, $period->fresh()->revision);
    }

    public function test_snapshot_cannot_be_overwritten_through_model_update(): void
    {
        $chair = $this->createSuperadmin();
        $period = ReportingPeriod::factory()->create();
        $snapshot = $period->snapshots()->create([
            'version' => 1,
            'source_revision' => 1,
            'mapping_version' => OperationalReport::VERSION,
            'metrics' => ['targets' => 1],
            'source_hash' => str_repeat('a', 64),
            'approved_by' => $chair->id,
            'created_at' => now(),
        ]);

        try {
            $snapshot->update(['metrics' => ['targets' => 999]]);
            $this->fail('Snapshot harus immutable.');
        } catch (\LogicException $exception) {
            $this->assertSame(
                'Snapshot terkunci tidak dapat diubah. Buat versi laporan baru.',
                $exception->getMessage()
            );
        }

        $this->assertSame(['targets' => 1], $snapshot->fresh()->metrics);
    }
}
