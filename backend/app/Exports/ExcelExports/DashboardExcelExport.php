<?php

namespace App\Exports\ExcelExports;

use App\Models\DataTransaksi;
use App\Models\LaporanUpload;
use App\Models\MemberCount;
use App\Models\SalesHarian;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithCharts;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Chart\Axis;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\GridLines;
use PhpOffice\PhpSpreadsheet\Chart\Layout;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class DashboardExcelExport implements FromArray, WithCharts, WithEvents, WithStrictNullComparison, WithTitle
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

    protected int $tahunLaporan;

    protected int $kpiTotalPenjualan;

    protected float|string $kpiTotalGrowth;

    protected int $kpiJumlahTransaksi;

    protected int $kpiGrowthMember;

    protected float $avgTransactionPerDay;

    protected float $avgTransactionCountPerDay;

    protected float $basketSizeThisMonth;

    protected float $avgGrowthPerDay;

    protected int $minSalesThisMonth;

    protected int $maxSalesThisMonth;

    protected int $diffMonthLength = 1;

    protected string $idrCurrentyFormat = '[$Rp-421] #,##0';

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

        if ($this->laporanPrev) {
            $this->diffMonthLength = $this->diffMonthLength + 1;
        }

        $this->salesHarian = SalesHarian::where('upload_id', $laporan->id);
        if ($this->laporanPrev) {
            $this->salesHarianPrev = SalesHarian::where('upload_id', $this->laporanPrev->id);
        }

        $this->dataTransaksi = DataTransaksi::where('upload_id', $laporan->id)->first();

        $this->memberCount = MemberCount::where('upload_id', $laporan->id)->first();

        $this->bulanLaporan = $this->bulanString[$this->laporan->periode_bulan - 1];
        if ($this->laporanPrev) {
            $this->bulanLaporanPrev = $this->bulanString[$this->laporanPrev->periode_bulan - 1];
        }

        $this->tahunLaporan = $this->laporan->periode_tahun;

        $this->kpiTotalPenjualan = $this->salesHarian->sum('total_penjualan');
        if ($this->laporanPrev) {
            $this->kpiTotalPenjualanPrev = $this->salesHarianPrev->sum('total_penjualan');
        }

        if ($this->laporanPrev) {
            $this->kpiTotalGrowth = ($this->kpiTotalPenjualan - $this->kpiTotalPenjualanPrev) / $this->kpiTotalPenjualanPrev;
            $this->kpiTotalGrowthDiff = $this->kpiTotalPenjualan - $this->kpiTotalPenjualanPrev;
        } else {
            $this->kpiTotalGrowth = '---';
            $this->kpiTotalGrowthDiff = '---';
        }

        $this->kpiJumlahTransaksi = $this->dataTransaksi->getTotalTransaction();

        $this->kpiGrowthMember = $this->laporan->getMemberCount->getNewMemberCount();

        $this->avgTransactionPerDay = $this->salesHarian->sum('total_penjualan') / $this->laporan->jumlah_hari;
        $this->avgTransactionCountPerDay = $this->dataTransaksi->getTotalTransaction() / $this->laporan->jumlah_hari;
        $this->basketSizeThisMonth = $this->salesHarian->sum('total_penjualan') / $this->dataTransaksi->getTotalTransaction();
        $this->avgGrowthPerDay = $this->laporan->getMemberCount->getNewMemberCount() / $this->laporan->jumlah_hari;

        $dailyTotalQuery = (clone $this->salesHarian)->newQuery()->select('tanggal', DB::raw('SUM(total_penjualan) as daily_total'))->groupBy('tanggal');

        $this->minSalesThisMonth = DB::query()->fromSub($dailyTotalQuery, 'daily_summary')->min('daily_total');
        $this->maxSalesThisMonth = DB::query()->fromSub($dailyTotalQuery, 'daily_summary')->max('daily_total');
    }

    private function getColorGoodOrBad(float|int $number): string
    {
        if ($number > 0) {
            return '76933c';
        } else {
            return 'e26b0a';
        }
    }

    public function array(): array
    {
        return [
            [null],
            [null, 'LAPORAN DASHBOARD HARMONIS'],
            [null],
            [null],
            [null],
            [null, 'PERIODE', null, null, null, null, 'TOTAL SALES', null, null, null, null, 'TOTAL GROWTH', null, null, null, null, 'JUMLAH TRANSAKSI', null, null, null, null, 'GROWTH MEMBER'],
            [null, $this->bulanLaporan, null, null, null, null, $this->kpiTotalPenjualan, null, null, null, null, $this->kpiTotalGrowth, null, null, null, null, $this->kpiJumlahTransaksi, null, null, null, null, $this->kpiGrowthMember],
            [null],
            [null],
            [null, $this->tahunLaporan, null, null, null, null, null, null, null, null, null, $this->kpiTotalGrowthDiff], // 10
            [null],
            [null, 'TOTAL PENJUALAN', null, null, null, null, null, null, null, null, null, null, null, null, null, $this->bulanLaporan, $this->salesHarian->sum('total_penjualan'), "TOTAL\nPENJUALAN"],
            [null, 'AMPERA', null, null, null, null, null, null, null, null, null, null, null, null, null, $this->bulanLaporanPrev ?? null, isset($this->salesHarianPrev) ? $this->salesHarianPrev->sum('total_penjualan') : null],
            [null, 'PAL'],
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
            [null, 'NON-MEMBER', $this->laporan->getDataTransaksi->getTotalNonMemberTransaction(), null, null, null, null, null, null, null, null, null, "MEMBER\nBARU", null, null, null, null, null, null, 'TOP 300 HIGHEST', null, null, null, 'VIEW'],
            [null, 'MEMBER', $this->laporan->getDataTransaksi->getTotalMemberTransaction(), null, null, null, null, null, null, null],
            [null],
            [null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, 'TOP 300 LOWEST', null, null, null, 'VIEW'],
            [null],
            [null],
            [null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, 'TOP SUPPLIER', null, null, null, 'VIEW'], // 40
            [null],
            [null],
            [null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, 'AVG TRANSACTION/DAY', null, null, null, $this->avgTransactionPerDay],
            [null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, 'AVG TRANSACTION COUNT/DAY', null, null, null, $this->avgTransactionCountPerDay],
            [null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, 'AVG CUST BASKET SIZE BULAN INI', null, null, null, $this->basketSizeThisMonth],
            [null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, 'AVG MEMBER GROWTH/DAY', null, null, null, $this->avgGrowthPerDay],
            [null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, 'MIN SALES THIS MONTH', null, null, null, $this->minSalesThisMonth],
            [null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, 'MAX SALES THIS MONTH', null, null, null, $this->maxSalesThisMonth],
            [null],
            [null], // 50
            [null],
            [null],
            [null, null, null, null, null, null, null, null, null, null, $this->bulanLaporanPrev ?? null, isset($this->laporanPrev) ? $this->laporanPrev->getMemberCount->getNewMemberCount() : null],
            [null, null, null, null, null, null, null, null, null, null, $this->bulanLaporan, $this->laporan->getMemberCount->getNewMemberCount()]
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
                $sheet->mergeCells('B7:E9');
                $sheet->mergeCells('B10:E10');
                $sheet->mergeCells('G7:J10');
                $sheet->mergeCells('L7:O9');
                $sheet->mergeCells('Q7:T10');
                $sheet->mergeCells('V7:Y10');

                // footer kpi
                $sheet->mergeCells('L10:O10');

                // label peringkat produk dan supplier
                $sheet->mergeCells('T34:W35');
                $sheet->mergeCells('T37:W38');
                $sheet->mergeCells('T40:W41');

                // tombol peringkat produk dan supplier
                $sheet->mergeCells('X34:Y35');
                $sheet->mergeCells('X37:Y38');
                $sheet->mergeCells('X40:Y41');

                // label teks
                $sheet->mergeCells('T43:W43');
                $sheet->mergeCells('T44:W44');
                $sheet->mergeCells('T45:W45');
                $sheet->mergeCells('T46:W46');
                $sheet->mergeCells('T47:W47');
                $sheet->mergeCells('T48:W48');

                // nilai dari label
                $sheet->mergeCells('X43:Y43');
                $sheet->mergeCells('X44:Y44');
                $sheet->mergeCells('X45:Y45');
                $sheet->mergeCells('X46:Y46');
                $sheet->mergeCells('X47:Y47');
                $sheet->mergeCells('X48:Y48');

                // format angka kpi
                $sheet->getStyle('G7')->getNumberFormat()->setFormatCode($this->idrCurrentyFormat);
                $sheet->getStyle('L7')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
                $sheet->getStyle('Q7')->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle('V7')->getNumberFormat()->setFormatCode('+#0;-#0;0');
                $sheet->getStyle('L10')->getNumberFormat()->setFormatCode($this->idrCurrentyFormat);

                // bikin teks judul dan nilai kpi tengah serta shrink to fit buat kpi
                $sheet->getStyle('B2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setShrinkToFit(true);
                $sheet->getStyle('B7')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setShrinkToFit(true);
                $sheet->getStyle('G7')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setShrinkToFit(true);
                $sheet->getStyle('L7')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setShrinkToFit(true);
                $sheet->getStyle('Q7')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setShrinkToFit(true);
                $sheet->getStyle('V7')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setShrinkToFit(true);
                $sheet->getStyle('B10')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setShrinkToFit(true);

                // ubah ukuran teks dari judul dan kpi lebih besar serta ketebalan
                $sheet->getStyle('B2')->getFont()->setSize(22)->setBold(true);
                $sheet->getStyle('B6')->getFont()->setBold(true);
                $sheet->getStyle('G6')->getFont()->setBold(true);
                $sheet->getStyle('L6')->getFont()->setBold(true);
                $sheet->getStyle('Q6')->getFont()->setBold(true);
                $sheet->getStyle('V6')->getFont()->setBold(true);
                $sheet->getStyle('B7')->getFont()->setSize(22)->setBold(true);
                $sheet->getStyle('G7')->getFont()->setSize(22)->setBold(true);
                $sheet->getStyle('L7')->getFont()->setSize(22)->setBold(true);
                $sheet->getStyle('Q7')->getFont()->setSize(22)->setBold(true);
                $sheet->getStyle('V7')->getFont()->setSize(22)->setBold(true);
                $sheet->getStyle('B10')->getFont()->setBold(true);
                $sheet->getStyle('L10')->getFont()->setBold(true);

                // ubah warna fill dan font dari judul dan card kpi
                $sheet->getStyle('B2')->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => [
                            'rgb' => '92d050',
                        ],
                    ],
                    'font' => [
                        'color' => ['rgb' => 'ffffff'],
                    ],
                ]);
                $sheet->getStyle('B6:B10')->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => [
                            'rgb' => '305496',
                        ],
                    ],
                    'font' => [
                        'color' => ['rgb' => 'ffffff'],
                    ],
                ]);
                $sheet->getStyle('G6:G10')->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => [
                            'rgb' => '305496',
                        ],
                    ],
                    'font' => [
                        'color' => ['rgb' => 'ffffff'],
                    ],
                ]);
                $sheet->getStyle('L6:L10')->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => [
                            'rgb' => $this->getColorGoodOrBad(is_numeric($this->kpiTotalGrowth) ? $this->kpiTotalGrowth : -1),
                        ],
                    ],
                    'font' => [
                        'color' => ['rgb' => 'ffffff'],
                    ],
                ]);
                $sheet->getStyle('Q6:Q10')->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => [
                            'rgb' => '305496',
                        ],
                    ],
                    'font' => [
                        'color' => ['rgb' => 'ffffff'],
                    ],
                ]);
                $sheet->getStyle('V6:V10')->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => [
                            'rgb' => $this->getColorGoodOrBad(is_numeric($this->kpiGrowthMember) ? $this->kpiGrowthMember : -1),
                        ],
                    ],
                    'font' => [
                        'color' => ['rgb' => 'ffffff'],
                    ],
                ]);

                // format bagian bawah
                $rankUrlLabelStyle = [
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => [
                            'rgb' => 'e2efda',
                        ],
                    ],
                    'font' => [
                        'size' => 18,
                    ],
                ];

                $rankUrlButtonStyle = [
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => [
                            'rgb' => 'ffe699',
                        ],
                    ],
                    'font' => [
                        'size' => 14,
                        'color' => [
                            'rgb' => '0563c1',
                        ],
                        'underline' => Font::UNDERLINE_SINGLE,
                    ],
                ];

                $sheet->getStyle('T34')->applyFromArray($rankUrlLabelStyle);
                $sheet->getStyle('T37')->applyFromArray($rankUrlLabelStyle);
                $sheet->getStyle('T40')->applyFromArray($rankUrlLabelStyle);

                $sheet->getCell('X34')->getHyperlink()->setUrl("sheet://'TOP 300 HIGHEST'!A1");
                $sheet->getCell('X37')->getHyperlink()->setUrl("sheet://'TOP 300 LOWEST'!A1");
                $sheet->getCell('X40')->getHyperlink()->setUrl("sheet://'TOP SUPPLIER'!A1");
                $sheet->getStyle('X34')->applyFromArray($rankUrlButtonStyle);
                $sheet->getStyle('X37')->applyFromArray($rankUrlButtonStyle);
                $sheet->getStyle('X40')->applyFromArray($rankUrlButtonStyle);

                $sheet->getStyle('X43')->getNumberFormat()->setFormatCode($this->idrCurrentyFormat);
                $sheet->getStyle('X44')->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle('X45')->getNumberFormat()->setFormatCode($this->idrCurrentyFormat);
                $sheet->getStyle('X46')->getNumberFormat()->setFormatCode('+#0;-#0;0');
                $sheet->getStyle('X47')->getNumberFormat()->setFormatCode($this->idrCurrentyFormat);
                $sheet->getStyle('X48')->getNumberFormat()->setFormatCode($this->idrCurrentyFormat);

                $sheet->getStyle('T34:Y35')->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => [
                                'rgb' => '000000',
                            ],
                        ],
                    ],
                ]);

                $sheet->getStyle('T37:Y38')->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => [
                                'rgb' => '000000',
                            ],
                        ],
                    ],
                ]);

                $sheet->getStyle('T40:Y41')->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => [
                                'rgb' => '000000',
                            ],
                        ],
                    ],
                ]);

                $sheet->setCellValue('Y54', 'Generated: '.date('d-m-Y H:i:s.ms T (P)'));
                $sheet->getStyle('Y54')->applyFromArray([
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_RIGHT,
                    ],
                ]);

                $sheet->setSelectedCell('A1');
            },
        ];
    }

    public function buildDailySalesChart(): Chart
    {
        $dataSeriesLabels = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, '\'VIEW\'!$B$12', null, 1),
        ];
        $labels = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, '\'PENJUALAN HARIAN\'!$A$2:$A$'.($this->laporan->jumlah_hari + 1), null, 1, ['TOTAL PENJUALAN']),
        ];
        $values = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, '\'PENJUALAN HARIAN\'!$B$2:$B$'.($this->laporan->jumlah_hari + 1), $this->idrCurrentyFormat, 5),
        ];

        if ($this->laporan->jenis_laporan === 'gabungan') {
            // ampera
            $dataSeriesLabels[] = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, '\''.($this->sheetName).'\'!$B$13', null, 1);
            $labels[] = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, '\'PENJUALAN HARIAN\'!$A$2:$A$'.($this->laporan->jumlah_hari + 1), null, 1);
            $values[] = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, '\'PENJUALAN HARIAN\'!$C$2:$C$'.($this->laporan->jumlah_hari + 1), $this->idrCurrentyFormat, 5);

            // pal
            $dataSeriesLabels[] = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, '\''.($this->sheetName).'\'!$B$14', null, 1);
            $labels[] = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, '\'PENJUALAN HARIAN\'!$A$2:$A$'.($this->laporan->jumlah_hari + 1), null, 1);
            $values[] = new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, '\'PENJUALAN HARIAN\'!$D$2:$D$'.($this->laporan->jumlah_hari + 1), $this->idrCurrentyFormat, 5);
        }

        $series = new DataSeries(
            DataSeries::TYPE_LINECHART,
            DataSeries::GROUPING_STANDARD,
            range(0, count($values) - 1),
            $dataSeriesLabels,
            $labels,
            $values
        );

        $layout = new Layout;
        $layout->setShowVal(false);
        $layout->setNumFmtCode($this->idrCurrentyFormat);

        $plotArea = new PlotArea($layout, [$series]);
        $legend = new Legend(Legend::POSITION_BOTTOM, null, false);
        $chart = new Chart('totalsalesdaily', new Title('SALES DAILY'), $legend, $plotArea);

        $chart->setTopLeftPosition('B12', 0, 0);
        $chart->setBottomRightPosition('O33', 0, 0);
        $chart->getChartAxisY()->setAxisOption('minimum', '0');
        $chart->getChartAxisY()->setAxisNumberProperties($this->idrCurrentyFormat);

        $chart->getChartAxisX()->setAxisOption('major_tick_mark', Axis::TICK_MARK_CROSS);
        $chart->getChartAxisY()->setAxisOption('major_tick_mark', Axis::TICK_MARK_CROSS);

        $yGridLines = new GridLines;
        $yGridLines->activateObject();
        $yGridLines->setLineColorProperties('d9d9d9');
        $chart->getChartAxisY()->setMajorGridlines($yGridLines);

        return $chart;
    }

    public function buildMonthlySalesChart(): Chart
    {
        $dataSeriesLabels = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, '\''.($this->sheetName).'\'!$R$12', null, 1),
        ];
        $labels = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, '\''.($this->sheetName).'\'!$P$12:$P$'.($this->diffMonthLength + 11), null, 1),
        ];
        $values = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, '\''.($this->sheetName).'\'!$Q$12:$Q$'.($this->diffMonthLength + 11), $this->idrCurrentyFormat, 5),
        ];

        $series = new DataSeries(
            DataSeries::TYPE_BARCHART,
            DataSeries::GROUPING_STANDARD,
            range(0, count($values) - 1),
            $dataSeriesLabels,
            $labels,
            $values
        );
        $series->setPlotDirection(DataSeries::DIRECTION_HORIZONTAL);

        $layout = new Layout;
        $layout->setShowVal(true);
        $layout->setNumFmtCode($this->idrCurrentyFormat);

        $plotArea = new PlotArea($layout, [$series]);
        $legend = new Legend(Legend::POSITION_RIGHT, null, false);
        $chart = new Chart('totalsalesmonthly', new Title('TOTAL SALES MONTHLY'), $legend, $plotArea);

        $chart->setTopLeftPosition('P12', 0, 0);
        $chart->setBottomRightPosition('Z33', 0, 0);
        $chart->getChartAxisY()->setAxisOption('minimum', '0');
        $chart->getChartAxisY()->setAxisNumberProperties($this->idrCurrentyFormat);

        $chart->getChartAxisX()->setAxisOption('major_tick_mark', Axis::TICK_MARK_CROSS);
        $chart->getChartAxisY()->setAxisOption('major_tick_mark', Axis::TICK_MARK_CROSS);

        $yGridLines = new GridLines;
        $yGridLines->activateObject();
        $yGridLines->setLineColorProperties('d9d9d9');
        $chart->getChartAxisY()->setMajorGridlines($yGridLines);

        return $chart;
    }

    public function buildTransactionInfoChart(): Chart
    {
        $labels = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, '\''.($this->sheetName).'\'!$B$34:$B$35', null, 1),
        ];
        $values = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, '\''.($this->sheetName).'\'!$C$34:$C$35', null, 5),
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
        $layout->setShowLegendKey(true);

        $plotArea = new PlotArea($layout, [$series]);
        $legend = new Legend(Legend::POSITION_RIGHT, null, false);
        $chart = new Chart('transaksimembervsnonmember', new Title('TRANSAKSI MEMBER VS NON-MEMBER'), $legend, $plotArea);

        $chart->setTopLeftPosition('B34', 0, 0);
        $chart->setBottomRightPosition('J55', 0, 0);

        return $chart;
    }

    public function buildMemberGrowthChart(): Chart
    {
        $dataSeriesLabels = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, '\''.($this->sheetName).'\'!$M$34', null, 1),
        ];
        $labels = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, '\''.($this->sheetName).'\'!$K$54:$K$'.(55 - $this->diffMonthLength), null, 1),
        ];
        $values = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, '\''.($this->sheetName).'\'!$L$54:$L$'.(55 - $this->diffMonthLength), null, 1),
        ];

        $series = new DataSeries(
            DataSeries::TYPE_BARCHART,
            DataSeries::GROUPING_STANDARD,
            range(0, count($values) - 1),
            $dataSeriesLabels,
            $labels,
            $values
        );
        $series->setPlotDirection(DataSeries::DIRECTION_COLUMN);

        $layout = new Layout;
        $layout->setShowVal(true);
        $layout->setNumFmtCode('+#0;-#0;0');

        $plotArea = new PlotArea($layout, [$series]);
        $legend = new Legend(Legend::POSITION_RIGHT, null, false);
        $chart = new Chart('membergrowth', new Title('MEMBER GROWTH'), $legend, $plotArea);

        $chart->setTopLeftPosition('K34', 0, 0);
        $chart->setBottomRightPosition('S55', 0, 0);
        $chart->getChartAxisY()->setAxisOption('minimum', '0');
        $chart->getChartAxisY()->setAxisNumberProperties('+#0;-#0;0', true);

        $chart->getChartAxisX()->setAxisOption('major_tick_mark', Axis::TICK_MARK_CROSS);
        $chart->getChartAxisY()->setAxisOption('major_tick_mark', Axis::TICK_MARK_CROSS);

        $yGridLines = new GridLines;
        $yGridLines->activateObject();
        $yGridLines->setLineColorProperties('d9d9d9');
        $chart->getChartAxisY()->setMajorGridlines($yGridLines);

        return $chart;
    }

    public function charts(): array
    {
        return [
            $this->buildMonthlySalesChart(),
            $this->buildTransactionInfoChart(),
            $this->buildMemberGrowthChart(),
            $this->buildDailySalesChart(),
        ];
    }

    public function title(): string
    {
        return $this->sheetName;
    }
}
