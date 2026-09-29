<?php

namespace App\Imports\Sheets;

use Maatwebsite\Excel\Concerns\ToArray;

class SalesHarianSheetImport implements ToArray
{
    protected array $data = [];

    public function __construct() {}

    public function array(array $rows): void
    {
        // Skip 2 baris awal (header)
        foreach (array_slice($rows, 2) as $row) {
            $tanggal = $row[3] ?? null;
            if ($tanggal === null || $tanggal === '') {
                continue;
            }

            $this->data[(int) $tanggal] = [
                'total'  => (int) ($row[4] ?? 0),
                'ampera' => (int) ($row[10] ?? 0),
                'pal'    => (int) ($row[11] ?? 0),
            ];
        }
    }

    public function getData(): array
    {
        return $this->data;
    }
}