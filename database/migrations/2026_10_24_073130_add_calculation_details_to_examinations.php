<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('examinations', function (Blueprint $table) {
            Schema::table('examinations', function (Blueprint $table): void {
                $table->text('calculation_details')->nullable();
            });
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('examinations', function (Blueprint $table) {
            throw new RuntimeException('Riwayat hasil hitung tidak dihapus otomatis. Gunakan forward fix atau pemulihan backup terverifikasi.');
        });
    }
};
