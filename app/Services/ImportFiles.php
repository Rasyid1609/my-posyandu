<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use ZipArchive;

class ImportFiles
{
    public function rows(UploadedFile $file, string $kind): array
    {
        $path = $file->getRealPath();
        if (mb_strtolower($file->getClientOriginalExtension()) === 'xlsx') {
            $zip = new ZipArchive;
            if ($zip->open($path) !== true) {
                throw ValidationException::withMessages(['file' => 'Berkas XLSX tidak valid.']);
            }
            $size = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $size += $zip->statIndex($i)['size'];
            }
            $count = $zip->numFiles;
            $zip->close();
            if ($size > 30000000 || $count > 2000) {
                throw ValidationException::withMessages(['file' => 'Isi arsip melebihi batas aman.']);
            }
            try {
                $reader = IOFactory::createReaderForFile($path, ['Xlsx']);
                $reader->setReadDataOnly(true);
                $book = $reader->load($path);
                if ($book->getSheetCount() !== 1 || $book->getActiveSheet()->getHighestDataRow() > 501 || Coordinate::columnIndexFromString($book->getActiveSheet()->getHighestDataColumn()) > 26) {
                    throw new \RuntimeException('too_large');
                }
                $raw = $book->getActiveSheet()->toArray(null, false, false);
                $book->disconnectWorksheets();
            } catch (\Throwable) {
                throw ValidationException::withMessages(['file' => 'XLSX harus satu sheet, maksimal 500 baris data dan 26 kolom.']);
            }
        } else {
            $stream = fopen($path, 'rb');
            $raw = [];
            while (($row = fgetcsv($stream, 20000, ',', '"', '')) !== false) {
                $raw[] = $row;
                if (count($raw) > 501) {
                    fclose($stream);
                    throw ValidationException::withMessages(['file' => 'Maksimal 500 baris per import.']);
                }
            }
            fclose($stream);
        }
        $header = array_map(
            fn ($cell) => trim(preg_replace('/^\\xEF\\xBB\\xBF/', '', (string) $cell)),
            array_shift($raw) ?? []
        );
        $expected = $kind === 'residents' ? ['full_name', 'gender', 'birth_date', 'address', 'rt', 'rw'] : ['metric_code', 'value', 'source_cell'];
        if ($header !== $expected) {
            throw ValidationException::withMessages(['file' => 'Header wajib sesuai urutan template: '.implode(', ', $expected)]);
        }
        $rows = [];
        foreach ($raw as $index => $row) {
            if (count(array_filter($row, fn ($value) => $value !== null && trim((string) $value) !== '')) === 0) {
                continue;
            }
            if (count($row) !== count($header)) {
                throw ValidationException::withMessages(['file' => 'Jumlah kolom tidak sesuai di baris '.($index + 2)]);
            }
            $rows[] = array_combine(
                $header,
                array_map(
                    fn ($cell) => $cell === null || trim((string) $cell) === '' ? null : trim((string) $cell),
                    $row
                )
            );
        }
        if (! $rows) {
            throw ValidationException::withMessages(['file' => 'Tidak ada baris data.']);
        }

        return $rows;
    }
}
