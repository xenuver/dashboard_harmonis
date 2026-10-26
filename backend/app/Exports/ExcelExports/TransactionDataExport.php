<?php

namespace App\Exports\ExcelExports;

use App\Models\LaporanUpload;
use App\Models\SalesHarian;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

class TransactionDataExport extends DefaultValueBinder implements 
    FromArray, 
    WithTitle, 
    WithStrictNullComparison
{
    protected LaporanUpload $laporan;
    protected SalesHarian $prevSalesHarian;

    public function __construct(LaporanUpload $laporan)
    {
        $this->laporan = $laporan;
    }

    public function array(): array
    {
        $totalMember = $this->laporan->getDataTransaksi->getTotalMemberTransaction();
        $totalNonMember = $this->laporan->getDataTransaksi->getTotalNonMemberTransaction();
        $newMember = $this->laporan->getMemberCount->getNewMemberCount();

        return [
            [
                $totalMember,
                $totalNonMember,
                $newMember,
            ],
        ];
    }

    public function title(): string
    {
        return "DATA";
    }
}