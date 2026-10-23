<?php

namespace App\Exports\ExcelExports;

use App\Models\LaporanUpload;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class TopProductsExcelExport extends DefaultValueBinder implements 
    FromQuery, 
    WithHeadings, 
    WithMapping, 
    WithTitle, 
    WithColumnFormatting, 
    WithCustomValueBinder,
    WithStrictNullComparison
{
    protected LaporanUpload $laporan;

    public function __construct(LaporanUpload $laporan)
    {
        $this->laporan = $laporan;
    }

    public function bindValue(Cell $cell, mixed $value): bool
    {
        // Format product code (A) and barcode (G) as text without warning flags
        if (in_array($cell->getColumn(), ['A', 'G'], true)) {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);
            $cell->getIgnoredErrors()->setNumberStoredAsText(true);
            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function query(): Relation|EloquentBuilder
    {
        return $this->laporan->produkPenjualan()->tertinggi();
    }

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'B' => NumberFormat::FORMAT_TEXT,
            'C' => NumberFormat::FORMAT_TEXT,
            'D' => NumberFormat::FORMAT_NUMBER,
            'E' => '[$Rp-421] #,##0',
            'F' => '[$Rp-421] #,##0',
            'G' => NumberFormat::FORMAT_TEXT,
            'H' => NumberFormat::FORMAT_NUMBER,
        ];
    }

    public function headings(): array
    {
        return [
            'KODE_BRG',
            'NAMA_BRG',
            'SATUAN',
            'QTY',
            'HRG_JUAL',
            'JUMLAH',
            'BARCODE',
            'STOK',
        ];
    }

    public function map($produk): array
    {
        return [
            $produk->kode_brg,
            $produk->nama_brg,
            $produk->satuan,
            (int) $produk->qty,
            (float) $produk->harga_jual,
            (float) $produk->jumlah,
            $produk->barcode,
            (int) $produk->stok,
        ];
    }

    public function title(): string
    {
        return "TOP 300 HIGHEST";
    }
}