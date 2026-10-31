<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('age_rule_versions', function (Blueprint $table) {
            $table->id();
            $table->string('version', 60)->unique();
            $table->string('source', 1000);
            $table->boolean('is_provisional')->default(true);
            $table->json('boundaries');
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        DB::table('age_rule_versions')->insert([
            'version' => 'temporary-2026-v1',

            'source' => 'Konfigurasi operasional sementara atas permintaan pengguna; bukan SOP klinis Dinkes.',

            'is_provisional' => true,

            'boundaries' => json_encode([12, 60, 72, 180, 228, 720]),

            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Schema::table('reporting_periods', function (Blueprint $table) {
            $table->foreignId('age_rule_version_id')->nullable()->constrained()->restrictOnDelete();
        });
        Schema::table('target_memberships', function (Blueprint $table) {
            $table->string('gender_snapshot', 1)->nullable();
            foreach (['pregnant', 'postpartum', 'breastfeeding'] as $type) {
                $table->boolean('is_'.$type.'_snapshot')->default(false);
            }
        });
        Schema::table('households', function (Blueprint $table) {
            $table->unsignedInteger('revision')->default(1);
        });
        Schema::table('residents', function (Blueprint $table) {
            $table->foreignId('guardian_id')->nullable()->constrained('residents')->restrictOnDelete();
        });
        Schema::table('maternal_episodes', function (Blueprint $table) {
            $table->string('source', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
        });
        Schema::table('examinations', function (Blueprint $table) {
            foreach (['head_cm', 'arm_cm', 'waist_cm'] as $field) {
                $table->decimal($field, 6, 2)->nullable();
            }
            foreach (['head', 'arm', 'waist'] as $field) {
                $table->string($field.'_state', 20)->default('not_examined');
            }
            $table->string('assessment_source', 500)->nullable();
            $table->string('assessor_name', 150)->nullable();
            foreach ([
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
            ] as $field) {
                $table->string($field, 30)->default('not_examined');
            }
        });
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->unique()->constrained()->restrictOnDelete();
            $table->string('destination', 200);
            $table->string('reason', 1000);
            $table->date('follow_up_date')->nullable();
            $table->string('follow_up_status', 30)->default('pending');
            $table->string('follow_up_note', 1000)->nullable();
            $table->timestamps();
        });
        Schema::create('visit_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained()->restrictOnDelete();
            $table->text('previous_record');
            $table->string('reason', 500);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at');
        });
        Schema::create('household_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporting_period_id')->constrained()->restrictOnDelete();
            $table->foreignId('household_id')->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->unique(['reporting_period_id', 'household_id']);
        });
        Schema::create('home_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporting_period_id')->constrained()->restrictOnDelete();
            $table->foreignId('household_id')->constrained()->restrictOnDelete();
            $table->date('visited_on');
            $table->string('reason', 500);
            $table->string('follow_up', 1000)->nullable();
            $table->string('record_status', 20)->default('draft');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();
        });
        Schema::create('home_visit_residents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('home_visit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resident_id')->constrained()->restrictOnDelete();
            $table->string('category_snapshot', 30);
            foreach (['pregnant', 'postpartum', 'breastfeeding'] as $type) {
                $table->boolean('is_'.$type.'_snapshot')->default(false);
            }
            foreach (['no_service', 'danger_sign', 'tb_symptom', 'irregular_medication'] as $field) {
                $table->string($field, 20)->default('unknown');
            }
            $table->unique(['home_visit_id', 'resident_id']);
        });
        Schema::create('home_visit_staff', function (Blueprint $table) {
            $table->foreignId('home_visit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->primary(['home_visit_id', 'user_id']);
        });
    }

    public function down(): void
    {
        foreach ([
            'home_visit_staff',
            'home_visit_residents',
            'home_visits',
            'household_targets',
            'visit_corrections',
            'referrals',
        ] as $name) {
            Schema::dropIfExists($name);
        }
        Schema::table('examinations', function (Blueprint $table) {
            $table->dropColumn([
                'head_cm',
                'arm_cm',
                'waist_cm',
                'head_state',
                'arm_state',
                'waist_state',
                'assessment_source',
                'assessor_name',

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
            ]);
        });
        Schema::table('maternal_episodes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn('source');
        });
        Schema::table('residents', fn (Blueprint $table) => $table->dropConstrainedForeignId('guardian_id'));
        Schema::table('households', fn (Blueprint $table) => $table->dropColumn('revision'));
        Schema::table('target_memberships', fn (Blueprint $table) => $table->dropColumn([
            'gender_snapshot',
            'is_pregnant_snapshot',
            'is_postpartum_snapshot',
            'is_breastfeeding_snapshot',
        ]));
        Schema::table(
            'reporting_periods',
            fn (Blueprint $table) => $table->dropConstrainedForeignId('age_rule_version_id')
        );
        Schema::dropIfExists('age_rule_versions');
    }
};
