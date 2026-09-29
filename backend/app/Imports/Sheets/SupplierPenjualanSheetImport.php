<?php

namespace App\Imports\Sheets;

use Maatwebsite\Excel\Concerns\ToArray;

class SupplierPenjualanSheetImport implements ToArray
{
    protected array $data = [];

    public function array(array $rows): void
    {
        foreach (array_slice($rows, 2) as $row) {
            if (empty($row[0])) {
                continue;
            }

            $this->data[] = [
                'kode_supp'       => $row[0],
                'nama_supp'       => $row[1],
                'gross_total'     => (int) ($row[2] ?? 0),
                'net_sales_total' => (int) ($row[3] ?? 0),
            ];
        }
    }

    public function getData(): array
    {
        return $this->data;
    }
}