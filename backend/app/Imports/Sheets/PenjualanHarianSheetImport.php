<?php

namespace App\Imports\Sheets;

use Maatwebsite\Excel\Concerns\ToArray;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class PenjualanHarianSheetImport implements ToArray
{
    protected bool $isGabungan;

    protected array $data = [];

    protected array $errors = [];

    protected int $dayLength;

    public function __construct(bool $isGabungan, ?int $dayLength = null)
    {
        $this->isGabungan = $isGabungan;
        $this->dayLength = $dayLength ?? (int) date('t');
    }

    public function array(array $rows): void
    {
        if (empty($rows)) {
            $this->errors[] = [
                'location' => null,
                'message' => 'Sheet kosong',
            ];

            return;
        }

        $tanggalIndex = array_search('TANGGAL', $rows[0]);
        $totalPenjualanIndex = array_search('TOTAL_PENJUALAN', $rows[0]);
        $totalPenjualanAmperaIndex = array_search('AMPERA', $rows[0]);
        $totalPenjualanPalIndex = array_search('PAL', $rows[0]);

        if ($tanggalIndex === false) {
            $this->errors[] = [
                'location' => null,
                'message' => 'Kolom TANGGAL tidak ditemukan',
            ];
        }

        if ($totalPenjualanIndex === false) {
            $this->errors[] = [
                'location' => null,
                'message' => 'Kolom TOTAL_PENJUALAN tidak ditemukan',
            ];
        }

        if ($this->isGabungan === true) {
            if ($totalPenjualanAmperaIndex === false) {
                $this->errors[] = [
                    'location' => null,
                    'message' => 'Kolom AMPERA tidak ditemukan',
                ];
            }
            if ($totalPenjualanPalIndex === false) {
                $this->errors[] = [
                    'location' => null,
                    'message' => 'Kolom PAL tidak ditemukan',
                ];
            }
        }

        if (! empty($this->errors)) {
            return;
        }

        foreach (array_slice($rows, 1) as $rowIndex => $row) {
            if (empty($row)) {
                $this->errors[] = [
                    'location' => strval($row),
                    'message' => 'Baris kosong!',
                ];

                continue;
            }

            $tanggal = $row[$tanggalIndex] ?? null;
            if ($tanggal === null || $tanggal === '') {
                $this->errors[] = [
                    'location' => Coordinate::stringFromColumnIndex($tanggalIndex + 1).($rowIndex + 2),
                    'message' => 'Terdapat tanggal kosong',
                ];

                continue;
            }

            $numericFields = [
                'TANGGAL' => $tanggalIndex,
                'TOTAL_PENJUALAN' => $totalPenjualanIndex,
            ];

            if ($this->isGabungan === true) {
                $numericFields['AMPERA'] = $totalPenjualanAmperaIndex;
                $numericFields['PAL'] = $totalPenjualanPalIndex;
            }

            foreach ($numericFields as $field => $columnIndex) {
                $value = $row[$columnIndex] ?? null;

                if ($value !== null && trim((string) $value) !== '') {
                    if (! is_numeric($value) || (float) $value != (int) $value) {
                        $this->errors[] = [
                            'location' => Coordinate::stringFromColumnIndex($columnIndex + 1).($rowIndex + 2),
                            'message' => "{$field} harus berupa angka bulat",
                        ];
                    }
                } else {
                    $this->errors[] = [
                        'location' => Coordinate::stringFromColumnIndex($columnIndex + 1).($rowIndex + 2),
                        'message' => "{$field} kosong!",
                    ];
                }
            }

            if ($this->isGabungan === true) {
                $this->data[(int) $tanggal] = [
                    'total' => (int) ($row[$totalPenjualanIndex] ?? null),
                    'ampera' => (int) ($row[$totalPenjualanAmperaIndex] ?? null),
                    'pal' => (int) ($row[$totalPenjualanPalIndex] ?? null),
                ];
            } else {
                $this->data[(int) $tanggal] = [
                    'total' => (int) ($row[$totalPenjualanIndex] ?? null),
                ];
            }
        }

        for ($i = 1; $i <= $this->dayLength; $i++) {
            if (! array_key_exists($i, $this->data)) {
                $this->errors[] = [
                    'location' => null,
                    'message' => 'Data hari ke-'.$i.' tidak ditemukan!',
                ];
            }
        }

        if (count($this->data) !== $this->dayLength) {
            $this->errors[] = [
                'location' => null,
                'message' => 'Jumlah hari tidak sama dengan yang disebutkan! Tanggal diinput: '.($this->dayLength).', tapi '.count($this->data).' hari terbaca',
            ];
        }
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getData(): array
    {
        return $this->data;
    }
}
