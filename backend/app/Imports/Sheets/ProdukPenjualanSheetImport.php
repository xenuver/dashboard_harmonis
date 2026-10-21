<?php

namespace App\Imports\Sheets;

use Maatwebsite\Excel\Concerns\ToArray;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class ProdukPenjualanSheetImport implements ToArray
{
    protected array $data = [];

    protected array $errors = [];

    protected string $kategori;

    public function __construct(string $kategori)
    {
        $this->kategori = $kategori;
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

        $column = [
            'KODE_BRG' => array_search('KODE_BRG', $rows[0]),
            'NAMA_BRG' => array_search('NAMA_BRG', $rows[0]),
            'SATUAN' => array_search('SATUAN', $rows[0]),
            'QTY' => array_search('QTY', $rows[0]),
            'HRG_JUAL' => array_search('HRG_JUAL', $rows[0]),
            'JUMLAH' => array_search('JUMLAH', $rows[0]),
            'BARCODE' => array_search('BARCODE', $rows[0]),
            'STOK' => array_search('STOK', $rows[0]),
        ];

        // Validate required columns
        foreach ($column as $field => $index) {
            if ($index === false) {
                $this->errors[] = [
                    'location' => null,
                    'message' => "Kolom {$field} tidak ditemukan",
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
                'kategori' => $this->kategori,
                'barcode' => $row[$column['BARCODE']] ?? null,
                'kode_brg' => $row[$column['KODE_BRG']] ?? null,
                'nama_brg' => $row[$column['NAMA_BRG']] ?? null,
                'satuan' => $row[$column['SATUAN']] ?? null,
                'harga_jual' => $row[$column['HRG_JUAL']] ?? null,
                'jumlah' => $row[$column['JUMLAH']] ?? null,
                'qty' => $row[$column['QTY']] ?? null,
                'stok' => $row[$column['STOK']] ?? null,
            ];

            $hasError = false;

            // Excel column mapping
            $fieldColumns = [
                'barcode' => 'BARCODE',
                'kode_barang' => 'KODE_BRG',
                'nama_barang' => 'NAMA_BRG',
                'satuan' => 'SATUAN',
                'harga_jual' => 'HRG_JUAL',
                'jumlah' => 'JUMLAH',
                'qty' => 'QTY',
                'stok' => 'STOK',
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

            // Validate numeric integer fields
            $numericFields = [
                'qty' => 'QTY',
                'harga_jual' => 'HRG_JUAL',
                'jumlah' => 'JUMLAH',
                'stok' => 'STOK',
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
                $rowItem['qty'] = (int) $rowItem['qty'];
                $rowItem['harga_jual'] = (int) $rowItem['harga_jual'];
                $rowItem['jumlah'] = (int) $rowItem['jumlah'];
                $rowItem['stok'] = (int) $rowItem['stok'];

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
