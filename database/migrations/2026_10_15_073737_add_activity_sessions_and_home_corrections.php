<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporting_period_id')->constrained()->restrictOnDelete();
            $table->string('name', 150);
            $table->date('scheduled_on');
            $table->string('location', 300)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::table(
            'visits',
            fn (Blueprint $table) => $table->foreignId('activity_session_id')->nullable()->constrained()->restrictOnDelete()
        );
        Schema::create('home_visit_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('home_visit_id')->constrained()->restrictOnDelete();
            $table->text('previous_record');
            $table->string('reason', 500);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_visit_corrections');
        Schema::table(
            'visits',
            fn (Blueprint $table) => $table->dropConstrainedForeignId('activity_session_id')
        );
        Schema::dropIfExists('activity_sessions');
    }
};
