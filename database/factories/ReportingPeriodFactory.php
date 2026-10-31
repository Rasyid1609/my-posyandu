<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ReportingPeriodFactory extends Factory
{
    public function definition(): array
    {
        return [
            'month' => 1,
            'year' => 2026,
            'reference_date' => '2026-01-31',
            'status' => 'open',
            'revision' => 1,
        ];
    }
}
