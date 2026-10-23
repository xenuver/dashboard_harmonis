<?php

namespace App\Exports;

use App\Exports\ExcelExports\SupplierExcelExport;
use App\Exports\ExcelExports\TopProductsExcelExport;
use App\Exports\ExcelExports\WorstProductExcelExport;
use App\Models\LaporanUpload;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class SalesDashboardExport implements WithMultipleSheets, Export
{
    protected LaporanUpload $laporan;

    public function __construct(LaporanUpload $laporan)
    {
        $this->laporan = $laporan;
    }

    public function sheets(): array
    {
        return [
            new TopProductsExcelExport($this->laporan),
            new WorstProductExcelExport($this->laporan),
            new SupplierExcelExport($this->laporan),
        ];
    }
}
