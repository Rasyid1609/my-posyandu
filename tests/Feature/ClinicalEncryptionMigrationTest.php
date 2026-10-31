<?php

namespace Tests\Feature;

use App\Models\Examination;
use App\Models\HomeVisit;
use App\Models\Household;
use App\Models\Referral;
use App\Models\ReportingPeriod;
use App\Models\Resident;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class ClinicalEncryptionMigrationTest extends TestCase
{
    private function legacyDatabase(): void
    {
        config([
            'database.default' => 'clinical_migration',
            'database.connections.clinical_migration' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);
        Schema::clearResolvedInstance('db.schema');
        foreach (glob(database_path('migrations/*.php')) as $path) {
            if (basename($path) < '2026_10_23_061526_encrypt_clinical_records.php') {
                (require $path)->up();
            }
        }
    }

    public function test_existing_health_data_is_encrypted_without_changing_values_links_or_revision(): void
    {
        $this->legacyDatabase();
        $user = User::factory()->create();
        $period = ReportingPeriod::factory()->create(['revision' => 7]);
        $household = Household::create(['head_of_household_name' => 'DEMO Keluarga']);
        $resident = Resident::factory()->create(['household_id' => $household->id]);
        $target = $period->targets()->create(['resident_id' => $resident->id, 'category_code' => 'adult']);
        $visit = $period->visits()->create([
            'resident_id' => $resident->id,
            'target_membership_id' => $target->id,
            'visited_at' => '2026-01-15 02:00:00',
            'record_status' => 'finalized',
        ]);
        $examId = DB::table('examinations')->insertGetId([
            'visit_id' => $visit->id,
            'category_code' => 'adult',
            'weight_kg' => 56.25,
            'glucose_mg_dl' => 91.25,

            'head_cm' => 50.5,
            'weight_state' => 'measured',
            'height_state' => 'unknown',
            'glucose_state' => 'measured',

            'blood_pressure_state' => 'declined',
            'mental_health_result' => 'risk',
            'tb_symptom_status' => 'no',

            'assessment_source' => 'Sumber sintetis — utuh',
            'assessor_name' => 'DEMO Petugas',

            'received_education' => true,
            'is_referred' => false,
            'updated_at' => '2026-01-15 02:00:00',
        ]);
        $referralId = DB::table('referrals')->insertGetId([
            'visit_id' => $visit->id,
            'destination' => 'DEMO Puskesmas',
            'reason' => 'Alasan sintetis',
            'follow_up_date' => '2026-01-20',
            'follow_up_note' => null,
        ]);
        $homeId = DB::table('home_visits')->insertGetId([
            'reporting_period_id' => $period->id,
            'household_id' => $household->id,
            'visited_on' => '2026-01-15',
            'reason' => 'Catatan rumah sintetis',
            'follow_up' => 'Tindak lanjut sintetis',
            'created_by' => $user->id,
        ]);
        DB::table('home_visit_residents')->insert([
            'home_visit_id' => $homeId,
            'resident_id' => $resident->id,
            'category_snapshot' => 'adult',
            'danger_sign' => 'yes',
            'tb_symptom' => 'no',
        ]);
        DB::table('sessions')->insert([
            'id' => 'legacy-session',
            'user_id' => $user->id,
            'payload' => base64_encode('{"old_input":"data sintetis"}'),
            'last_activity' => 100,
        ]);

        (require database_path('migrations/2026_10_23_061526_encrypt_clinical_records.php'))->up();

        $exam = Examination::findOrFail($examId);
        $this->assertSame('56.25', $exam->weight_kg);
        $this->assertSame('91.25', $exam->glucose_mg_dl);
        $this->assertSame('50.50', $exam->head_cm);
        $this->assertNull($exam->height_cm);
        $this->assertSame('unknown', $exam->height_state);
        $this->assertSame('declined', $exam->blood_pressure_state);
        $this->assertSame('risk', Crypt::decryptString($exam->getRawOriginal('mental_health_result')));
        $this->assertSame('Sumber sintetis — utuh', $exam->assessment_source);
        $this->assertSame('DEMO Petugas', $exam->assessor_name);
        $this->assertTrue($exam->received_education);
        $this->assertFalse($exam->is_referred);
        $this->assertSame('not_examined', $exam->skilas_depression);
        $this->assertSame('2026-01-15 02:00:00', $exam->getRawOriginal('updated_at'));
        $this->assertSame($visit->id, $exam->visit_id);
        $this->assertSame('finalized', $visit->fresh()->record_status);
        $this->assertSame(7, $period->fresh()->revision);
        $referral = Referral::findOrFail($referralId);
        $this->assertSame('Alasan sintetis', Crypt::decryptString($referral->getRawOriginal('reason')));
        $this->assertSame('2026-01-20', $referral->follow_up_date);
        $this->assertSame('pending', $referral->follow_up_status);
        $this->assertNull($referral->follow_up_note);
        $home = HomeVisit::findOrFail($homeId);
        $this->assertSame('Catatan rumah sintetis', Crypt::decryptString($home->getRawOriginal('reason')));
        $this->assertSame('Tindak lanjut sintetis', $home->follow_up);
        $pivot = DB::table('home_visit_residents')->sole();
        $this->assertSame('yes', Crypt::decryptString($pivot->danger_sign));
        $this->assertSame('no', $home->residents()->sole()->pivot->tb_symptom);
        $this->assertSame('unknown', $home->residents()->sole()->pivot->no_service);
        $this->assertDatabaseCount('sessions', 0);
    }

    public function test_rollback_refuses_to_restore_health_data_as_plaintext(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('tidak dibalik ke teks terbuka');

        (require database_path('migrations/2026_10_23_061526_encrypt_clinical_records.php'))->down();
    }
}
