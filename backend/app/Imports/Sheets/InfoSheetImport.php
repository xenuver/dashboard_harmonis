<?php

namespace App\Imports\Sheets;

use Maatwebsite\Excel\Concerns\ToArray;

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

        $nilaiColIndex = array_search('NILAI', $rows[0]);

        if ($nilaiColIndex === false) {
            $this->errors[] = [
                'location' => null,
                'message' => 'Kolom NILAI tidak ditemukan.',
            ];

            return;
        }

        $this->data = [
            'jenis_laporan' => $rows[1][$nilaiColIndex] ?? null,
            'bulan' => $rows[2][$nilaiColIndex] ?? null,
            'tahun' => $rows[3][$nilaiColIndex] ?? null,
            'jumlah_hari' => $rows[4][$nilaiColIndex] ?? null,
            'format' => $rows[5][$nilaiColIndex] ?? null,
            'catatan' => $rows[6][$nilaiColIndex] ?? null,
        ];

        $requiredFields = [
            'jenis_laporan' => 'JENIS_LAPORAN',
            'bulan' => 'BULAN',
            'tahun' => 'TAHUN',
            'jumlah_hari' => 'JUMLAH_HARI',
            'format' => 'FORMAT',
        ];

        foreach ($requiredFields as $field => $label) {
            if (
                $this->data[$field] === null ||
                trim((string) $this->data[$field]) === ''
            ) {
                $this->errors[] = "{$label} tidak boleh kosong.";
            }
        }

        $integerFields = [
            'bulan' => 'BULAN',
            'tahun' => 'TAHUN',
            'jumlah_hari' => 'JUMLAH_HARI',
        ];

        foreach ($integerFields as $field => $label) {
            $value = $this->data[$field];

            if ($value === null || trim((string) $value) === '') {
                continue;
            }

            if (filter_var($value, FILTER_VALIDATE_INT) === false) {
                $this->errors[] = "{$label} harus berupa angka bulat.";

                continue;
            }

            $this->data[$field] = (int) $value;
        }

        $bulan = $this->data['bulan'];
        $tahun = $this->data['tahun'];
        $jumlahHari = $this->data['jumlah_hari'];

        if ($bulan !== null && ($bulan < 1 || $bulan > 12)) {
            $this->errors[] = 'Bulan harus berupa angka antara 1 sampai 12. ('.$bulan.')';
        }

        if ($tahun !== null && ($tahun < 0 || $tahun > 65535)) {
            $this->errors[] = 'Tahun harus berupa angka antara 0 sampai 65535. ('.$tahun.')';
        }

        if ($jumlahHari !== null && ($jumlahHari < 1 || $jumlahHari > 31)) {
            $this->errors[] = 'Jumlah hari harus berupa angka antara 1 sampai 31. ('.$jumlahHari.')';
        }

        $this->data['bulan'] = (int) $this->data['bulan'];
        $this->data['tahun'] = (int) $this->data['tahun'];
        $this->data['jumlah_hari'] = (int) $this->data['jumlah_hari'];
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
