<?php

namespace App\Exports\ExcelExports;

use App\Models\LaporanUpload;
use App\Models\SalesHarian;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class PenjualanHarianExcelExport implements FromArray, WithHeadings, WithTitle, WithEvents, WithColumnFormatting
{
    protected LaporanUpload $laporan;
    protected ?LaporanUpload $laporanPrev;

    protected array $monthList = [
        'JANUARI','FEBRUARI','MARET','APRIL','MAY','JUNI',
        'JULI','AGUSTUS','SEPTEMBER','OKTOBER','NOVEMBER','DESEMBER',
    ];

    public function __construct(LaporanUpload $laporan)
    {
        $this->laporan = $laporan;

        $prevDate = Carbon::createFromDate($laporan->periode_tahun, $laporan->periode_bulan, 1)->subMonth();
        $this->laporanPrev = LaporanUpload::jenisLaporan($laporan->jenis_laporan)
            ->periode($prevDate->month, $prevDate->year)
            ->first();
    }

    public function array(): array
    {
        $reportMonthDayCount = Carbon::createFromDate($this->laporan->periode_tahun, $this->laporan->periode_bulan, 1)->daysInMonth() ?? 0;
        $reportMonthDayCountPrev = null;

        if($this->laporanPrev) {
            $reportMonthDayCountPrev = Carbon::createFromDate($this->laporanPrev->periode_tahun, $this->laporanPrev->periode_bulan, 1)->daysInMonth();
        }

        $pickedReportDayCount = max($reportMonthDayCount, $reportMonthDayCountPrev);

        $sales = DB::table(app(SalesHarian::class)->getTable().' as t')
            ->join('cabang as c', 'c.id', '=', 't.cabang_id')
            ->where('t.upload_id', $this->laporan->id)
            ->selectRaw('t.tanggal, SUM(t.total_penjualan) as total')
            ->groupBy('t.tanggal')
            ->orderBy('t.tanggal')
            ->get();

        $salesPrev = [];

        if($this->laporanPrev) {
            $salesPrev = DB::table(app(SalesHarian::class)->getTable().' as t')
                ->join('cabang as c', 'c.id', '=', 't.cabang_id')
                ->where('t.upload_id', $this->laporanPrev->id)
                ->selectRaw('t.tanggal, SUM(t.total_penjualan) as total')
                ->groupBy('t.tanggal')
                ->orderBy('t.tanggal')
                ->get();
        }

        $rows = [];

        for ($day = 0; $day < $pickedReportDayCount; $day++) {
            if ($this->laporanPrev) {
                $rows[] = [
                    $day + 1,
                    $salesPrev[$day]->total ?? null,
                    $sales[$day]->total ?? null,
                ];
            } else {
                $rows[] = [
                    $day + 1,
                    $sales[$day]->total ?? null,
                ];
            }
        }

        return $rows;
    }

    public function headings(): array
    {
        $rows = ['HARI'];

        if($this->laporanPrev) {
            $rows[] = $this->monthList[$this->laporanPrev->periode_bulan - 1].' '.$this->laporanPrev->periode_tahun;
        }
        $rows[] = $this->monthList[$this->laporan->periode_bulan -1].' '.$this->laporan->periode_tahun;

        return $rows;
    }

    public function columnFormats(): array
    {
        $columnFormats=  [
            'A' => NumberFormat::FORMAT_NUMBER,
            'B' => '[$Rp-421] #,##0',
        ];

        if($this->laporanPrev) {
            $columnFormats['C'] = '[$Rp-421] #,##0';
        }

        return $columnFormats;
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
