<?php

namespace App\Exports\ExcelExports;

use App\Models\LaporanUpload;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class SupplierExcelExport implements FromQuery, WithColumnFormatting, WithHeadings, WithMapping, WithStrictNullComparison, WithTitle, WithEvents
{
    protected LaporanUpload $laporan;

    public function __construct(LaporanUpload $laporan)
    {
        $this->laporan = $laporan;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                foreach($sheet->getColumnIterator() as $column) {
                    $sheet->getColumnDimension($column->getColumnIndex())->setAutoSize(true);
                }

                $cellRange = $sheet->calculateWorksheetDimension();
                $sheet->setAutoFilter($cellRange);
                $sheet->freezePane('A2');

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
            }
        ];
    }

    public function query(): Relation|EloquentBuilder
    {
        return $this->laporan->supplierPenjualan();
    }

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'B' => NumberFormat::FORMAT_TEXT,
            'C' => '[$Rp-421] #,##0',
            'D' => '[$Rp-421] #,##0',
        ];
    }

    public function headings(): array
    {
        return [
            'KODE_SUPP',
            'NAMA_SUPP',
            'GROSS_TOTAL',
            'NET_SALES_TOTAL',
        ];
    }

    public function map($supplier): array
    {
        return [
            $supplier->kode_supp,
            $supplier->nama_supp,
            (float) $supplier->gross_total,
            (float) $supplier->net_sales_total,
        ];
    }

    public function title(): string
    {
        return 'TOP SUPPLIER';
    }
}
