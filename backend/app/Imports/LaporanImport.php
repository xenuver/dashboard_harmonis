<?php

namespace App\Imports;

use App\Imports\Sheets\ProdukPenjualanSheetImport;
use App\Imports\Sheets\SalesHarianSheetImport;
use App\Imports\Sheets\SupplierPenjualanSheetImport;
use App\Imports\Sheets\ViewSheetImport;
use Maatwebsite\Excel\Concerns\Import;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class LaporanImport implements Import, WithMultipleSheets
{
    protected ViewSheetImport $viewSheet;

    protected SalesHarianSheetImport $salesSheet;

    protected ProdukPenjualanSheetImport $topHighestSheet;

    protected ProdukPenjualanSheetImport $topLowestSheet;

    protected SupplierPenjualanSheetImport $supplierSheet;

    public function __construct()
    {
        $this->viewSheet = new ViewSheetImport;
        $this->salesSheet = new SalesHarianSheetImport;
        $this->topHighestSheet = new ProdukPenjualanSheetImport('tertinggi');
        $this->topLowestSheet = new ProdukPenjualanSheetImport('terendah');
        $this->supplierSheet = new SupplierPenjualanSheetImport;
    }

    public function sheets(): array
    {
        return [
            'VIEW' => $this->viewSheet,
            'DATA' => $this->salesSheet,
            'TOP 300 HIGHEST' => $this->topHighestSheet,
            'TOP 300 LOWEST' => $this->topLowestSheet,
            'TOP SUPPLIER' => $this->supplierSheet,
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
