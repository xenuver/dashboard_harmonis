<?php

namespace App\Imports\Sheets;

use Maatwebsite\Excel\Concerns\ToArray;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class SupplierPenjualanSheetImport implements ToArray
{
    protected array $data = [];

    protected array $errors = [];

    public function __construct() {}

    public function array(array $rows): void
    {
        if (empty($rows)) {
            $this->errors[] = [
                'location' => null,
                'message' => 'Sheet kosong',
            ];

            return;
        }

        // Normalize header row to uppercase strings
        $headerRow = array_map(fn ($col) => strtoupper(trim((string) $col)), $rows[0]);

        $column = [
            'KODE_SUPP' => array_search('KODE_SUPP', $headerRow),
            'NAMA_SUPP' => array_search('NAMA_SUPP', $headerRow),
            'GROSS_TOTAL' => array_search('GROSS_TOTAL', $headerRow),
            'NET_SALES_TOTAL' => array_search('NET_SALES_TOTAL', $headerRow),
        ];

        // Validate required columns exist
        foreach ($column as $field => $index) {
            if ($index === false) {
                $this->errors[] = [
                    'location' => null,
                    'message' => "Kolom {$field} tidak ditemukan.",
                ];
            }
        }

        if (! empty($this->errors)) {
            return;
        }

        foreach (array_slice($rows, 1) as $rowIndex => $row) {
            $excelRowNumber = $rowIndex + 2;

            // Detect completely empty row
            $isEmptyRow = empty(array_filter($row, fn ($value) => $value !== null && trim((string) $value) !== ''));

            if ($isEmptyRow) {
                $this->errors[] = [
                    'location' => $excelRowNumber,
                    'message' => 'Baris kosong!',
                ];

                continue;
            }

            $rowItem = [
                'kode_supp' => $row[$column['KODE_SUPP']] ?? null,
                'nama_supp' => $row[$column['NAMA_SUPP']] ?? null,
                'gross_total' => $row[$column['GROSS_TOTAL']] ?? null,
                'net_sales_total' => $row[$column['NET_SALES_TOTAL']] ?? null,
            ];

            $hasError = false;

            // Excel column mapping
            $fieldColumns = [
                'kode_supp' => 'KODE_SUPP',
                'nama_supp' => 'NAMA_SUPP',
                'gross_total' => 'GROSS_TOTAL',
                'net_sales_total' => 'NET_SALES_TOTAL',
            ];

            // Check empty fields
            foreach ($fieldColumns as $field => $label) {
                $value = $rowItem[$field];

                if ($value === null || (is_string($value) && trim($value) === '')) {
                    $excelColumn = Coordinate::stringFromColumnIndex(
                        $column[$label] + 1
                    );

                    $this->errors[] = [
                        'location' => "{$excelColumn}{$excelRowNumber}",
                        'message' => "Field {$field} tidak boleh kosong.",
                    ];

                    $hasError = true;
                }
            }

            // Validate numeric integer fields (accepts numeric values like 100 or 100.0)
            $numericFields = [
                'gross_total' => 'GROSS_TOTAL',
                'net_sales_total' => 'NET_SALES_TOTAL',
            ];

            foreach ($numericFields as $field => $label) {
                $value = $rowItem[$field];

                if ($value !== null && trim((string) $value) !== '') {
                    if (! is_numeric($value) || (float) $value != (int) $value) {
                        $excelColumn = Coordinate::stringFromColumnIndex(
                            $column[$label] + 1
                        );

                        $this->errors[] = [
                            'location' => "{$excelColumn}{$excelRowNumber}",
                            'message' => "Field {$field} harus berupa angka bulat.",
                        ];

                        $hasError = true;
                    }
                }
            }

            if (! $hasError) {
                $rowItem['gross_total'] = (int) $rowItem['gross_total'];
                $rowItem['net_sales_total'] = (int) $rowItem['net_sales_total'];

                $this->data[] = $rowItem;
            }
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
