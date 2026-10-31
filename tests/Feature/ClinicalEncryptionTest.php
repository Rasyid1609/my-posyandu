<?php

namespace Tests\Feature;

use App\Models\Examination;
use App\Models\Household;
use App\Models\ReportingPeriod;
use App\Models\Resident;
use App\Services\OperationalReport;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ClinicalEncryptionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_measurements_screening_and_referrals_are_encrypted_without_changing_authorized_responses(): void
    {
        $chair = $this->createSuperadmin();
        $chair->givePermissionTo(['sensitive.view', 'sensitive.record']);
        $this->actingAs($chair);
        $period = ReportingPeriod::factory()->create();
        $target = $period->targets()->create(['resident_id' => Resident::factory()->create()->id, 'category_code' => 'adult']);
        $url = '/sasaran/'.$period->id.'/anggota/'.$target->id.'/kunjungan';
        $this->post($url, $this->visitPayload(1, [
            'weight_state' => 'measured',
            'weight_kg' => 56.25,
            'height_state' => 'measured',
            'height_cm' => 160,

            'blood_pressure_state' => 'measured',
            'systolic_mmhg' => 120,
            'diastolic_mmhg' => 80,

            'glucose_state' => 'measured',
            'glucose_mg_dl' => 91.25,

            'head_state' => 'measured',
            'head_cm' => 50.5,
            'arm_state' => 'measured',
            'arm_cm' => 25.25,

            'waist_state' => 'measured',
            'waist_cm' => 76.5,
            'mental_health_result' => 'risk',
            'blood_sugar_status' => 'normal',

            'assessment_source' => 'Catatan sintetis — pemeriksaan terverifikasi',
            'assessor_name' => 'DEMO Petugas',

            'referral_result' => 'yes',
            'referral_destination' => 'DEMO Puskesmas',
            'referral_reason' => 'Tindak lanjut sintetis',

            'follow_up_date' => '2026-01-20',
            'follow_up_status' => 'scheduled',
            'follow_up_note' => 'Catatan rujukan sintetis',
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $visit = $target->visits()->sole();
        $exam = $visit->examination;
        foreach ([
            'weight_kg' => '56.25',
            'height_cm' => '160.00',
            'systolic_mmhg' => '120.00',
            'diastolic_mmhg' => '80.00',
            'glucose_mg_dl' => '91.25',
            'head_cm' => '50.50',
            'arm_cm' => '25.25',
            'waist_cm' => '76.50',
        ] as $field => $value) {
            $this->assertSame($value, $exam->{$field});
            $this->assertSame($value, Crypt::decryptString($exam->getRawOriginal($field)));
        }
        foreach ([
            'mental_health_result' => 'risk',
            'blood_sugar_status' => 'normal',
            'weight_state' => 'measured',
            'assessment_source' => 'Catatan sintetis — pemeriksaan terverifikasi',
            'assessor_name' => 'DEMO Petugas',
        ] as $field => $value) {
            $this->assertNotSame($value, $exam->getRawOriginal($field));
            $this->assertSame($value, Crypt::decryptString($exam->getRawOriginal($field)));
        }
        $this->assertFalse($exam->received_education);
        $this->assertFalse($exam->is_referred);
        foreach ([
            'destination' => 'DEMO Puskesmas',
            'reason' => 'Tindak lanjut sintetis',
            'follow_up_date' => '2026-01-20',
            'follow_up_status' => 'scheduled',
            'follow_up_note' => 'Catatan rujukan sintetis',
        ] as $field => $value) {
            $this->assertNotSame($value, $visit->referral->getRawOriginal($field));
            $this->assertSame($value, $visit->referral->{$field});
        }
        $this->get($url)->assertInertia(fn (Assert $page) => $page
            ->where('visits.data.0.examination.weight_kg', '56.25')
            ->where('visits.data.0.examination.mental_health_result', 'risk')
            ->where('visits.data.0.referral.reason', 'Tindak lanjut sintetis'));
        $this->actingAs($this->createStaff());
        $this->get($url)->assertInertia(fn (Assert $page) => $page
            ->where('visits.data.0.examination.weight_kg', '56.25')
            ->missing('visits.data.0.examination.mental_health_result'));
    }

    public function test_reports_count_unique_finalized_people_including_unknown_age_after_encryption(): void
    {
        $this->actingAs($this->createSuperadmin());
        $period = ReportingPeriod::factory()->create();
        $target = $period->targets()->create([
            'resident_id' => Resident::factory()->create(['birth_date' => null])->id,
            'category_code' => 'general',
        ]);
        $url = '/sasaran/'.$period->id.'/anggota/'.$target->id.'/kunjungan';
        for ($index = 0; $index < 2; $index++) {
            $this->post($url, $this->visitPayload($period->fresh()->revision, [
                'weight_state' => 'measured',
                'weight_kg' => 56,
                'glucose_state' => 'measured',
                'glucose_mg_dl' => 91.25,
            ]))->assertSessionHasNoErrors();
            $visit = $target->visits()->latest('id')->first();
            $this->post(
                '/sasaran/'.$period->id.'/kunjungan/'.$visit->id.'/finalisasi',
                ['revision' => $period->fresh()->revision]
            )->assertSessionHasNoErrors();
        }
        $draft = $period->targets()->create(['resident_id' => Resident::factory()->create()->id, 'category_code' => 'adult']);
        $this->post(
            '/sasaran/'.$period->id.'/anggota/'.$draft->id.'/kunjungan',
            $this->visitPayload($period->fresh()->revision, ['weight_state' => 'measured', 'weight_kg' => 60])
        )->assertSessionHasNoErrors();

        $metrics = app(OperationalReport::class)->calculate($period);

        $this->assertSame(1, $metrics['weight_measured']);
        $this->assertSame(1, $metrics['blood_sugar_measured']);
        $this->assertSame(2, $metrics['finalized_visits']);
        $this->assertSame(1, $metrics['draft_visits']);
        $target->forceFill(['is_excluded' => true])->save();
        $excluded = app(OperationalReport::class)->calculate($period);
        $this->assertSame(0, $excluded['weight_measured']);
        $this->assertSame(0, $excluded['blood_sugar_measured']);
    }

    public function test_home_visit_notes_and_pivot_results_stay_encrypted_when_a_draft_is_updated(): void
    {
        $this->actingAs($user = $this->createSuperadmin());
        $period = ReportingPeriod::factory()->create();
        $family = Household::create(['head_of_household_name' => 'DEMO Keluarga', 'is_active' => true]);
        $resident = Resident::factory()->create(['household_id' => $family->id]);
        $payload = [
            'revision' => 1,
            'household_id' => $family->id,
            'visited_on' => '2026-01-15',
            'reason' => 'Catatan rumah sintetis',
            'follow_up' => 'Tindak lanjut sintetis',
            'staff_ids' => [$user->id],

            'residents' => [[
                'resident_id' => $resident->id,
                'no_service' => 'no',
                'danger_sign' => 'yes',
                'tb_symptom' => 'unknown',
                'irregular_medication' => 'not_applicable',
            ]],
        ];
        $url = '/sasaran/'.$period->id.'/rumah';
        $this->post($url, $payload)->assertRedirect()->assertSessionHasNoErrors();
        $home = $period->homeVisits()->sole();
        $this->assertSame('Catatan rumah sintetis', Crypt::decryptString($home->getRawOriginal('reason')));
        $this->assertSame(
            'Tindak lanjut sintetis',
            Crypt::decryptString($home->getRawOriginal('follow_up'))
        );
        $pivot = DB::table('home_visit_residents')->sole();
        foreach ([
            'no_service' => 'no',
            'danger_sign' => 'yes',
            'tb_symptom' => 'unknown',
            'irregular_medication' => 'not_applicable',
        ] as $field => $value) {
            $this->assertSame($value, Crypt::decryptString($pivot->{$field}));
            $this->assertSame($value, $home->residents()->sole()->pivot->{$field});
        }
        $payload['revision'] = $period->fresh()->revision;
        $payload['reason'] = 'Catatan rumah diperbarui';
        $payload['residents'][0]['danger_sign'] = 'no';
        $this->put($url.'/'.$home->id, $payload)->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseCount('home_visit_residents', 1);
        $this->assertSame('Catatan rumah diperbarui', $home->fresh()->reason);
        $this->assertSame(
            'no',
            Crypt::decryptString(DB::table('home_visit_residents')->sole()->danger_sign)
        );
        $this->get($url)->assertInertia(fn (Assert $page) => $page
            ->where('visits.data.0.reason', 'Catatan rumah diperbarui')
            ->where('visits.data.0.residents.0.pivot.danger_sign', 'no'));
    }

    public function test_corrupt_ciphertext_cannot_be_read_as_a_plaintext_health_result(): void
    {
        $this->actingAs($this->createSuperadmin());
        $period = ReportingPeriod::factory()->create();
        $target = $period->targets()->create([
            'resident_id' => Resident::factory()->create()->id,
            'category_code' => 'general',
        ]);
        $this->post('/sasaran/'.$period->id.'/anggota/'.$target->id.'/kunjungan', $this->visitPayload(1))->assertSessionHasNoErrors();
        $exam = Examination::sole();
        DB::table('examinations')->where('id', $exam->id)->update(['mental_health_result' => 'normal']);

        $this->expectException(DecryptException::class);

        $exam->fresh()->mental_health_result;
    }
}
