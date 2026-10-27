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
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class PenjualanHarianExcelExport implements FromCollection, WithColumnFormatting, WithHeadings, WithStrictNullComparison, WithTitle, WithEvents
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
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                foreach($sheet->getColumnIterator() as $column) {
                    $sheet->getColumnDimension($column->getColumnIndex())->setAutoSize(true);
                }
            }
        ];
    }

    public function title(): string
    {
        return 'PENJUALAN HARIAN';
    }
}
