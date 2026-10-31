<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posyandu_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('Posyandu Jeruk');
            $table->string('rt', 10)->default('45');
            $table->string('rw', 10)->default('15');
            $table->string('hamlet')->nullable()->default('Hambangan Kidul');
            $table->string('village')->default('Sidodadi');
            $table->string('subdistrict')->default('Manguharjo');
            $table->string('city')->default('Madiun');
            $table->timestamps();
        });

        Schema::create('households', function (Blueprint $table) {
            $table->id();
            $table->string('family_card_number', 32)->nullable()->index();
            $table->string('head_of_household_name');
            $table->text('address')->nullable();
            $table->string('rt', 10)->default('45');
            $table->string('rw', 10)->default('15');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('residents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->nullable()->constrained()->nullOnDelete();
            $table->string('registration_number', 40)->unique();
            $table->string('national_id_number', 32)->nullable()->index();
            $table->string('full_name');
            $table->enum('gender', ['L', 'P']);
            $table->date('birth_date');
            $table->boolean('is_birth_date_estimated')->default(false);
            $table->string('family_relationship', 30)->nullable();
            $table->string('mother_name')->nullable();
            $table->string('phone_number', 30)->nullable();
            $table->text('address')->nullable();
            $table->string('rt', 10)->default('45');
            $table->string('rw', 10)->default('15');
            $table->enum('residency_status', ['active', 'moved', 'deceased', 'inactive'])->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('maternal_episodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resident_id')->constrained()->cascadeOnDelete();
            $table->enum('episode_type', ['pregnant', 'postpartum', 'breastfeeding']);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('reporting_periods', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('month');
            $table->unsignedSmallInteger('year');
            $table->date('reference_date');
            $table->enum('status', ['open', 'submitted', 'returned', 'locked'])->default('open');
            $table->unsignedInteger('target_households_count')->default(65);
            $table->timestamps();
            $table->unique(['month', 'year']);
        });

        Schema::create('target_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporting_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resident_id')->constrained()->restrictOnDelete();
            $table->string('category_code', 30);
            $table->unsignedInteger('age_in_months_snapshot');
            $table->unsignedSmallInteger('age_in_years_snapshot');
            $table->boolean('is_within_target')->default(true);
            $table->boolean('is_excluded')->default(false);
            $table->enum('attendance_status', ['unconfirmed', 'present', 'absent'])->default('unconfirmed');
            $table->timestamps();
            $table->unique(['reporting_period_id', 'resident_id', 'category_code'], 'uniq_period_res_cat');
        });

        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporting_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('target_membership_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resident_id')->constrained()->restrictOnDelete();
            $table->dateTime('visited_at');
            $table->enum('record_status', ['draft', 'finalized'])->default('draft');
            $table->timestamps();
        });

        Schema::create('examinations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('category_code', 30)->index();
            $table->decimal('weight_kg', 5, 2)->nullable();
            $table->decimal('height_cm', 5, 2)->nullable();
            $table->string('bmi_status', 30)->default('not_examined');
            $table->string('bp_status', 30)->default('not_examined');
            $table->string('blood_sugar_status', 30)->default('not_examined');
            $table->string('anemia_status', 30)->default('not_examined');
            $table->string('tb_symptom_status', 30)->default('not_examined');
            $table->string('heeadsss_status', 30)->default('not_examined');
            $table->boolean('received_education')->default(false);
            $table->boolean('is_referred')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('examinations');
        Schema::dropIfExists('visits');
        Schema::dropIfExists('target_memberships');
        Schema::dropIfExists('reporting_periods');
        Schema::dropIfExists('maternal_episodes');
        Schema::dropIfExists('residents');
        Schema::dropIfExists('households');
        Schema::dropIfExists('posyandu_profiles');
    }
};
