<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AggregateExport extends DefaultValueBinder implements FromArray, WithColumnWidths, WithCustomValueBinder, WithStyles
{
    public function __construct(private array $metadata, private array $rows) {}

    public function array(): array
    {
        $values = [
            ['POSYANDU JERUK — REKAP APLIKASI'],
            ...array_map(
                fn ($key, $value) => [$key, $value],
                array_keys($this->metadata),
                array_values($this->metadata)
            ),

            [],
            ['Indikator', 'Jumlah', 'Penyebut', 'Persentase (%)'],

            ...array_map(fn ($row) => [
                $row['label'],
                $row['value'] ?? 'Belum diketahui',
                $row['denominator'] ?? 'Tidak berlaku',
                $row['percentage'] ?? 'Tidak berlaku',
            ], $this->rows),
        ];

        return array_map(
            fn ($row) => array_map(
                fn ($cell) => is_string($cell) && preg_match('/^[\\s]*[=+@\\-]/u', $cell) ? "'".$cell : $cell,
                $row
            ),
            $values
        );
    }

    public function columnWidths(): array
    {
        return ['A' => 78, 'B' => 36, 'C' => 20, 'D' => 20];
    }

    public function styles(Worksheet $sheet): ?array
    {
        $header = count($this->metadata) + 3;
        $sheet->freezePane('B'.($header + 1));
        $sheet->getStyle('A1:D'.$sheet->getHighestRow())->getAlignment()->setWrapText(true);
        $sheet->getPageSetup()
            ->setOrientation('landscape')
            ->setFitToWidth(1)
            ->setFitToHeight(0)
            ->setRowsToRepeatAtTopByStartAndEnd($header, $header);
        $sheet->getStyle('A'.$header.':D'.$header)
            ->getFont()
            ->setBold(true)
            ->getColor()
            ->setARGB('FFFFFFFF');
        $sheet->getStyle('A'.$header.':D'.$header)
            ->getFill()
            ->setFillType('solid')
            ->getStartColor()
            ->setARGB('FF2E5E49');

        return [1 => ['font' => ['bold' => true, 'size' => 16]]];
    }
}
