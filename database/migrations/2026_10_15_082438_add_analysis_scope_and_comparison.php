<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analysis_runs', function (Blueprint $table) {
            $table->string('report_scope', 30)->default('operational');
            $table->foreignId('comparison_period_id')
                ->nullable()
                ->constrained('reporting_periods')
                ->restrictOnDelete();
            $table->unsignedInteger('comparison_revision')->nullable();
            $table->string('comparison_hash', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('analysis_runs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('comparison_period_id');
            $table->dropColumn(['report_scope', 'comparison_revision', 'comparison_hash']);
        });
    }
};
