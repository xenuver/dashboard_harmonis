<?php

namespace App\Imports;

use App\Imports\Sheets\DataTransaksiSheetImport;
use App\Imports\Sheets\InfoSheetImport;
use App\Imports\Sheets\PenjualanHarianSheetImport;
use App\Imports\Sheets\ProdukPenjualanSheetImport;
use App\Imports\Sheets\SupplierPenjualanSheetImport;
use Maatwebsite\Excel\Concerns\Import;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class LaporanImport implements Import, WithMultipleSheets
{
    protected InfoSheetImport $infoSheet;

    protected PenjualanHarianSheetImport $salesSheet;

    protected DataTransaksiSheetImport $transactionSheet;

    protected ProdukPenjualanSheetImport $topHighestSheet;

    protected ProdukPenjualanSheetImport $topLowestSheet;

    protected SupplierPenjualanSheetImport $supplierSheet;

    public function __construct(bool $isGabungan)
    {
        $this->infoSheet = new InfoSheetImport;
        $this->salesSheet = new PenjualanHarianSheetImport($isGabungan);
        $this->transactionSheet = new DataTransaksiSheetImport;
        $this->topHighestSheet = new ProdukPenjualanSheetImport('tertinggi');
        $this->topLowestSheet = new ProdukPenjualanSheetImport('terendah');
        $this->supplierSheet = new SupplierPenjualanSheetImport;
    }

    public function sheets(): array
    {
        return [
            'INFO' => $this->infoSheet,
            'PENJUALAN_HARIAN' => $this->salesSheet,
            'TRANSAKSI' => $this->transactionSheet,
            'PRODUK_TERTINGGI' => $this->topHighestSheet,
            'PRODUK_TERENDAH' => $this->topLowestSheet,
            'SUPPLIER' => $this->supplierSheet,
        ];
    }

    public function getInfoData(): array
    {
        return $this->infoSheet->getData();
    }

    public function getSalesHarianData(): array
    {
        return $this->salesSheet->getData();
    }

    public function getTopHighestData(): array
    {
        return $this->topHighestSheet->getData();
    }

    public function getTopLowestData(): array
    {
        return $this->topLowestSheet->getData();
    }

    public function getSupplierData(): array
    {
        return $this->supplierSheet->getData();
    }

    public function getImportErrors(): array
    {
        return array_merge(
            $this->infoSheet->getErrors(),
            $this->salesSheet->getErrors(),
            $this->topHighestSheet->getErrors(),
            $this->topLowestSheet->getErrors(),
            $this->supplierSheet->getErrors(),
        );
    }
}
