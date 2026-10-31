<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(AccessControlSeeder::class);
        if (! DB::table('posyandu_profiles')->exists()) {
            DB::table('posyandu_profiles')->insert(['name' => 'Posyandu Jeruk', 'created_at' => now(), 'updated_at' => now()]);
        }
    }
}
