<?php

namespace App\Exports\ExcelExports;

use App\Models\LaporanUpload;
use App\Models\SalesHarian;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class TransactionDataExport extends DefaultValueBinder implements FromArray, WithHeadings, WithStrictNullComparison, WithTitle, WithEvents
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

    public function headings(): array
    {
        return [
            'TOTAL_MEMBER',
            'TOTAL_NON_MEMBER',
            'MEMBER_BARU',
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                $sheet->getStyle("A1:{$sheet->getHighestColumn()}1")->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => [
                            'rgb' => 'dcf2e1'
                        ]
                    ],
                    'font' => [
                        'bold' => true,
                    ],
                ]);

                foreach($sheet->getColumnIterator() as $column) {
                    $sheet->getColumnDimension($column->getColumnIndex())->setAutoSize(true);
                }
            }
        ];
    }

    public function title(): string
    {
        return 'DATA';
    }
}
