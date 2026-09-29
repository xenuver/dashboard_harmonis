<?php

namespace App\Imports;

use App\Imports\Sheets\ProdukPenjualanSheetImport;
use App\Imports\Sheets\SalesHarianSheetImport;
use App\Imports\Sheets\SupplierPenjualanSheetImport;
use App\Imports\Sheets\ViewSheetImport;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\Import;

class LaporanImport implements WithMultipleSheets, Import
{
    protected ViewSheetImport $viewSheet;
    protected SalesHarianSheetImport $salesSheet;
    protected ProdukPenjualanSheetImport $topHighestSheet;
    protected ProdukPenjualanSheetImport $topLowestSheet;
    protected SupplierPenjualanSheetImport $supplierSheet;

    public function __construct(bool $isGabungan = false)
    {
        $this->viewSheet       = new ViewSheetImport();
        $this->salesSheet      = new SalesHarianSheetImport($isGabungan);
        $this->topHighestSheet = new ProdukPenjualanSheetImport('tertinggi');
        $this->topLowestSheet  = new ProdukPenjualanSheetImport('terendah');
        $this->supplierSheet   = new SupplierPenjualanSheetImport();
    }

    public function sheets(): array
    {
        return [
            0 => $this->viewSheet,
            1 => $this->salesSheet,
            2 => $this->topHighestSheet,
            3 => $this->topLowestSheet,
            4 => $this->supplierSheet,
        ];
    }

    public function getViewData(): array
    {
        return $this->viewSheet->getData();
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
}