<?php

namespace App\Imports\Sheets;

use Maatwebsite\Excel\Concerns\ToArray;

class ProdukPenjualanSheetImport implements ToArray
{
    protected array $data = [];

    public function __construct(protected string $kategori) {}

    public function array(array $rows): void
    {
        // Skip baris judul & header (index 0 & 1)
        foreach (array_slice($rows, 2) as $row) {
            if (empty($row[1])) {
                continue;
            }

            $this->data[] = [
                'kategori'   => $this->kategori,
                'barcode'    => $row[0] ?? null,
                'kode_brg'   => $row[1],
                'nama_brg'   => $row[2],
                'satuan'     => $row[3] ?? null,
                'harga_jual' => (int) ($row[4] ?? 0),
                'jumlah'     => (int) ($row[5] ?? 0),
                'qty'        => (int) ($row[6] ?? 0),
                'stok'       => isset($row[7]) ? (int) $row[7] : null,
            ];
        }
    }

    public function getData(): array
    {
        return $this->data;
    }
}