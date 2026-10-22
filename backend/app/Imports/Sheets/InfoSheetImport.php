<?php

namespace App\Imports\Sheets;

use Maatwebsite\Excel\Concerns\ToArray;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class InfoSheetImport implements ToArray
{
    protected array $data = [];

    protected array $errors = [];

    public function array(array $rows): void
    {
        if (empty($rows)) {
            $this->errors[] = [
                'location' => null,
                'message' => 'Sheet kosong',
            ];

            return;
        }

        $keteranganColIndex = array_search('KETERANGAN', $rows[0]);
        $nilaiColIndex = array_search('NILAI', $rows[0]);

        if ($keteranganColIndex === false) {
            $this->errors[] = [
                'location' => null,
                'message'  => 'Kolom KETERANGAN tidak ditemukan.',
            ];

            return;
        }

        if ($nilaiColIndex === false) {
            $this->errors[] = [
                'location' => null,
                'message' => 'Kolom NILAI tidak ditemukan.',
            ];

            return;
        }

        $allFields = [
            'jenis_laporan' => 'JENIS_LAPORAN',
            'bulan' => 'BULAN',
            'tahun' => 'TAHUN',
            'jumlah_hari' => 'JUMLAH_HARI',
            'format' => 'FORMAT',
        ];

        $requiredFields = [
            'jenis_laporan' => 'JENIS_LAPORAN',
            'bulan' => 'BULAN',
            'tahun' => 'TAHUN',
            'jumlah_hari' => 'JUMLAH_HARI',
            'format' => 'FORMAT',
        ];

        $entryRowIndex = [];

        foreach($allFields as $field => $columnName) {
            foreach($rows as $rowIndex => $value1) {
                if($rows[$rowIndex][$keteranganColIndex] === $columnName) {
                    $this->data[$field] = $rows[$rowIndex][$nilaiColIndex];
                    $entryRowIndex[$field] = $rowIndex;
                }
            }
        }

        foreach ($requiredFields as $field => $label) {
            if (!array_key_exists($field, $this->data)) {
                $this->errors[] = [
                    "location" => null,
                    "message" => "{$label} tidak ditemukan!",
                ];
                continue;
            }

            if ($this->data[$field] === null || trim((string) $this->data[$field]) === '') {
                $this->errors[] = [
                    "location" => Coordinate::stringFromColumnIndex($nilaiColIndex + 1).($entryRowIndex[$field] + 1),
                    "message" => "{$label} tidak boleh kosong.",
                ];
                continue;
            }
        }

        $integerFields = [
            'bulan' => 'BULAN',
            'tahun' => 'TAHUN',
            'jumlah_hari' => 'JUMLAH_HARI',
        ];

        foreach ($integerFields as $field => $label) {
            $value = $this->data[$field] ?? null;

            if ($value === null || trim((string) $value) === '') {
                continue;
            }

            $cellCoordinate = Coordinate::stringFromColumnIndex($nilaiColIndex + 1).($entryRowIndex[$field] + 1);

            if (filter_var($value, FILTER_VALIDATE_INT) === false) {
                $this->errors[] = [
                    "location" => $cellCoordinate,
                    "message" => "{$label} harus berupa angka bulat.",
                ];

                continue;
            }

            if ($field === 'bulan' && filter_var($this->data['bulan'] ?? null, FILTER_VALIDATE_INT) !== false) {
                $bulan = $this->data['bulan'];
                if ($bulan !== null && ($bulan < 1 || $bulan > 12)) {
                    $this->errors[] = [
                        "location" => $cellCoordinate,
                        "message" => 'Bulan harus berupa angka antara 1 sampai 12. ('.$bulan.')',
                    ];
                }
            }

            if ($field === 'tahun' && filter_var($this->data['tahun'] ?? null, FILTER_VALIDATE_INT) !== false) {
                $tahun = $this->data['tahun'];
                if ($tahun !== null && ($tahun < 0 || $tahun > 65535)) {
                    $this->errors[] = [
                        "location" => $cellCoordinate,
                        "message" => 'Tahun harus berupa angka antara 0 sampai 65535. ('.$tahun.')',
                    ];
                }
            }

            if ($field === 'jumlah_hari' && filter_var($this->data['jumlah_hari'] ?? null, FILTER_VALIDATE_INT) !== false) {
                $jumlahHari = $this->data['jumlah_hari'];
                if ($jumlahHari !== null && ($jumlahHari < 1 || $jumlahHari > 31)) {
                    $this->errors[] = [
                        "location" => $cellCoordinate,
                        "message" => 'Jumlah hari harus berupa angka antara 1 sampai 31. ('.$jumlahHari.')',
                    ];
                }
            }

            $this->data[$field] = (int) $value;
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
