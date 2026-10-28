<?php

namespace App\Exports\ExcelExports;

use App\Models\LaporanUpload;
use App\Models\SalesHarian;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class PenjualanHarianExcelExport implements FromCollection, WithColumnFormatting, WithEvents, WithHeadings, WithStrictNullComparison, WithTitle
{
    protected LaporanUpload $laporan;

    public function __construct(LaporanUpload $laporan)
    {
        $this->laporan = $laporan;
    }

    public function collection(): Collection
    {
        $data = DB::table(app(SalesHarian::class)->getTable().' as t')->join('cabang as c', 'c.id', '=', 't.cabang_id')
            ->where('t.upload_id', $this->laporan->id)
            ->selectRaw('
            t.tanggal,
            t.cabang_id,
            c.nama as cabang_nama,
            SUM(t.total_penjualan) as total
        ')
            ->groupBy(
                't.tanggal',
                't.cabang_id',
                'c.nama'
            )
            ->orderBy('t.tanggal')
            ->get();

        $rows = $data
            ->groupBy('tanggal')
            ->map(function ($items, $tanggal) {

                $row = [
                    'day' => Carbon::parse($tanggal)->day,
                    'total' => $items->sum('total'),
                ];

                if ($this->laporan->jenis_laporan === 'gabungan') {
                    foreach ($items as $item) {
                        $row[$item->cabang_nama] = $item->total;
                    }
                }

                return $row;
            })
            ->values();

        return $rows;
    }

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_NUMBER,
            'B' => '[$Rp-421] #,##0',
            'C' => '[$Rp-421] #,##0',
            'D' => '[$Rp-421] #,##0',
        ];
    }

    public function headings(): array
    {
        if ($this->laporan->jenis_laporan === 'gabungan') {
            return [
                'TANGGAL',
                'TOTAL_PENJUALAN',
                'AMPERA',
                'PAL',
            ];
        } else {
            return [
                'TANGGAL',
                'TOTAL_PENJUALAN',
            ];
        }
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                $cellRange = $sheet->calculateWorksheetDimension();
                $sheet->setAutoFilter($cellRange);
                $sheet->freezePane('A2');

                $sheet->getStyle("A1:{$sheet->getHighestColumn()}1")->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => [
                            'rgb' => 'dcf2e1',
                        ],
                    ],
                    'font' => [
                        'bold' => true,
                    ],
                ]);

                foreach ($sheet->getColumnIterator() as $column) {
                    $sheet->getColumnDimension($column->getColumnIndex())->setAutoSize(true);
                }

                $sheet->setSelectedCell('A1');
            },
        ];
    }

    public function title(): string
    {
        return 'PENJUALAN HARIAN';
    }
}
