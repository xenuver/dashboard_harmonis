<?php

namespace App\Imports\Sheets;

use Maatwebsite\Excel\Concerns\ToArray;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class DataTransaksiSheetImport implements ToArray
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

        $headers = $rows[0];

        $transaksiMemberColIndex = array_search('TRANSAKSI_MEMBER', $headers);
        $transaksiNonMemberColIndex = array_search('TRANSAKSI_NON_MEMBER', $headers);
        $memberBaruColIndex = array_search('MEMBER_BARU', $headers);

        if ($transaksiMemberColIndex === false) {
            $this->errors[] = [
                'location' => null,
                'message' => 'Kolom TRANSAKSI_MEMBER tidak ditemukan',
            ];
        }

        if ($transaksiNonMemberColIndex === false) {
            $this->errors[] = [
                'location' => null,
                'message' => 'Kolom TRANSAKSI_NON_MEMBER tidak ditemukan',
            ];
        }

        if ($memberBaruColIndex === false) {
            $this->errors[] = [
                'location' => null,
                'message' => 'Kolom MEMBER_BARU tidak ditemukan',
            ];
        }

        if (! empty($this->errors)) {
            return;
        }

        // Pastikan ada baris data setelah header
        if (! isset($rows[1])) {
            $this->errors[] = [
                'location' => null,
                'message' => 'Data transaksi tidak ditemukan',
            ];

            return;
        }

        $row = $rows[1];

        $rowItem = [
            'transaksi_member' => $row[$transaksiMemberColIndex] ?? null,
            'transaksi_non_member' => $row[$transaksiNonMemberColIndex] ?? null,
            'member_baru' => $row[$memberBaruColIndex] ?? null,
        ];

        $numericFields = [
            'transaksi_member' => $transaksiMemberColIndex,
            'transaksi_non_member' => $transaksiNonMemberColIndex,
            'member_baru' => $memberBaruColIndex,
        ];

        foreach ($numericFields as $field => $columnIndex) {
            $value = $rowItem[$field];

            if ($value !== null && trim((string) $value) !== '') {
                if (! is_numeric($value) || (float) $value != (int) $value) {
                    $this->errors[] = [
                        'location' => Coordinate::stringFromColumnIndex($columnIndex + 1).'2',
                        'message' => "{$field} harus berupa angka bulat",
                    ];
                }
            }
        }

        $this->data = $rowItem;
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
