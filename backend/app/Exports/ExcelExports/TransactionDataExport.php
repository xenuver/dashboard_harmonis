<?php

namespace App\Exports\ExcelExports;

use App\Models\LaporanUpload;
use App\Models\SalesHarian;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Events\BeforeSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class TransactionDataExport extends DefaultValueBinder implements FromArray, WithEvents, WithStrictNullComparison, WithTitle
{
    protected LaporanUpload $laporan;
    protected ?LaporanUpload $laporanPrev;

    protected array $monthList = [
        'JANUARI','FEBRUARI','MARET','APRIL','MAY','JUNI',
        'JULI','AGUSTUS','SEPTEMBER','OKTOBER','NOVEMBER','DESEMBER',
    ];

    protected SalesHarian $prevSalesHarian;

    protected int $lowestGridY1 = 0;
    protected int $rightestGridY1 = 0;

    protected array $requestMergeCell = [];
    protected array $requestFillColor = [];
    protected array $requestBoldText = [];
    protected array $requestNumberFormat = [];
    protected array $requestTextAlignmentH = [];
    protected array $requestEnableColAutoWidth = [];

    public function __construct(LaporanUpload $laporan)
    {
        $this->laporan = $laporan;

        $prevDate = Carbon::createFromDate($laporan->periode_tahun, $laporan->periode_bulan, 1)->subMonth();
        $this->laporanPrev = LaporanUpload::jenisLaporan($laporan->jenis_laporan)
            ->periode($prevDate->month, $prevDate->year)
            ->first();
    }

    protected function renderPenjualanHarianTable(LaporanUpload $laporan, int $coordStartX = 0, int $coordStartY = 0) {
        $dataSales = DB::table(app(SalesHarian::class)->getTable().' as t')->join('cabang as c', 'c.id', '=', 't.cabang_id')
            ->where('t.upload_id', $laporan->id)
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

        $periodeLaporan = $this->monthList[$laporan->periode_bulan - 1].' '.$laporan->periode_tahun;

        // render heading
        $tableHeading = [];
        $tableHeading[] = [$periodeLaporan];

        $this->requestEnableColAutoWidth[] = $coordStartX;
        $this->requestEnableColAutoWidth[] = $coordStartX + 1;

        if($laporan->jenis_laporan === 'gabungan') {
            $tableHeading[] = ['HARI', 'TOTAL', 'AMPERA', 'PAL'];

            $this->requestEnableColAutoWidth[] = $coordStartX + 2;
            $this->requestEnableColAutoWidth[] = $coordStartX + 3;

            // baris 1 kolom 1 - baris 1 kolom 4
            $this->requestMergeCell[] = (Coordinate::stringFromColumnIndex($coordStartX + 1)).($coordStartY + 1).':'.(Coordinate::stringFromColumnIndex($coordStartX + 4)).($coordStartY + 1);
        } else {
            $tableHeading[] = ['HARI', 'TOTAL'];

            // baris 1 kolom 1 - baris 1 kolom 2
            $this->requestMergeCell[] = (Coordinate::stringFromColumnIndex($coordStartX + 1)).($coordStartY + 1).':'.(Coordinate::stringFromColumnIndex($coordStartX + 2)).($coordStartY + 1);
        }

        $tableData = $dataSales
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
            ->values()->toArray();

        $finalTable = array_merge($tableHeading, $tableData);
        $tableSizeX = !empty($finalTable)? max(array_map('count', $finalTable)): 0;
        $tableSizeY = count($finalTable);

        $this->requestNumberFormat[] = [
            'cells' => Coordinate::stringFromColumnIndex($coordStartX + 2).($coordStartY + 3).':'.Coordinate::stringFromColumnIndex($coordStartX + $tableSizeX).($coordStartY + $tableSizeY),
            'formatCode' => '[$Rp-421] #,##0',
        ];

        // render data
        return [
            'table' => $finalTable,
            'sizeX' => $tableSizeX,
            'sizeY' => $tableSizeY,
        ];
    }

    protected function renderTotalPenjualanTable(int $coordStartX = 0, int $coordStartY = 0) {
        $totalSales = 0;
        $minSales = 0;
        $maxSales = 0;
        $avgSales = 0;

        $totalSalesPrev = 0;
        $minSalesPrev = 0;
        $maxSalesPrev = 0;
        $avgSalesPrev = 0;

        $dailyTotalQuery = SalesHarian::where('upload_id', $this->laporan->id)->select('tanggal', DB::raw('SUM(total_penjualan) as daily_total'))->groupBy('tanggal');

        $dailySummary = DB::query()->fromSub($dailyTotalQuery, 'daily_summary');

        $totalSales = (clone $dailySummary)->sum('daily_total');
        $minSales = (clone $dailySummary)->min('daily_total');
        $maxSales = (clone $dailySummary)->max('daily_total');
        $avgSales = (clone $dailySummary)->avg('daily_total');

        if ($this->laporanPrev) {
            $dailyTotalQueryPrev = SalesHarian::where('upload_id', $this->laporanPrev->id)->select('tanggal', DB::raw('SUM(total_penjualan) as daily_total'))->groupBy('tanggal');

            $dailySummaryPrev = DB::query()->fromSub($dailyTotalQueryPrev, 'daily_summary');

            $totalSalesPrev = (clone $dailySummaryPrev)->sum('daily_total');
            $minSalesPrev = (clone $dailySummaryPrev)->min('daily_total');
            $maxSalesPrev = (clone $dailySummaryPrev)->max('daily_total');
            $avgSalesPrev = (clone $dailySummaryPrev)->avg('daily_total');
        }

        // render heading
        $tableHeading = [];
        $tableHeading[] = ['TOTAL PENJUALAN'];

        $bulanLaporan = $this->monthList[$this->laporan->periode_bulan - 1].' '.$this->laporan->periode_tahun;

        $this->requestEnableColAutoWidth[] = $coordStartX;
        $this->requestEnableColAutoWidth[] = $coordStartX + 1;

        if($this->laporanPrev) {
            // baris 1 kolom 1 - baris 1 kolom 3
            $this->requestMergeCell[] = (Coordinate::stringFromColumnIndex($coordStartX + 1)).($coordStartY + 1).':'.(Coordinate::stringFromColumnIndex($coordStartX + 3)).($coordStartY + 1);
            $bulanLaporanPrev = $this->monthList[$this->laporanPrev->periode_bulan - 1].' '.$this->laporanPrev->periode_tahun;
            $tableHeading[] = ['', $bulanLaporanPrev, $bulanLaporan];

            $this->requestEnableColAutoWidth[] = $coordStartX + 2;
        } else {
            // baris 1 kolom 1 - baris 1 kolom 2
            $this->requestMergeCell[] = (Coordinate::stringFromColumnIndex($coordStartX + 1)).($coordStartY + 1).':'.(Coordinate::stringFromColumnIndex($coordStartX + 2)).($coordStartY + 1);
            $tableHeading[] = ['', $bulanLaporan];
        }

        // render data
        if($this->laporanPrev) {
            $tableData[] = ['TOTAL', $totalSalesPrev, $totalSales];
            $tableData[] = ['LOWEST', $minSalesPrev, $minSales];
            $tableData[] = ['HIGHEST', $maxSalesPrev, $maxSales];
            $tableData[] = ['AVG', $avgSalesPrev, $avgSales];
        } else {
            $tableData[] = ['TOTAL', $totalSales];
            $tableData[] = ['LOWEST', $minSales];
            $tableData[] = ['HIGHEST', $maxSales];
            $tableData[] = ['AVG', $avgSales];
        }

        $finalTable = array_merge($tableHeading, $tableData);
        $tableSizeX = !empty($finalTable)? max(array_map('count', $finalTable)): 0;
        $tableSizeY = count($finalTable);

        $this->requestNumberFormat[] = [
            'cells' => Coordinate::stringFromColumnIndex($coordStartX +2).($coordStartY + 3).':'.Coordinate::stringFromColumnIndex($coordStartX + $tableSizeX).($coordStartY + $tableSizeY),
            'formatCode' => '[$Rp-421] #,##0',
        ];

        return [
            'table' => $finalTable,
            'sizeX' => $tableSizeX,
            'sizeY' => $tableSizeY,
        ];
    }

    protected function renderCustomerGrowthTable(int $coordStartX = 0, int $coordStartY = 0) {
        $totalSales = $this->laporan->salesHarian()->sum('total_penjualan');
        $totalSalesPrev = 0;
        if($this->laporanPrev) {
            $totalSalesPrev = $this->laporanPrev->salesHarian()->sum('total_penjualan');
        }

        $this->requestEnableColAutoWidth[] = $coordStartX;
        $this->requestEnableColAutoWidth[] = $coordStartX + 1;

        $finalTable = [['CUST GROWTH', (($totalSales-$totalSalesPrev)/$totalSalesPrev)]];
        $tableSizeX = max(array_map('count', $finalTable));
        $tableSizeY = count($finalTable);

        $this->requestNumberFormat[] = [
            'cells' => Coordinate::stringFromColumnIndex($coordStartX + 2).($coordStartY + 1),
            'formatCode' => NumberFormat::FORMAT_PERCENTAGE,
        ];

        return [
            'table' => $finalTable,
            'sizeX' => $tableSizeX,
            'sizeY' => $tableSizeY,
        ];
    }

    protected function renderMemberGrowthTable(int $coordStartX = 0, int $coordStartY = 0) {
        $memberGrowth = $this->laporan->getMemberCount->getNewMemberCount();
        $memberGrowthPrev = 0;
        $memberGrowthDiff = 0;

        if($this->laporanPrev) {
            $memberGrowthPrev = $this->laporanPrev->getMemberCount->getNewMemberCount();
            $memberGrowthDiff = ($memberGrowth - $memberGrowthPrev)/$memberGrowthPrev;
        }

        // render heading
        $tableHeading = [];

        $bulanLaporan = $this->monthList[$this->laporan->periode_bulan - 1].' '.$this->laporan->periode_tahun;

        $this->requestEnableColAutoWidth[] = $coordStartX;
        $this->requestEnableColAutoWidth[] = $coordStartX + 1;

        if($this->laporanPrev) {
            $tableHeading[] = ['GROWTH MEMBER'];
            $bulanLaporanPrev = $this->monthList[$this->laporanPrev->periode_bulan - 1].' '.$this->laporanPrev->periode_tahun;
            $tableHeading[] = [$bulanLaporanPrev, $bulanLaporan, 'DIFF'];

            $this->requestEnableColAutoWidth[] = $coordStartX + 2;
        }

        $tableData = [];

        // render data
        if($this->laporanPrev) {
            // baris 1 kolom 1 - baris 1 kolom 3
            $this->requestMergeCell[] = (Coordinate::stringFromColumnIndex($coordStartX + 1)).($coordStartY + 1).':'.(Coordinate::stringFromColumnIndex($coordStartX + 3)).($coordStartY + 1);
            $tableData[] = [$memberGrowthPrev, $memberGrowth, $memberGrowthDiff];
        } else {
            $tableData[] = ['GROWTH MEMBER', $memberGrowth];
        }

        $finalTable = array_merge($tableHeading, $tableData);
        $tableSizeX = !empty($finalTable)? max(array_map('count', $finalTable)): 0;
        $tableSizeY = count($finalTable);

        if($this->laporanPrev) {
            $this->requestNumberFormat[] = [
                'cells' => Coordinate::stringFromColumnIndex($coordStartX + 1).($coordStartY + 3).':'.Coordinate::stringFromColumnIndex($coordStartX + $tableSizeX - 1).($coordStartY + $tableSizeY),
                'formatCode' => '+#0;-#0;0',
            ];
            $this->requestNumberFormat[] = [
                'cells' => Coordinate::stringFromColumnIndex($coordStartX + 3).($coordStartY + 3).':'.Coordinate::stringFromColumnIndex($coordStartX + $tableSizeX).($coordStartY + $tableSizeY),
                'formatCode' => NumberFormat::FORMAT_PERCENTAGE,
            ];
        } else {
            $this->requestNumberFormat[] = [
                'cells' => Coordinate::stringFromColumnIndex($coordStartX + 2).($coordStartY + 1),
                'formatCode' => '+#0;-#0;0',
            ];
        }

        return [
            'table' => $finalTable,
            'sizeX' => $tableSizeX,
            'sizeY' => $tableSizeY,
        ];
    }

    public function array(): array
    {
        return [];
    }

    public function registerEvents(): array
    {
        return [
            BeforeSheet::class => function (BeforeSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // render
                $coordStartX = 0;
                $coordStartY = 0;

                $penjualanTablePrev = null;

                if($this->laporanPrev) {
                    $penjualanTablePrev = $this->renderPenjualanHarianTable($this->laporanPrev, $coordStartX, $coordStartY);
                    $sheet->fromArray($penjualanTablePrev['table'] ?? [], null, Coordinate::stringFromColumnIndex($coordStartX + 1).($coordStartY + 1));

                    $coordStartX = $coordStartX + $penjualanTablePrev['sizeX'] + 1;
                }

                $penjualanTable = $this->renderPenjualanHarianTable($this->laporan, $coordStartX, $coordStartY);
                $sheet->fromArray($penjualanTable['table'] ?? [], null, Coordinate::stringFromColumnIndex($coordStartX + 1).($coordStartY + 1));

                $coordStartX = $coordStartX + $penjualanTable['sizeX'] + 1;

                $coordStartY = $coordStartY + 1;

                $totalPenjualanTable = $this->renderTotalPenjualanTable($coordStartX, $coordStartY);
                $sheet->fromArray($totalPenjualanTable['table'] ?? [], null, Coordinate::stringFromColumnIndex($coordStartX + 1).($coordStartY + 1));

                $coordStartY = $coordStartY+ $totalPenjualanTable['sizeY'] + 1;

                if($this->laporanPrev) {
                    $customerGrowthTable = $this->renderCustomerGrowthTable($coordStartX, $coordStartY);
                    $sheet->fromArray($customerGrowthTable['table'] ?? [], null, Coordinate::stringFromColumnIndex($coordStartX + 1).($coordStartY + 1));

                    $coordStartY = $coordStartY + $customerGrowthTable['sizeY'] + 1;
                }

                $memberGrowthTable = $this->renderMemberGrowthTable($coordStartX, $coordStartY);
                $sheet->fromArray($memberGrowthTable['table'] ?? [], null, Coordinate::stringFromColumnIndex($coordStartX + 1).($coordStartY + 1));

            },
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                foreach($this->requestNumberFormat as $selectedCells) {
                    $sheet->getStyle($selectedCells['cells'])->getNumberFormat()->setFormatCode($selectedCells['formatCode']);
                }

                foreach($this->requestMergeCell as $selectedCells) {
                    $sheet->mergeCells($selectedCells);
                }

                foreach($this->requestEnableColAutoWidth as $columnIndex) {
                    $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($columnIndex + 1))->setAutoSize(true);
                }

                $sheet->setSelectedCell('A1');
            },
        ];
    }

    public function title(): string
    {
        return 'DATA';
    }
}
