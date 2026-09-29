<?php

namespace App\Imports\Sheets;

use Maatwebsite\Excel\Concerns\ToArray;

class ViewSheetImport implements ToArray
{
    protected array $data = [];

    public function array(array $rows): void
    {
        // Posisi Excel baris 5 (index 4)
        $row = $rows[4] ?? [];

        $this->data = [
            'total_sales'      => (int) ($row[6] ?? 0),
            'total_growth'     => (float) ($row[11] ?? 0),
            'jumlah_transaksi' => (int) ($row[16] ?? 0),
            'growth_member'    => (int) str_replace('+', '', (string) ($row[21] ?? 0)),
        ];
    }

    public function getData(): array
    {
        return $this->data;
    }
}