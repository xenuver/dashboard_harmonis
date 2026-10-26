<?php

namespace App\Exports\ExcelExports;

use App\Models\DataTransaksi;
use App\Models\LaporanUpload;
use App\Models\MemberCount;
use App\Models\SalesHarian;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use Illuminate\Support\Carbon;

class DashboardExcelExport implements FromArray, WithEvents
{
    protected LaporanUpload $laporan;
    protected ?LaporanUpload $laporanPrev;
    protected DataTransaksi $dataTransaksi;
    protected MemberCount $memberCount;

    protected Builder $salesHarian;
    protected ?Builder $salesHarianPrev;

    protected int $kpiTotalPenjualanPrev;
    protected int|string $kpiTotalGrowthDiff;

    protected string $bulanLaporan;
    protected int $kpiTotalPenjualan;
    protected float|string $kpiTotalGrowth;
    protected int $kpiJumlahTransaksi;
    protected int $kpiGrowthMember;

    protected float $avgTransactionPerDay;
    protected float $avgTransactionCountPerDay;
    protected float $basketSizePerDay;
    protected float $avgGrowthPerDay;

    protected array $bulanString = [
        'JANUARI', 'FEBRUARI', 'MARET', 'APRIL', 'MEI', 'JUNI', 'JULI', 'AGUSTUS', 'SEPTEMBER', 'OKTOBER', 'NOVEMBER', 'DESEMBER'
    ];

    public function __construct(LaporanUpload $laporan)
    {
        $this->laporan = $laporan;
        $prevDate = Carbon::createFromDate($laporan->periode_tahun, $laporan->periode_bulan, 1)->subMonth();
        $this->laporanPrev = LaporanUpload::jenisLaporan($laporan->jenis_laporan)
            ->periode($prevDate->month, $prevDate->year)
            ->first();

        $this->salesHarian = SalesHarian::where('upload_id', $laporan->id);
        if($this->laporanPrev) {
            $this->salesHarianPrev = SalesHarian::where('upload_id', $this->laporanPrev->id);
        }

        $this->dataTransaksi = DataTransaksi::where('upload_id', $laporan->id)->first();

        $this->memberCount = MemberCount::where('upload_id', $laporan->id)->first();

        $this->bulanLaporan = $this->bulanString[$this->laporan->periode_bulan - 1];

        $this->kpiTotalPenjualan = $this->salesHarian->sum('total_penjualan');
        if($this->laporanPrev) {
            $this->kpiTotalPenjualanPrev = $this->salesHarianPrev->sum('total_penjualan');
        }

        if($this->laporanPrev) {
            $this->kpiTotalGrowth = (($this->kpiTotalPenjualan - $this->kpiTotalPenjualanPrev) / $this->kpiTotalPenjualanPrev) * 100;
            $this->kpiTotalGrowthDiff = $this->kpiTotalPenjualan - $this->kpiTotalPenjualanPrev;
        } else {
            $this->kpiTotalGrowth = "---";
            $this->kpiTotalGrowthDiff = "---";
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
            [null, $this->bulanLaporan, null, null, null, null, $this->kpiTotalPenjualan, null, null, null,null, $this->kpiTotalGrowth, null, null, null, null, $this->kpiJumlahTransaksi, null, null, null, null, $this->kpiGrowthMember],
            [null],
            [null],
            [null, null, null, null, null, null, null, null, null, null, null, $this->kpiTotalGrowthDiff],
            [null],
            [null],
            [null],
            [null],
            [null],
            [null],
            [null],
            [null],
            [null],
            [null],
            [null],
            [null],
            [null],
            [null],
            [null],
            [null],
            [null],
            [null],
            [null],
            [null],
            [null],
            [null],
            [null],
            [null],
            [null],
            [null],
            [null],
            [null],
            [null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, 'TOP 300 HIGHEST', null, null, null, 'VIEW'],
            [null],
            [null],
            [null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, 'TOP 300 LOWEST', null, null, null, 'VIEW'],
            [null],
            [null],
            [null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, 'TOP SUPPLIER', null, null, null, 'VIEW'],
            [null],
            [null],
            [null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, 'AVG TRANSACTION/DAY', null, null, null, $this->avgTransactionPerDay],
            [null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, 'AVG TRANSACTION COUNT/DAY', null, null, null, $this->avgTransactionCountPerDay],
            [null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, 'AVG CUST BASKET SIZE/DAY', null, null, null, $this->basketSizePerDay],
            [null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, 'AVG MEMBER GROWTH/DAY', null, null, null, $this->avgGrowthPerDay],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
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

                // what?
                $sheet->mergeCells('T48:W48');
                $sheet->mergeCells('T49:W49');
                $sheet->mergeCells('T50:W50');
                $sheet->mergeCells('T51:W51');

                // huh?
                $sheet->mergeCells('X48:Y48');
                $sheet->mergeCells('X49:Y49');
                $sheet->mergeCells('X50:Y50');
                $sheet->mergeCells('X51:Y51');
            }
        ];
    }

    public function title(): string
    {
        return "VIEW";
    }
}
