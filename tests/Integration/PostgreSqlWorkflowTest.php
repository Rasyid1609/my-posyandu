<?php

namespace Tests\Integration;

use App\Models\Household;
use App\Models\ReportingPeriod;
use App\Models\Resident;
use App\Services\IdentityDetails;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PostgreSqlWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    public function test_postgresql_optional_identity_is_encrypted_and_duplicate_nik_is_rejected(): void
    {
        $nik = '0000000000000001';
        $kk = '0000000000000002';
        if (Resident::withTrashed()
            ->where('national_id_lookup_hash', IdentityDetails::lookup('national_id', $nik))
            ->exists()
            || Household::withTrashed()
                ->where('family_card_lookup_hash', IdentityDetails::lookup('family_card', $kk))
                ->exists()) {
            $this->markTestSkipped('Identitas fixture telah digunakan; tidak mengubah data existing.');
        }
        $this->actingAs($this->createSuperadmin());
        $name = 'DEMO Identitas PostgreSQL '.uniqid();
        $this->post('/masyarakat', $this->residentPayload([
            'full_name' => $name,
            'national_id_number' => $nik,
            'phone_number' => '081234567890',
        ]))->assertSessionHasNoErrors();
        $resident = Resident::where('full_name', $name)->sole();
        $this->assertSame($nik, Crypt::decryptString($resident->getRawOriginal('national_id_ciphertext')));
        $this->assertSame('081234567890', $resident->phone_ciphertext);
        $this->assertNull($resident->national_id_number);
        $this->post(
            '/masyarakat',
            $this->residentPayload(['full_name' => $name.' Duplikat', 'national_id_number' => $nik])
        )->assertSessionHasErrors('national_id_number');
        $this->post('/masyarakat/keluarga', [
            'head_of_household_name' => $name,
            'is_active' => true,
            'family_card_number' => $kk,
        ])->assertSessionHasNoErrors();
        $family = Household::where('head_of_household_name', $name)->sole();
        $this->assertSame($kk, Crypt::decryptString($family->getRawOriginal('family_card_ciphertext')));
        $this->assertNull($family->family_card_number);
    }

    public function test_postgresql_roles_inherit_active_matrix_without_direct_identity_grant(): void
    {
        $staff = $this->createStaff('bendahara');

        $this->assertTrue($staff->can('aggregates.view'));
        $this->assertFalse($staff->can('residents.view'));
        $this->assertFalse($staff->can('reports.approve'));
        $this->assertSame(0, $staff->permissions()->count());
        $this->assertSame(11, Role::findByName('sekretaris', 'web')->permissions()->count());
        $this->assertSame(6, Role::findByName('admin', 'web')->permissions()->count());
    }

    public function test_postgresql_automatic_calculation_is_encrypted_and_requires_confirmation(): void
    {
        if (ReportingPeriod::where('year', 2001)->where('month', 1)->exists()) {
            $this->markTestSkipped('Periode fixture telah digunakan; tidak mengubah data existing.');
        }
        $this->actingAs($user = $this->createSuperadmin());
        $resident = Resident::factory()->create(['birth_date' => '1960-01-01', 'gender' => 'L']);
        $this->post('/sasaran', ['year' => 2001, 'month' => 1])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $period = ReportingPeriod::where('year', 2001)->where('month', 1)->sole();
        $this->post('/sasaran/'.$period->id.'/anggota', [
            'resident_id' => $resident->id,
            'revision' => 1,
            'reason' => 'Fixture hitung PostgreSQL sintetis',
        ])->assertSessionHasNoErrors();
        $target = $period->targets()->sole();

        $this->post('/sasaran/'.$period->id.'/anggota/'.$target->id.'/kunjungan', $this->visitPayload($period->fresh()->revision, [
            'assessment_mode' => 'automatic',
            'visited_at' => '2001-01-15T09:00',

            'weight_state' => 'measured',
            'weight_kg' => 60,
            'height_state' => 'measured',
            'height_cm' => 160,
            'bmi_status' => 'normal',
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $visit = $period->visits()->sole();
        $this->assertSame('overweight', $visit->examination->bmi_status);
        $details = json_decode(
            Crypt::decryptString($visit->examination->getRawOriginal('calculation_details')),
            true,
            flags: JSON_THROW_ON_ERROR
        );
        $this->assertSame(23.44, $details['results']['bmi']['value']);
        $this->assertSame('clinical-calculation-v1', $details['version']);
        $url = '/sasaran/'.$period->id.'/kunjungan/'.$visit->id.'/finalisasi';
        $this->post($url, ['revision' => $period->fresh()->revision])->assertSessionHasErrors('calculation_reviewed');
        $this->post($url, ['revision' => $period->fresh()->revision, 'calculation_reviewed' => true])->assertSessionHasNoErrors();
        $this->assertSame('finalized', $visit->fresh()->record_status);
        $this->assertSame($user->id, $visit->fresh()->examination->calculation_details['reviewed_by']);
    }

    public function test_development_postgresql_persists_workflow_inside_rolled_back_transaction(): void
    {
        $this->assertSame('pgsql', DB::connection()->getDriverName());
        if (ReportingPeriod::where('year', 2000)->where('month', 1)->exists()) {
            $this->markTestSkipped('Periode fixture telah digunakan; tidak mengubah data existing.');
        }
        $this->actingAs($this->createSuperadmin());
        $this->post('/masyarakat', $this->residentPayload(['full_name' => 'DEMO PostgreSQL '.uniqid()]))->assertSessionHasNoErrors();
        $resident = Resident::latest('id')->firstOrFail();
        $this->post('/sasaran', ['month' => 1, 'year' => 2000])->assertSessionHasNoErrors();
        $period = ReportingPeriod::where('year', 2000)->where('month', 1)->sole();
        $this->post('/sasaran/'.$period->id.'/anggota', [
            'revision' => 1,
            'resident_id' => $resident->id,
            'reason' => 'Fixture integrasi PostgreSQL',
        ])->assertSessionHasNoErrors();
        $target = $period->targets()->sole();
        $this->post('/sasaran/'.$period->id.'/anggota/'.$target->id.'/kunjungan', $this->visitPayload($period->fresh()->revision, [
            'visited_at' => '2000-01-15T09:00',
            'glucose_state' => 'measured',
            'glucose_mg_dl' => 91.25,
        ]))->assertSessionHasNoErrors();
        $visit = $period->visits()->sole();
        $this->post(
            '/sasaran/'.$period->id.'/kunjungan/'.$visit->id.'/finalisasi',
            ['revision' => $period->fresh()->revision]
        )->assertSessionHasNoErrors();
        $this->patch('/rekapitulasi/'.$period->id, [
            'revision' => $period->fresh()->revision,
            'action' => 'submit',
            'reason' => 'Pengajuan integrasi PostgreSQL',
        ])->assertSessionHasNoErrors();
        $this->patch('/rekapitulasi/'.$period->id, [
            'revision' => $period->fresh()->revision,
            'action' => 'approve',
            'reason' => 'Persetujuan integrasi PostgreSQL',
        ])->assertSessionHasNoErrors();

        $snapshot = $period->snapshots()->sole();
        $this->assertSame(1, $snapshot->metrics['present']);
        $this->assertSame(1, $snapshot->metrics['blood_sugar_measured']);
        $exam = $visit->examination()->sole();
        $this->assertSame('91.25', $exam->glucose_mg_dl);
        $this->assertNotSame('91.25', $exam->getRawOriginal('glucose_mg_dl'));
        $this->assertSame('91.25', Crypt::decryptString($exam->getRawOriginal('glucose_mg_dl')));
        $this->assertSame('measured', Crypt::decryptString($exam->getRawOriginal('glucose_state')));
        $this->assertSame('locked', $period->fresh()->status);
    }
}
