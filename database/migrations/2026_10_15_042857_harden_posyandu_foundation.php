<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true);
        });
        Schema::table('posyandu_profiles', function (Blueprint $table) {
            foreach (['rt', 'rw'] as $column) {
                $table->string($column, 10)->nullable()->default(null)->change();
            }
            foreach (['hamlet', 'village', 'subdistrict', 'city'] as $column) {
                $table->string($column)->nullable()->default(null)->change();
            }
        });
        foreach (['households', 'residents'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('rt', 10)->nullable()->default(null)->change();
                $table->string('rw', 10)->nullable()->default(null)->change();
            });
        }
        Schema::table('residents', function (Blueprint $table) {
            $table->date('birth_date')->nullable()->change();
            $table->unsignedInteger('revision')->default(1);
            $table->index(['residency_status', 'full_name']);
        });
        Schema::create('resident_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resident_id')->constrained()->restrictOnDelete();
            $table->string('status', 20);
            $table->date('effective_date');
            $table->text('reason');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::table('reporting_periods', function (Blueprint $table) {
            $table->unsignedInteger('target_households_count')->nullable()->default(null)->change();
            $table->unsignedInteger('revision')->default(1);
        });
        Schema::table('target_memberships', function (Blueprint $table) {
            $table->unsignedInteger('age_in_months_snapshot')->nullable()->change();
            $table->unsignedSmallInteger('age_in_years_snapshot')->nullable()->change();
            $table->text('selection_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
        });
        Schema::table('visits', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->index(['reporting_period_id', 'record_status']);
        });
        Schema::table('examinations', function (Blueprint $table) {
            foreach (['weight', 'height', 'blood_pressure', 'glucose'] as $measurement) {
                $table->string($measurement.'_state', 20)->default('not_examined');
            }
            $table->decimal('systolic_mmhg', 6, 2)->nullable();
            $table->decimal('diastolic_mmhg', 6, 2)->nullable();
            $table->decimal('glucose_mg_dl', 7, 2)->nullable();
        });
        Schema::create('report_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporting_period_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->unsignedInteger('source_revision');
            $table->string('mapping_version', 40);
            $table->json('metrics');
            $table->string('source_hash', 64);
            $table->foreignId('approved_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at');
            $table->unique(['reporting_period_id', 'version']);
        });
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('action', 80);
            $table->string('resource_type', 80);
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('created_at');
            $table->index(['resource_type', 'resource_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('report_snapshots');
        Schema::dropIfExists('resident_status_histories');
        Schema::table('examinations', function (Blueprint $table) {
            $table->dropColumn([
                'weight_state',
                'height_state',
                'blood_pressure_state',
                'glucose_state',
                'systolic_mmhg',
                'diastolic_mmhg',
                'glucose_mg_dl',
            ]);
        });
        Schema::table('visits', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropForeign(['finalized_by']);
            $table->dropIndex(['reporting_period_id', 'record_status']);
            $table->dropColumn(['created_by', 'finalized_by', 'finalized_at']);
        });
        Schema::table('target_memberships', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn(['selection_reason', 'created_by']);
        });
        Schema::table('reporting_periods', fn (Blueprint $table) => $table->dropColumn('revision'));
        Schema::table('residents', function (Blueprint $table) {
            $table->dropIndex(['residency_status', 'full_name']);
            $table->dropColumn('revision');
        });
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_active'));
    }
};
