<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class IdentityMigrationTest extends TestCase
{
    private function legacyDatabase(): void
    {
        config([
            'database.default' => 'identity_migration',
            'database.connections.identity_migration' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);
        Schema::clearResolvedInstance('db.schema');
        Schema::create('residents', function (Blueprint $table) {
            $table->id();
            $table->string('national_id_number', 32)->nullable();
            $table->string('phone_number', 30)->nullable();
        });
        Schema::create('households', function (Blueprint $table) {
            $table->id();
            $table->string('family_card_number', 32)->nullable();
        });
    }

    public function test_existing_plaintext_is_preserved_as_encrypted_values_and_cleared_from_legacy_columns(): void
    {
        $this->legacyDatabase();
        DB::table('residents')->insert(['national_id_number' => '0123456789012345', 'phone_number' => '081234567890']);
        DB::table('households')->insert(['family_card_number' => '0123456789098765']);
        $migration = require database_path('migrations/2026_10_16_020449_add_protected_identity_details.php');
        $migration->up();
        $resident = DB::table('residents')->first();
        $household = DB::table('households')->first();
        $this->assertSame('0123456789012345', Crypt::decryptString($resident->national_id_ciphertext));
        $this->assertSame('081234567890', Crypt::decryptString($resident->phone_ciphertext));
        $this->assertSame('0123456789098765', Crypt::decryptString($household->family_card_ciphertext));
        $this->assertNull($resident->national_id_number);
        $this->assertNull($resident->phone_number);
        $this->assertNull($household->family_card_number);
        $this->assertNotSame($resident->national_id_ciphertext, $resident->national_id_lookup_hash);
        $this->assertSame(64, strlen($household->family_card_lookup_hash));
    }

    public function test_duplicate_legacy_identity_stops_before_any_schema_or_data_change(): void
    {
        $this->legacyDatabase();
        DB::table('residents')->insert([
            ['national_id_number' => '0123456789012345'],
            ['national_id_number' => '0123456789012345'],
        ]);
        $migration = require database_path('migrations/2026_10_16_020449_add_protected_identity_details.php');
        try {
            $migration->up();
            $this->fail('Duplikat legacy harus menghentikan migrasi.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('duplikat', $exception->getMessage());
            $this->assertStringNotContainsString('0123456789012345', $exception->getMessage());
        }
        $this->assertFalse(Schema::hasColumn('residents', 'national_id_ciphertext'));
        $this->assertSame(
            2,
            DB::table('residents')->where('national_id_number', '0123456789012345')->count()
        );
    }
}
