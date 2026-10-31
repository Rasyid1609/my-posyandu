<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ResidentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'registration_number' => 'DEMO-'.Str::ulid(),
            'full_name' => 'DEMO '.fake()->name(),
            'gender' => fake()->randomElement(['L', 'P']),
            'birth_date' => '1990-01-01',
            'is_birth_date_estimated' => false,
            'residency_status' => 'active',
            'revision' => 1,
        ];
    }
}
