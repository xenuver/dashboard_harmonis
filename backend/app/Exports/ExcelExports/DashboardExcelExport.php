<?php

namespace App\Exports\ExcelExports;

use App\Models\DataTransaksi;
use App\Models\LaporanUpload;
use App\Models\MemberCount;
use App\Models\SalesHarian;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithCharts;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Layout;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;

class DashboardExcelExport implements FromArray, WithCharts, WithEvents, WithTitle
{
    protected LaporanUpload $laporan;

    protected ?LaporanUpload $laporanPrev;

    protected DataTransaksi $dataTransaksi;

    protected MemberCount $memberCount;

    protected Builder $salesHarian;

    protected ?Builder $salesHarianPrev;

    protected int $kpiTotalPenjualanPrev;

    protected string $kpiTotalGrowthDiff;

    protected string $sheetName = 'VIEW';

    protected string $bulanLaporan;

    protected string $bulanLaporanPrev;

    protected int $kpiTotalPenjualan;

    protected float|string $kpiTotalGrowth;

    protected int $kpiJumlahTransaksi;

    protected int $kpiGrowthMember;

    protected float $avgTransactionPerDay;

    protected float $avgTransactionCountPerDay;

    protected float $basketSizePerDay;

    protected float $avgGrowthPerDay;

    protected array $bulanString = [
        'JANUARI', 'FEBRUARI', 'MARET', 'APRIL', 'MEI', 'JUNI', 'JULI', 'AGUSTUS', 'SEPTEMBER', 'OKTOBER', 'NOVEMBER', 'DESEMBER',
    ];

    public function __construct(LaporanUpload $laporan)
    {
        $this->laporan = $laporan;
        $prevDate = Carbon::createFromDate($laporan->periode_tahun, $laporan->periode_bulan, 1)->subMonth();
        $this->laporanPrev = LaporanUpload::jenisLaporan($laporan->jenis_laporan)
            ->periode($prevDate->month, $prevDate->year)
            ->first();

        $this->salesHarian = SalesHarian::where('upload_id', $laporan->id);
        if ($this->laporanPrev) {
            $this->salesHarianPrev = SalesHarian::where('upload_id', $this->laporanPrev->id);
        }

        $this->dataTransaksi = DataTransaksi::where('upload_id', $laporan->id)->first();

        $this->memberCount = MemberCount::where('upload_id', $laporan->id)->first();

        $this->bulanLaporan = $this->bulanString[$this->laporan->periode_bulan - 1];
        $this->bulanLaporanPrev = $this->bulanString[$this->laporanPrev->periode_bulan - 1];

        $this->kpiTotalPenjualan = $this->salesHarian->sum('total_penjualan');
        if ($this->laporanPrev) {
            $this->kpiTotalPenjualanPrev = $this->salesHarianPrev->sum('total_penjualan');
        }

        if ($this->laporanPrev) {
            $this->kpiTotalGrowth = (($this->kpiTotalPenjualan - $this->kpiTotalPenjualanPrev) / $this->kpiTotalPenjualanPrev) * 100;
            $this->kpiTotalGrowthDiff = $this->kpiTotalPenjualan - $this->kpiTotalPenjualanPrev;
        } else {
            $this->kpiTotalGrowth = '---';
            $this->kpiTotalGrowthDiff = '---';
        }

        $this->kpiJumlahTransaksi = $this->dataTransaksi->getTotalTransaction();

        $this->kpiGrowthMember = $this->laporan->getMemberCount->getNewMemberCount();

        $this->avgTransactionPerDay = $this->salesHarian->sum('total_penjualan') / $this->laporan->jumlah_hari;
        $this->avgTransactionCountPerDay = $this->dataTransaksi->getTotalTransaction() / $this->laporan->jumlah_hari;
        $this->basketSizePerDay = $this->salesHarian->sum('total_penjualan') / $this->dataTransaksi->getTotalTransaction();
        $this->avgGrowthPerDay = $this->laporan->getMemberCount->getNewMemberCount() / $this->laporan->jumlah_hari;
    }

    public function array(): array
    {
        return [
            [null],
            [null, 'LAPORAN DASHBOARD HARMONIS'],
            [null],
            [null],
            [null],
            [null, 'BULAN', null, null, null, null, 'TOTAL SALES', null, null, null, null, 'TOTAL GROWTH', null, null, null, null, 'JUMLAH TRANSAKSI', null, null, null, null, 'GROWTH MEMBER'],
            [null, $this->bulanLaporan, null, null, null, null, $this->kpiTotalPenjualan, null, null, null, null, $this->kpiTotalGrowth, null, null, null, null, $this->kpiJumlahTransaksi, null, null, null, null, $this->kpiGrowthMember],
            [null],
            [null],
            [null, null, null, null, null, null, null, null, null, null, null, $this->kpiTotalGrowthDiff], // 10
            [null],
            [null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, $this->bulanLaporanPrev, $this->salesHarianPrev->sum('total_penjualan')],
            [null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, $this->bulanLaporan, $this->salesHarian->sum('total_penjualan')],
            [null],
            [null],
            [null],
            [null],
            [null],
            [null],
            [null], // 20
            [null],
            [null],
            [null],
            [null],
            [null],
            [null],
            [null],
            [null],
            [null],
            [null], // 30
            [null],
            [null],
            [null],
            [null],
            [null],
            [null],
            [null],
            [null],
            [null, 'non-member', $this->laporan->getDataTransaksi->getTotalMemberTransaction(), null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, 'TOP 300 HIGHEST', null, null, null, 'VIEW'],
            [null, 'member', $this->laporan->getDataTransaksi->getTotalNonMemberTransaction()], // 40
            [null],
            [null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, 'TOP 300 LOWEST', null, null, null, 'VIEW'],
            [null],
            [null],
            [null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, 'TOP SUPPLIER', null, null, null, 'VIEW'],
            [null],
            [null],
            [null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, 'AVG TRANSACTION/DAY', null, null, null, $this->avgTransactionPerDay],
            [null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, 'AVG TRANSACTION COUNT/DAY', null, null, null, $this->avgTransactionCountPerDay],
            [null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, 'AVG CUST BASKET SIZE/DAY', null, null, null, $this->basketSizePerDay], // 50
            [null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, 'AVG MEMBER GROWTH/DAY', null, null, null, $this->avgGrowthPerDay],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // judul
                $sheet->mergeCells('B2:Y4');

                // header kpi
                $sheet->mergeCells('B6:E6');
                $sheet->mergeCells('G6:J6');
                $sheet->mergeCells('L6:O6');
                $sheet->mergeCells('Q6:T6');
                $sheet->mergeCells('V6:Y6');

                // nilai kpi
                $sheet->mergeCells('B7:E10');
                $sheet->mergeCells('G7:J10');
                $sheet->mergeCells('L7:O9');
                $sheet->mergeCells('Q7:T10');
                $sheet->mergeCells('V7:Y10');

                // footer kpi
                $sheet->mergeCells('L10:O10');

                // label peringkat produk dan supplier
                $sheet->mergeCells('T39:W40');
                $sheet->mergeCells('T42:W43');
                $sheet->mergeCells('T45:W46');

                // tombol peringkat produk dan supplier
                $sheet->mergeCells('X39:Y40');
                $sheet->mergeCells('X42:Y43');
                $sheet->mergeCells('X45:Y46');

                // label teks
                $sheet->mergeCells('T48:W48');
                $sheet->mergeCells('T49:W49');
                $sheet->mergeCells('T50:W50');
                $sheet->mergeCells('T51:W51');

                // nilai dari label
                $sheet->mergeCells('X48:Y48');
                $sheet->mergeCells('X49:Y49');
                $sheet->mergeCells('X50:Y50');
                $sheet->mergeCells('X51:Y51');
            },
        ];
    }

    public function buildMonthlySalesChart(): Chart
    {
        $labels = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, '\''.($this->sheetName).'\'!$P$12:$P$13', null, 1),
        ];
        $values = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, '\''.($this->sheetName).'\'!$Q$12:$Q$13', '[$Rp-421] #,##0', 5),
        ];

        $series = new DataSeries(
            DataSeries::TYPE_BARCHART,
            DataSeries::GROUPING_STANDARD,
            range(0, count($values) - 1),
            [],
            $labels,
            $values
        );
        $series->setPlotDirection(DataSeries::DIRECTION_HORIZONTAL);

        $layout = new Layout;
        $layout->setShowVal(true);
        $layout->setNumFmtCode('[$Rp-421] #,##0');

        $plotArea = new PlotArea($layout, [$series]);
        $legend = new Legend(Legend::POSITION_RIGHT, null, false);
        $chart = new Chart('totalsalesmonthly', new Title('TOTAL SALES MONTHLY'), $legend, $plotArea);

        $chart->setTopLeftPosition('P12');
        $chart->setBottomRightPosition('Y35');
        $chart->getChartAxisY()->setAxisOption('minimum', '0');
        $chart->getChartAxisY()->setAxisNumberProperties('[$Rp-421] #,##0');

        return $chart;
    }

    public function buildTransactionInfoChart(): Chart
    {
        $labels = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, '\''.($this->sheetName).'\'!$B$39:$B$40', null, 1),
        ];
        $values = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, '\''.($this->sheetName).'\'!$C$39:$C$40', null, 5),
        ];

        $series = new DataSeries(
            DataSeries::TYPE_PIECHART,
            DataSeries::GROUPING_STANDARD,
            range(0, count($values) - 1),
            [],
            $labels,
            $values
        );
        $series->setPlotDirection(DataSeries::DIRECTION_HORIZONTAL);

        $layout = new Layout;
        $layout->setShowCatName(true);
        $layout->setShowPercent(true);
        $layout->setShowVal(false);
        $layout->setShowLeaderLines(true);
        $layout->setDLblPos('outEnd');

        $plotArea = new PlotArea($layout, [$series]);
        $legend = new Legend(Legend::POSITION_RIGHT, null, false);
        $chart = new Chart('transaksimembervsnonmember', new Title('TRANSAKSI MEMBER VS NON-MEMBER'), $legend, $plotArea);

        $chart->setTopLeftPosition('B39');
        $chart->setBottomRightPosition('I53');

        return $chart;
    }

    public function charts(): array
    {
        return [
            $this->buildMonthlySalesChart(),
            $this->buildTransactionInfoChart(),
        ];
    }

    public function title(): string
    {
        return $this->sheetName;
    }
}
