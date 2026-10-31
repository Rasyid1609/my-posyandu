<?php

namespace Database\Seeders;

use App\Models\Resident;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Seed demo hanya untuk lingkungan local/testing.');
        }
        $this->call(DatabaseSeeder::class);
        $user = User::firstOrCreate(
            ['email' => 'ketua@demo.test'],
            ['name' => 'DEMO Ketua', 'password' => Hash::make('Demo-Jeruk-2026!')]
        );
        $user->assignRole('superadmin');
        foreach ([
            ['DEMO-001', 'DEMO Warga Satu', 'L', '1990-01-01'],
            ['DEMO-002', 'DEMO Warga Dua', 'P', '2020-02-29'],
            ['DEMO-003', 'DEMO Warga Tiga', 'P', null],
        ] as [$registration, $name, $gender, $birthDate]) {
            Resident::firstOrCreate(['registration_number' => $registration], [
                'full_name' => $name,
                'gender' => $gender,
                'birth_date' => $birthDate,
                'is_birth_date_estimated' => false,
                'residency_status' => 'active',
            ]);
        }
    }
}
