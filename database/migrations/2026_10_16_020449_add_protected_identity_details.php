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
        foreach (['residents' => 'national_id_number', 'households' => 'family_card_number'] as $table => $column) {
            if (DB::table($table)
                ->select($column)
                ->whereNotNull($column)
                ->where($column, '!=', '')
                ->groupBy($column)
                ->havingRaw('COUNT(*) > 1')
                ->exists()) {
                throw new RuntimeException('Identitas legacy duplikat ditemukan pada '.$table.'. Periksa secara privat sebelum migrasi; tidak ada data yang dihapus.');
            }
        }
        Schema::table('residents', function (Blueprint $table) {
            $table->text('national_id_ciphertext')->nullable();
            $table->text('phone_ciphertext')->nullable();
            $table->char('national_id_lookup_hash', 64)->nullable()->unique();
        });
        Schema::table('households', function (Blueprint $table) {
            $table->text('family_card_ciphertext')->nullable();
            $table->char('family_card_lookup_hash', 64)->nullable()->unique();
        });
        DB::table('residents')->orderBy('id')->chunkById(100, function ($rows) {
            foreach ($rows as $row) {
                $nik = $row->national_id_number ?: null;
                $phone = $row->phone_number ?: null;
                DB::table('residents')->where('id', $row->id)->update([
                    'national_id_ciphertext' => $nik === null ? null : Crypt::encryptString($nik),

                    'phone_ciphertext' => $phone === null ? null : Crypt::encryptString($phone),

                    'national_id_lookup_hash' => $nik === null ? null : hash_hmac('sha256', 'national_id|'.$nik, config('app.key')),

                    'national_id_number' => null,
                    'phone_number' => null,
                ]);
            }
        });
        DB::table('households')->orderBy('id')->chunkById(100, function ($rows) {
            foreach ($rows as $row) {
                $kk = $row->family_card_number ?: null;
                DB::table('households')->where('id', $row->id)->update([
                    'family_card_ciphertext' => $kk === null ? null : Crypt::encryptString($kk),
                    'family_card_lookup_hash' => $kk === null ? null : hash_hmac('sha256', 'family_card|'.$kk, config('app.key')),
                    'family_card_number' => null,
                ]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new RuntimeException('Migrasi identitas terenkripsi tidak dibalik otomatis. Gunakan forward fix atau pemulihan backup terverifikasi.');
    }
};
