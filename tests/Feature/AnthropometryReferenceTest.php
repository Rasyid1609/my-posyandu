<?php

namespace Tests\Feature;

use Tests\TestCase;

class AnthropometryReferenceTest extends TestCase
{
    public function test_reference_has_complete_age_and_height_coverage_except_the_identified_truncated_source_cell(): void
    {
        $reference = json_decode(
            file_get_contents(resource_path('anthropometry-2020.json')),
            true,
            flags: JSON_THROW_ON_ERROR
        );
        $tables = $reference['tables'];
        foreach ([1, 8] as $table) {
            $this->assertSame(range(0, 60), array_keys($tables[$table]));
        }
        foreach ([2, 6, 9, 13] as $table) {
            $this->assertSame(range(0, 24), array_keys($tables[$table]));
        }
        foreach ([3, 7, 10, 14] as $table) {
            $this->assertSame(range(24, 60), array_keys($tables[$table]));
        }
        foreach ([15, 16] as $table) {
            $this->assertSame(range(61, 228), array_keys($tables[$table]));
        }
        foreach ([4 => [45, 110], 5 => [65, 120], 11 => [45, 110], 12 => [65, 120]] as $table => [$start, $end]) {
            for ($height = $start * 2; $height <= $end * 2; $height++) {
                if ($table === 12 && $height === 139) {
                    $this->assertArrayNotHasKey('69.5', $tables[$table]);
                } else {
                    $this->assertArrayHasKey((string) ($height / 2), $tables[$table]);
                }
            }
        }
        foreach (range(1, 16) as $table) {
            foreach ($tables[$table] as $row) {
                $this->assertCount(7, $row);
                $this->assertSame($row, collect($row)->sort()->values()->all());
            }
        }
    }

    public function test_curated_official_rows_preserve_sex_position_and_page_boundary_alignment(): void
    {
        $tables = json_decode(
            file_get_contents(resource_path('anthropometry-2020.json')),
            true,
            flags: JSON_THROW_ON_ERROR
        )['tables'];

        $this->assertSame([9.1, 10.2, 11.5, 12.9, 14.5, 16.3, 18.4], $tables[1][28]);
        $this->assertSame([2, 2.4, 2.8, 3.2, 3.7, 4.2, 4.8], $tables[8][0]);
        $this->assertSame([78.7, 81.7, 84.8, 87.8, 90.9, 93.9, 97], $tables[2][24]);
        $this->assertSame([78, 81, 84.1, 87.1, 90.2, 93.2, 96.3], $tables[3][24]);
        $this->assertSame([12.1, 13, 14.1, 15.3, 16.6, 18.3, 20.2], $tables[15][61]);
        $this->assertSame([11.8, 12.7, 13.9, 15.2, 16.9, 18.9, 21.3], $tables[16][61]);
        $this->assertSame([88, 102], $tables[17]['12-15']);
    }
}
