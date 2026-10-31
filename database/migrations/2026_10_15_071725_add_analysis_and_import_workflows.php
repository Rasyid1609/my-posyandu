<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analysis_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporting_period_id')->constrained()->restrictOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->string('deduplication_key', 64)->unique();
            $table->unsignedInteger('source_revision');
            $table->string('source_hash', 64);
            $table->string('provider', 30);
            $table->string('model', 100);
            $table->string('prompt_version', 40);
            $table->string('schema_version', 40);
            $table->string('status', 30)->default('queued');
            $table->boolean('is_stale')->default(false);
            $table->json('evidence');
            $table->json('result')->nullable();
            $table->json('usage')->nullable();
            $table->string('failure_code', 60)->nullable();
            $table->string('review_status', 30)->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('review_note', 1000)->nullable();
            $table->json('reviewed_result')->nullable();
            $table->timestamps();
        });
        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporting_period_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('kind', 30);
            $table->string('report_type', 30)->nullable();
            $table->string('status', 30)->default('draft');
            $table->string('checksum', 64);
            $table->string('source_name', 150);
            $table->string('file_path')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->text('draft_rows');
            $table->string('ocr_status', 30)->default('not_requested');
            $table->string('failure_code', 60)->nullable();
            $table->unsignedInteger('revision')->default(1);
            $table->foreignId('committed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('committed_at')->nullable();
            $table->timestamps();
            $table->unique(['reporting_period_id', 'kind', 'checksum']);
        });
        Schema::create('import_resident_links', function (Blueprint $table) {
            $table->foreignId('import_batch_id')->constrained()->restrictOnDelete();
            $table->foreignId('resident_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('row_number');
            $table->primary(['import_batch_id', 'resident_id']);
        });
        Schema::create('legacy_report_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporting_period_id')->constrained()->restrictOnDelete();
            $table->foreignId('import_batch_id')->constrained()->restrictOnDelete();
            $table->string('report_type', 30);
            $table->string('metric_code', 100);
            $table->unsignedInteger('value')->nullable();
            $table->string('source_cell', 150)->nullable();
            $table->timestamps();
            $table->unique(['import_batch_id', 'metric_code']);
        });
        Schema::create('report_source_selections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporting_period_id')->constrained()->restrictOnDelete();
            $table->string('report_type', 30);
            $table->foreignId('import_batch_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('reason', 500);
            $table->foreignId('selected_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['reporting_period_id', 'report_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_source_selections');
        Schema::dropIfExists('legacy_report_values');
        Schema::dropIfExists('import_resident_links');
        Schema::dropIfExists('import_batches');
        Schema::dropIfExists('analysis_runs');
    }
};
