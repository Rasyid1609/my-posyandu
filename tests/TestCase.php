<?php

namespace Tests;

use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['app.locale' => 'id']);
    }

    protected function createSuperadmin(): User
    {
        $this->seed(AccessControlSeeder::class);
        $user = User::factory()->create();
        $user->assignRole('superadmin');

        return $user;
    }

    protected function createStaff(string $role = 'admin', array $permissions = []): User
    {
        $this->seed(AccessControlSeeder::class);
        $user = User::factory()->create();
        $user->assignRole($role);
        $user->givePermissionTo($permissions);

        return $user;
    }

    protected function residentPayload(array $overrides = []): array
    {
        return array_replace([
            'full_name' => 'DEMO Warga Uji',

            'gender' => 'P',

            'birth_date' => '1990-01-01',

            'is_birth_date_estimated' => false,

            'address' => null,
            'rt' => null,
            'rw' => null,

            'residency_status' => 'active',

            'effective_date' => '2026-01-01',

            'reason' => 'Pencatatan data sintetis',
        ], $overrides);
    }

    protected function visitPayload(int $revision, array $overrides = []): array
    {
        return array_replace([
            'revision' => $revision,

            'visited_at' => '2026-01-15T09:00',

            'weight_state' => 'not_examined',
            'height_state' => 'not_examined',

            'blood_pressure_state' => 'not_examined',
            'glucose_state' => 'not_examined',

            'weight_kg' => null,
            'height_cm' => null,

            'systolic_mmhg' => null,
            'diastolic_mmhg' => null,
            'glucose_mg_dl' => null,
        ], $overrides);
    }
}
