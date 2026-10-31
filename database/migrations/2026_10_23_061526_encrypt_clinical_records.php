<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Crypt::encryptString('clinical-encryption-preflight');

        $examinationFields = [
            'weight_kg',
            'height_cm',
            'systolic_mmhg',
            'diastolic_mmhg',
            'glucose_mg_dl',
            'head_cm',
            'arm_cm',
            'waist_cm',

            'weight_state',
            'height_state',
            'blood_pressure_state',
            'glucose_state',
            'head_state',
            'arm_state',
            'waist_state',

            'assessment_source',
            'assessor_name',
            'received_education',
            'is_referred',

            'bmi_status',
            'bp_status',
            'blood_sugar_status',
            'anemia_status',
            'tb_symptom_status',
            'heeadsss_status',

            'mental_health_result',
            'puma_result',
            'dependency_result',
            'skilas_cognition',
            'skilas_mobility',
            'skilas_nutrition',

            'skilas_hearing',
            'skilas_vision',
            'skilas_depression',
            'development_check',
            'weight_trend',
            'weight_age_result',

            'height_age_result',
            'weight_height_result',
            'head_result',
            'arm_result',
            'exclusive_breastfeeding',

            'complementary_feeding',
            'immunization',
            'vitamin_a',
            'deworming',
            'local_food',
            'child_illness',

            'maternal_weight',
            'maternal_arm',
            'maternal_pressure',
            'ttd_received',
            'ttd_daily',
            'pmt_received',
            'pmt_daily',

            'pregnancy_class',
            'postpartum_vitamin',
            'postpartum_contraception',
            'education_result',
            'referral_result',
        ];

        Schema::table('examinations', function (Blueprint $table): void {
            foreach (['received_education', 'is_referred'] as $field) {
                $table->boolean($field)->default(null)->change();
            }
        });

        $this->encryptColumns('examinations', $examinationFields, [
            'weight_kg',
            'height_cm',
            'systolic_mmhg',
            'diastolic_mmhg',
            'glucose_mg_dl',
            'head_cm',
            'arm_cm',
            'waist_cm',

            'assessment_source',
            'assessor_name',
        ], ['received_education', 'is_referred']);
        $this->encryptColumns('referrals', [
            'destination',
            'reason',
            'follow_up_date',
            'follow_up_status',
            'follow_up_note',
        ], ['follow_up_date', 'follow_up_note']);
        $this->encryptColumns('home_visits', ['reason', 'follow_up'], ['follow_up']);
        $this->encryptColumns(
            'home_visit_residents',
            ['no_service', 'danger_sign', 'tb_symptom', 'irregular_medication']
        );

        DB::table('sessions')->delete();
    }

    /**
     * @param  list<string>  $fields
     * @param  list<string>  $nullable
     * @param  list<string>  $booleans
     */
    private function encryptColumns(string $name, array $fields, array $nullable = [], array $booleans = []): void
    {
        Schema::table($name, function (Blueprint $table) use ($fields, $nullable): void {
            foreach ($fields as $field) {
                $table->text($field)->nullable(in_array($field, $nullable, true))->default(null)->change();
            }
        });

        DB::table($name)->select(['id', ...$fields])->orderBy('id')->chunkById(100, function ($rows) use ($name, $fields, $booleans): void {
            foreach ($rows as $row) {
                $encrypted = [];
                foreach ($fields as $field) {
                    $value = $row->{$field};
                    if ($value !== null && in_array($field, $booleans, true)) {
                        $value = json_encode(in_array($value, [true, 1, '1', 'true', 't'], true), JSON_THROW_ON_ERROR);
                    }
                    $encrypted[$field] = $value === null ? null : Crypt::encryptString((string) $value);
                }
                DB::table($name)->where('id', $row->id)->update($encrypted);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new RuntimeException('Enkripsi data kesehatan tidak dibalik ke teks terbuka. Gunakan forward fix atau pemulihan backup terverifikasi.');
    }
};
