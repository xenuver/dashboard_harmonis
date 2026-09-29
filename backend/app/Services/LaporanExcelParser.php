<?php

namespace App\Services;

use App\Imports\LaporanImport;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;

class LaporanExcelParser
{
    protected LaporanImport $importer;

    public function __construct(UploadedFile $file, bool $isGabungan = false)
    {
        $this->importer = new LaporanImport($isGabungan);
        Excel::import($this->importer, $file);
    }

    public function parseKpiSummary(): array
    {
        return $this->importer->getViewData();
    }

    public function parseSalesHarian(): array
    {
        return $this->importer->getSalesHarianData();
    }

    public function parseProdukPenjualan(string $kategori): array
    {
        return $kategori === 'tertinggi'
            ? $this->importer->getTopHighestData()
            : $this->importer->getTopLowestData();
    }

    public function parseSupplierPenjualan(): array
    {
        return $this->importer->getSupplierData();
    }

    /**
     * Helper to retrieve all parsed sections at once.
     */
    public function parseAll(): array
    {
        return [
            'kpi'          => $this->parseKpiSummary(),
            'sales_harian' => $this->parseSalesHarian(),
            'top_highest'  => $this->parseProdukPenjualan('tertinggi'),
            'top_lowest'   => $this->parseProdukPenjualan('terendah'),
            'supplier'     => $this->parseSupplierPenjualan(),
        ];
    }
}