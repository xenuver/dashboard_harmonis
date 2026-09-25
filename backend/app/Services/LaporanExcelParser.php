<?php
 
namespace App\Services;
 
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;
 
class LaporanExcelParser
{
    /** @var array Semua sheet, tiap sheet = array baris mentah (0-based) */
    protected array $sheets;
 
    public function __construct(UploadedFile $file)
    {
        // toArray(null, $file) = ambil SEMUA sheet apa adanya, tanpa
        // asumsi header. Kita yang tentukan sendiri baris/kolom mana
        // yang dipakai, sesuai posisi asli di file Excel Phoenix.
        $this->sheets = Excel::toArray(null, $file);
    }
 
    protected function sheet(int $index): array
    {
        return $this->sheets[$index] ?? [];
    }
 
    /**
     * Sheet ke-0 = VIEW.
     * Posisi asli di Excel (1-based): G5=total sales, L5=total growth,
     * Q5=jumlah transaksi, V5=growth member (format "+303").
     * Di array PHP (0-based): baris index 4, kolom index 6/11/16/21.
     */
    public function parseKpiSummary(): array
    {
        $row = $this->sheet(0)[4] ?? [];
 
        return [
            'total_sales'      => (int) ($row[6] ?? 0),
            'total_growth'     => (float) ($row[11] ?? 0),
            'jumlah_transaksi' => (int) ($row[16] ?? 0),
            'growth_member'    => (int) str_replace('+', '', (string) ($row[21] ?? 0)),
        ];
    }
 
    /**
     * Sheet ke-1 = DATA. Baris index 0-1 = header, data mulai index 2.
     * Kolom index 3 = tanggal, index 4 = total penjualan hari itu.
     * Untuk file Gabungan, ada tambahan kolom index 10 & 11 = total
     * per cabang (Ampera & Pal) -- lihat catatan di dokumen PRD.
     */
    public function parseSalesHarian(bool $isGabungan): array
    {
        $result = [];
 
        foreach (array_slice($this->sheet(1), 2) as $row) {
            $tanggal = $row[3] ?? null;
            if ($tanggal === null || $tanggal === '') {
                continue; // baris kosong sisa template
            }
 
            $result[(int) $tanggal] = [
                'total'  => (int) ($row[4] ?? 0),
                'ampera' => $isGabungan ? (int) ($row[10] ?? 0) : null,
                'pal'    => $isGabungan ? (int) ($row[11] ?? 0) : null,
            ];
        }
 
        return $result;
    }
 
    /**
     * Sheet ke-2 = TOP 300 HIGHEST, sheet ke-3 = TOP 300 LOWEST.
     * Baris index 0 = judul, index 1 = header kolom, data mulai index 2.
     */
    public function parseProdukPenjualan(int $sheetIndex, string $kategori): array
    {
        $result = [];
 
        foreach (array_slice($this->sheet($sheetIndex), 2) as $row) {
            if (empty($row[1])) {
                continue; // kode_brg kosong -> baris kosong sisa template
            }
 
            $result[] = [
                'kategori'   => $kategori, // 'tertinggi' | 'terendah'
                'barcode'    => $row[0] ?? null,
                'kode_brg'   => $row[1],
                'nama_brg'   => $row[2],
                'satuan'     => $row[3] ?? null,
                'harga_jual' => (int) ($row[4] ?? 0),
                'jumlah'     => (int) ($row[5] ?? 0),
                'qty'        => (int) ($row[6] ?? 0), // bisa negatif = retur
                'stok'       => isset($row[7]) ? (int) $row[7] : null,
            ];
        }
 
        return $result;
    }
 
    /** Sheet ke-4 = TOP SUPPLIER. Pola sama: judul, header, data. */
    public function parseSupplierPenjualan(): array
    {
        $result = [];
 
        foreach (array_slice($this->sheet(4), 2) as $row) {
            if (empty($row[0])) {
                continue;
            }
 
            $result[] = [
                'kode_supp'       => $row[0],
                'nama_supp'       => $row[1],
                'gross_total'     => (int) ($row[2] ?? 0),
                'net_sales_total' => (int) ($row[3] ?? 0),
            ];
        }
 
        return $result;
    }
}