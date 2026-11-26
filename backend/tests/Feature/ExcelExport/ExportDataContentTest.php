<?php

namespace Tests\Feature\ExcelExport;

use App\Exports\SalesDashboardExport;
use App\Models\Cabang;
use App\Models\DataTransaksi;
use App\Models\LaporanUpload;
use App\Models\MemberCount;
use App\Models\ProdukPenjualan;
use App\Models\SalesHarian;
use App\Models\SupplierPenjualan;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ExportDataContentTest extends TestCase
{
    use RefreshDatabase;

    private const EXPECTED_SHEET_TITLES = [
        'VIEW',
        'DATA',
        'PENJUALAN HARIAN',
        'TOP 300 HIGHEST',
        'TOP 300 LOWEST',
        'TOP SUPPLIER',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        // DatabaseSeeder creates Cabang rows (AMPERA, PAL) needed for SalesHarian
        $this->seed();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function loadExportedSpreadsheet(LaporanUpload $laporan): Spreadsheet
    {
        $rawBinary = Excel::raw(new SalesDashboardExport($laporan), ExcelFormat::XLSX);

        $this->assertNotEmpty($rawBinary);

        $filename = sprintf(
            'sales-report-%d-%s-%d-%d.xlsx',
            $laporan->id,
            $laporan->jenis_laporan,
            $laporan->periode_bulan,
            $laporan->periode_tahun,
        );

        $storage = Storage::fake('local');
        $storage->put($filename, $rawBinary);
        $storage->assertExists($filename);

        return IOFactory::load($storage->path($filename));
    }

    /**
     * @param  array<int, array{ampera?: int, pal?: int, total?: int}>  $dailySales  1-indexed day => amounts
     */
    private function seedReportWithDailySales(
        string $jenisLaporan,
        int $bulan,
        int $tahun,
        array $dailySales,
        int $transaksiMember,
        int $transaksiNonMember,
        int $memberBaru,
    ): LaporanUpload {
        $jumlahHari = Carbon::createFromDate($tahun, $bulan, 1)->daysInMonth;

        $laporan = LaporanUpload::factory()
            ->jenis_laporan($jenisLaporan)
            ->periode($bulan, $tahun)
            ->create([
                'jumlah_hari' => $jumlahHari,
            ]);

        $cabangAmpera = Cabang::where('kode', 'AMPERA')->firstOrFail();
        $cabangPal = Cabang::where('kode', 'PAL')->firstOrFail();

        foreach ($dailySales as $day => $amounts) {
            $tanggal = sprintf('%04d-%02d-%02d', $tahun, $bulan, $day);

            if ($jenisLaporan === 'gabungan') {
                SalesHarian::create([
                    'upload_id' => $laporan->id,
                    'cabang_id' => $cabangAmpera->id,
                    'tanggal' => $tanggal,
                    'total_penjualan' => $amounts['ampera'],
                ]);
                SalesHarian::create([
                    'upload_id' => $laporan->id,
                    'cabang_id' => $cabangPal->id,
                    'tanggal' => $tanggal,
                    'total_penjualan' => $amounts['pal'],
                ]);
            } else {
                $cabangId = $jenisLaporan === 'ampera' ? $cabangAmpera->id : $cabangPal->id;
                SalesHarian::create([
                    'upload_id' => $laporan->id,
                    'cabang_id' => $cabangId,
                    'tanggal' => $tanggal,
                    'total_penjualan' => $amounts['total'],
                ]);
            }
        }

        DataTransaksi::create([
            'upload_id' => $laporan->id,
            'transaksi_member' => $transaksiMember,
            'transaksi_non_member' => $transaksiNonMember,
        ]);

        MemberCount::create([
            'upload_id' => $laporan->id,
            'member_baru' => $memberBaru,
        ]);

        return $laporan;
    }

    /**
     * Builds uniform daily sales across all days of the month, with optional per-day overrides.
     *
     * @param  array<int, array{ampera?: int, pal?: int, total?: int}>  $overrides
     * @return array<int, array{ampera?: int, pal?: int, total?: int}>
     */
    private function buildMonthlyDailySales(
        string $jenisLaporan,
        int $bulan,
        int $tahun,
        int $defaultAmperaOrTotal,
        int $defaultPal = 0,
        array $overrides = [],
    ): array {
        $daysInMonth = Carbon::createFromDate($tahun, $bulan, 1)->daysInMonth;
        $sales = [];

        for ($day = 1; $day <= $daysInMonth; $day++) {
            if (isset($overrides[$day])) {
                $sales[$day] = $overrides[$day];
            } elseif ($jenisLaporan === 'gabungan') {
                $sales[$day] = [
                    'ampera' => $defaultAmperaOrTotal,
                    'pal' => $defaultPal,
                ];
            } else {
                $sales[$day] = [
                    'total' => $defaultAmperaOrTotal,
                ];
            }
        }

        return $sales;
    }

    // -------------------------------------------------------------------------
    // Workbook structure & visibility
    // -------------------------------------------------------------------------

    public function test_workbook_has_expected_six_sheets_in_order_and_hides_penjualan_harian(): void
    {
        $dailySales = $this->buildMonthlyDailySales('gabungan', 11, 2026, 1_200_000, 800_000);
        $laporan = $this->seedReportWithDailySales('gabungan', 11, 2026, $dailySales, 300, 200, 45);

        $spreadsheet = $this->loadExportedSpreadsheet($laporan);

        $this->assertSame(6, $spreadsheet->getSheetCount());
        $this->assertSame(self::EXPECTED_SHEET_TITLES, $spreadsheet->getSheetNames());

        $this->assertSame(
            Worksheet::SHEETSTATE_HIDDEN,
            $spreadsheet->getSheetByName('PENJUALAN HARIAN')->getSheetState()
        );

        foreach (['VIEW', 'DATA', 'TOP 300 HIGHEST', 'TOP 300 LOWEST', 'TOP SUPPLIER'] as $visibleTitle) {
            $this->assertSame(
                Worksheet::SHEETSTATE_VISIBLE,
                $spreadsheet->getSheetByName($visibleTitle)->getSheetState(),
                "Expected sheet {$visibleTitle} to be visible"
            );
        }
    }

    // -------------------------------------------------------------------------
    // Single-month gabungan export (no previous month)
    // -------------------------------------------------------------------------

    public function test_single_month_gabungan_export_populates_all_sheets_accurately(): void
    {
        // November 2026 has 30 days:
        // Day 1: Ampera 600,000 + Pal 400,000 = 1,000,000 (min day)
        // Day 15: Ampera 2,500,000 + Pal 1,500,000 = 4,000,000 (max day)
        // Other 28 days: Ampera 1,200,000 + Pal 800,000 = 2,000,000 each
        // Total Sales = 1,000,000 + 4,000,000 + (28 * 2,000,000) = 61,000,000 -> no, pick exact divisible totals:
        // Make Day 1 = 900,000 (500k + 400k), Day 15 = 3,100,000 (2,000k + 1,100k), 28 days = 2,000,000 (1,200k + 800k)
        // Total = 900,000 + 3,100,000 + 56,000,000 = 60,000,000
        // Avg/day = 60,000,000 / 30 = 2,000,000
        // Transactions: 360 member + 240 non-member = 600 total -> avg tx/day = 20, basket size = 100,000
        // Member growth: 90 -> avg member growth/day = 3
        $dailySales = $this->buildMonthlyDailySales('gabungan', 11, 2026, 1_200_000, 800_000, [
            1 => ['ampera' => 500_000, 'pal' => 400_000],
            15 => ['ampera' => 2_000_000, 'pal' => 1_100_000],
        ]);

        $laporan = $this->seedReportWithDailySales('gabungan', 11, 2026, $dailySales, 360, 240, 90);

        ProdukPenjualan::create([
            'upload_id' => $laporan->id,
            'kategori' => 'tertinggi',
            'kode_brg' => '00101',
            'nama_brg' => 'Beras Pandan Wangi',
            'satuan' => 'SAK',
            'qty' => 250,
            'harga_jual' => 75_000,
            'jumlah' => 18_750_000,
            'barcode' => '0899100100101',
            'stok' => 40,
        ]);

        ProdukPenjualan::create([
            'upload_id' => $laporan->id,
            'kategori' => 'terendah',
            'kode_brg' => '00202',
            'nama_brg' => 'Saus Sambal Mini',
            'satuan' => 'PCS',
            'qty' => 1,
            'harga_jual' => 5_000,
            'jumlah' => 5_000,
            'barcode' => '0899200200202',
            'stok' => 12,
        ]);

        SupplierPenjualan::create([
            'upload_id' => $laporan->id,
            'kode_supp' => 'SUP01',
            'nama_supp' => 'PT Sumber Makmur',
            'gross_total' => 25_000_000,
            'net_sales_total' => 22_500_000,
        ]);

        // Unrelated upload in a different branch/period to confirm query isolation
        $otherUpload = $this->seedReportWithDailySales(
            'ampera',
            1,
            2026,
            $this->buildMonthlyDailySales('ampera', 1, 2026, 500_000),
            10,
            10,
            5,
        );
        ProdukPenjualan::create([
            'upload_id' => $otherUpload->id,
            'kategori' => 'tertinggi',
            'kode_brg' => '99999',
            'nama_brg' => 'Leaked Product',
            'satuan' => 'PCS',
            'qty' => 9999,
            'harga_jual' => 10_000,
            'jumlah' => 99_990_000,
            'barcode' => '9999999999999',
            'stok' => 99,
        ]);
        SupplierPenjualan::create([
            'upload_id' => $otherUpload->id,
            'kode_supp' => 'SUP99',
            'nama_supp' => 'Leaked Supplier',
            'gross_total' => 99_000_000,
            'net_sales_total' => 90_000_000,
        ]);

        $spreadsheet = $this->loadExportedSpreadsheet($laporan);

        // 1. VIEW sheet
        $view = $spreadsheet->getSheetByName('VIEW');
        $this->assertSame('LAPORAN DASHBOARD HARMONIS', $view->getCell('B2')->getValue());
        $this->assertSame('NOVEMBER', $view->getCell('B7')->getValue());
        $this->assertSame(2026, $view->getCell('B10')->getValue());
        $this->assertSame(60_000_000, $view->getCell('G7')->getValue());
        $this->assertSame('---', $view->getCell('L7')->getValue());
        $this->assertSame('---', $view->getCell('L10')->getValue());
        $this->assertSame(600, $view->getCell('Q7')->getValue());
        $this->assertSame(90, $view->getCell('V7')->getValue());

        $this->assertSame('NOVEMBER', $view->getCell('P12')->getValue());
        $this->assertSame(60_000_000, $view->getCell('Q12')->getValue());
        $this->assertNull($view->getCell('P13')->getValue());
        $this->assertNull($view->getCell('Q13')->getValue());

        $this->assertNull($view->getCell('B31')->getValue());
        $this->assertSame('NOVEMBER 2026', $view->getCell('B32')->getValue());
        $this->assertSame('NON-MEMBER', $view->getCell('B34')->getValue());
        $this->assertSame(240, $view->getCell('C34')->getValue());
        $this->assertSame('MEMBER', $view->getCell('B35')->getValue());
        $this->assertSame(360, $view->getCell('C35')->getValue());

        $this->assertSame("sheet://'TOP 300 HIGHEST'!A1", $view->getCell('X34')->getHyperlink()->getUrl());
        $this->assertSame("sheet://'TOP 300 LOWEST'!A1", $view->getCell('X37')->getHyperlink()->getUrl());
        $this->assertSame("sheet://'TOP SUPPLIER'!A1", $view->getCell('X40')->getHyperlink()->getUrl());

        $this->assertEquals(2_000_000, $view->getCell('X43')->getValue());
        $this->assertEquals(20, $view->getCell('X44')->getValue());
        $this->assertEquals(100_000, $view->getCell('X45')->getValue());
        $this->assertEquals(3, $view->getCell('X46')->getValue());
        $this->assertSame(900_000, $view->getCell('X47')->getValue());
        $this->assertSame(3_100_000, $view->getCell('X48')->getValue());

        $this->assertNull($view->getCell('K53')->getValue());
        $this->assertNull($view->getCell('L53')->getValue());
        $this->assertSame('NOVEMBER', $view->getCell('K54')->getValue());
        $this->assertSame(90, $view->getCell('L54')->getValue());
        $this->assertStringStartsWith('Generated: ', (string) $view->getCell('Y54')->getValue());

        // 2. DATA sheet (single-month gabungan layout: A..D daily table, F..G summary tables)
        $data = $spreadsheet->getSheetByName('DATA');
        $this->assertSame('NOVEMBER 2026', $data->getCell('A1')->getValue());
        $this->assertSame(['HARI', 'TOTAL', 'AMPERA', 'PAL'], [
            $data->getCell('A2')->getValue(),
            $data->getCell('B2')->getValue(),
            $data->getCell('C2')->getValue(),
            $data->getCell('D2')->getValue(),
        ]);

        // Day 1 (row 3), Day 15 (row 17), Day 30 (row 32)
        $this->assertSame([1, 900_000, 500_000, 400_000], [
            $data->getCell('A3')->getValue(),
            $data->getCell('B3')->getValue(),
            $data->getCell('C3')->getValue(),
            $data->getCell('D3')->getValue(),
        ]);
        $this->assertSame([15, 3_100_000, 2_000_000, 1_100_000], [
            $data->getCell('A17')->getValue(),
            $data->getCell('B17')->getValue(),
            $data->getCell('C17')->getValue(),
            $data->getCell('D17')->getValue(),
        ]);
        $this->assertSame([30, 2_000_000, 1_200_000, 800_000], [
            $data->getCell('A32')->getValue(),
            $data->getCell('B32')->getValue(),
            $data->getCell('C32')->getValue(),
            $data->getCell('D32')->getValue(),
        ]);
        $this->assertNull($data->getCell('A33')->getValue());

        // Summary tables on DATA (no SALES GROWTH block when no previous month)
        $this->assertSame('TOTAL PENJUALAN', $data->getCell('F2')->getValue());
        $this->assertSame('NOVEMBER 2026', $data->getCell('G3')->getValue());
        $this->assertSame(['TOTAL', 60_000_000], [$data->getCell('F4')->getValue(), $data->getCell('G4')->getValue()]);
        $this->assertSame(['LOWEST', 900_000], [$data->getCell('F5')->getValue(), $data->getCell('G5')->getValue()]);
        $this->assertSame(['HIGHEST', 3_100_000], [$data->getCell('F6')->getValue(), $data->getCell('G6')->getValue()]);
        $this->assertEquals(2_000_000, $data->getCell('G7')->getValue());

        $this->assertSame('TRANSACTION COUNT', $data->getCell('F9')->getValue());
        $this->assertSame(['MEMBER', 360], [$data->getCell('F11')->getValue(), $data->getCell('G11')->getValue()]);
        $this->assertSame(['NON-MEMBER', 240], [$data->getCell('F12')->getValue(), $data->getCell('G12')->getValue()]);
        $this->assertSame(['TOTAL', 600], [$data->getCell('F13')->getValue(), $data->getCell('G13')->getValue()]);

        $this->assertSame(['GROWTH MEMBER', 90], [$data->getCell('F15')->getValue(), $data->getCell('G15')->getValue()]);

        // 3. PENJUALAN HARIAN sheet
        $daily = $spreadsheet->getSheetByName('PENJUALAN HARIAN');
        $this->assertSame(['HARI', 'NOVEMBER 2026'], [
            $daily->getCell('A1')->getValue(),
            $daily->getCell('B1')->getValue(),
        ]);
        $this->assertSame(31, $daily->getHighestDataRow());
        $this->assertSame([1, 900_000], [$daily->getCell('A2')->getValue(), $daily->getCell('B2')->getValue()]);
        $this->assertSame([15, 3_100_000], [$daily->getCell('A16')->getValue(), $daily->getCell('B16')->getValue()]);
        $this->assertSame([30, 2_000_000], [$daily->getCell('A31')->getValue(), $daily->getCell('B31')->getValue()]);

        // 4. TOP 300 HIGHEST
        $topProducts = $spreadsheet->getSheetByName('TOP 300 HIGHEST');
        $this->assertSame(2, $topProducts->getHighestDataRow());
        $this->assertSame(['KODE_BRG', 'NAMA_BRG', 'SATUAN', 'QTY', 'HRG_JUAL', 'JUMLAH', 'BARCODE', 'STOK'], $topProducts->rangeToArray('A1:H1', null, true, false)[0]);
        $this->assertEquals(['00101', 'Beras Pandan Wangi', 'SAK', 250, 75_000, 18_750_000, '0899100100101', 40], $topProducts->rangeToArray('A2:H2', null, true, false)[0]);

        // 5. TOP 300 LOWEST
        $worstProducts = $spreadsheet->getSheetByName('TOP 300 LOWEST');
        $this->assertSame(2, $worstProducts->getHighestDataRow());
        $this->assertEquals(['00202', 'Saus Sambal Mini', 'PCS', 1, 5_000, 5_000, '0899200200202', 12], $worstProducts->rangeToArray('A2:H2', null, true, false)[0]);

        // 6. TOP SUPPLIER
        $suppliers = $spreadsheet->getSheetByName('TOP SUPPLIER');
        $this->assertSame(2, $suppliers->getHighestDataRow());
        $this->assertSame(['KODE_SUPP', 'NAMA_SUPP', 'GROSS_TOTAL', 'NET_SALES_TOTAL'], $suppliers->rangeToArray('A1:D1', null, true, false)[0]);
        $this->assertEquals(['SUP01', 'PT Sumber Makmur', 25_000_000, 22_500_000], $suppliers->rangeToArray('A2:D2', null, true, false)[0]);
    }

    // -------------------------------------------------------------------------
    // Consecutive months comparison (October 31 days -> November 30 days)
    // -------------------------------------------------------------------------

    public function test_consecutive_months_gabungan_export_calculates_growth_and_comparison_tables(): void
    {
        // October 2026 (31 days):
        // 31 days * (1,200,000 + 800,000) = 62,000,000
        // Tx: member 400, non-member 200 -> total 600; Member baru: 50
        $octSales = $this->buildMonthlyDailySales('gabungan', 10, 2026, 1_200_000, 800_000, [
            1 => ['ampera' => 1_000_000, 'pal' => 500_000],   // 1,500,000 (min)
            31 => ['ampera' => 1_400_000, 'pal' => 1_100_000], // 2,500,000 (max) -> sum stays 62,000,000
        ]);
        $this->seedReportWithDailySales('gabungan', 10, 2026, $octSales, 400, 200, 50);

        // November 2026 (30 days):
        // Day 1: 1,500,000 (750k + 750k), Day 30: 3,500,000 (2,000k + 1,500k), 28 days * 2,500,000 (1,500k + 1,000k) = 70,000,000
        //Wait: 1,500,000 + 3,500,000 + 70,000,000 = 75,000,000
        // Make Total = 77,500,000 (+25% over 62,000,000; diff = +15,500,000) by setting Day 10 = 5,000,000 (3,000k + 2,000k) instead of 2,500,000
        $novSales = $this->buildMonthlyDailySales('gabungan', 11, 2026, 1_500_000, 1_000_000, [
            1 => ['ampera' => 750_000, 'pal' => 750_000],      // 1,500,000 (min)
            10 => ['ampera' => 3_000_000, 'pal' => 2_000_000], // 5,000,000 (max)
            30 => ['ampera' => 2_000_000, 'pal' => 1_500_000], // 3,500,000
        ]);
        // Sum: 1.5M + 5.0M + 3.5M + (27 * 2.5M = 67.5M) = 77,500,000
        // Tx: member 500 (+25%), non-member 150 (-25%) -> total 650 (+1/12)
        // Member baru: 75 (+50%)
        $novLaporan = $this->seedReportWithDailySales('gabungan', 11, 2026, $novSales, 500, 150, 75);

        $spreadsheet = $this->loadExportedSpreadsheet($novLaporan);

        // VIEW sheet growth & comparison cells
        $view = $spreadsheet->getSheetByName('VIEW');
        $this->assertSame(77_500_000, $view->getCell('G7')->getValue());
        $this->assertEqualsWithDelta(0.25, (float) $view->getCell('L7')->getValue(), 0.0001);
        $this->assertSame(15_500_000, (int) $view->getCell('L10')->getValue());
        $this->assertSame(650, $view->getCell('Q7')->getValue());
        $this->assertSame(75, $view->getCell('V7')->getValue());

        $this->assertSame('NOVEMBER', $view->getCell('P12')->getValue());
        $this->assertSame(77_500_000, $view->getCell('Q12')->getValue());
        $this->assertSame('OKTOBER', $view->getCell('P13')->getValue());
        $this->assertSame(62_000_000, $view->getCell('Q13')->getValue());

        $this->assertSame('OKTOBER 2026', $view->getCell('B31')->getValue());
        $this->assertSame('NOVEMBER 2026', $view->getCell('B32')->getValue());

        $this->assertSame('OKTOBER', $view->getCell('K53')->getValue());
        $this->assertSame(50, $view->getCell('L53')->getValue());
        $this->assertSame('NOVEMBER', $view->getCell('K54')->getValue());
        $this->assertSame(75, $view->getCell('L54')->getValue());

        // DATA sheet two-month comparison layout:
        // Prev daily table: A..D (31 days -> rows 3..33)
        // Current daily table: F..I (30 days -> rows 3..32)
        // Summary tables: K..N
        $data = $spreadsheet->getSheetByName('DATA');
        $this->assertSame('OKTOBER 2026', $data->getCell('A1')->getValue());
        $this->assertSame([31, 2_500_000, 1_400_000, 1_100_000], [
            $data->getCell('A33')->getValue(),
            $data->getCell('B33')->getValue(),
            $data->getCell('C33')->getValue(),
            $data->getCell('D33')->getValue(),
        ]);

        $this->assertSame('NOVEMBER 2026', $data->getCell('F1')->getValue());
        $this->assertSame([10, 5_000_000, 3_000_000, 2_000_000], [
            $data->getCell('F12')->getValue(),
            $data->getCell('G12')->getValue(),
            $data->getCell('H12')->getValue(),
            $data->getCell('I12')->getValue(),
        ]);
        $this->assertNull($data->getCell('F33')->getValue());

        // TOTAL PENJUALAN (K2:M7)
        $this->assertSame('TOTAL PENJUALAN', $data->getCell('K2')->getValue());
        $this->assertSame(['OKTOBER 2026', 'NOVEMBER 2026'], [
            $data->getCell('L3')->getValue(),
            $data->getCell('M3')->getValue(),
        ]);
        $this->assertSame(['TOTAL', 62_000_000, 77_500_000], [
            $data->getCell('K4')->getValue(),
            $data->getCell('L4')->getValue(),
            $data->getCell('M4')->getValue(),
        ]);
        $this->assertSame(['LOWEST', 1_500_000, 1_500_000], [
            $data->getCell('K5')->getValue(),
            $data->getCell('L5')->getValue(),
            $data->getCell('M5')->getValue(),
        ]);
        $this->assertSame(['HIGHEST', 2_500_000, 5_000_000], [
            $data->getCell('K6')->getValue(),
            $data->getCell('L6')->getValue(),
            $data->getCell('M6')->getValue(),
        ]);
        $this->assertEquals(2_000_000, $data->getCell('L7')->getValue());
        $this->assertEqualsWithDelta(77_500_000 / 30, (float) $data->getCell('M7')->getValue(), 0.01);

        // SALES GROWTH (K9:L9)
        $this->assertSame('SALES GROWTH', $data->getCell('K9')->getValue());
        $this->assertEqualsWithDelta(0.25, (float) $data->getCell('L9')->getValue(), 0.0001);

        // TRANSACTION COUNT (K11:N15)
        $this->assertSame('TRANSACTION COUNT', $data->getCell('K11')->getValue());
        $this->assertSame(['OKTOBER 2026', 'NOVEMBER 2026', 'DIFF'], [
            $data->getCell('L12')->getValue(),
            $data->getCell('M12')->getValue(),
            $data->getCell('N12')->getValue(),
        ]);
        $this->assertSame(400, $data->getCell('L13')->getValue());
        $this->assertSame(500, $data->getCell('M13')->getValue());
        $this->assertEqualsWithDelta(0.25, (float) $data->getCell('N13')->getValue(), 0.0001);

        $this->assertSame(200, $data->getCell('L14')->getValue());
        $this->assertSame(150, $data->getCell('M14')->getValue());
        $this->assertEqualsWithDelta(-0.25, (float) $data->getCell('N14')->getValue(), 0.0001);

        $this->assertSame(600, $data->getCell('L15')->getValue());
        $this->assertSame(650, $data->getCell('M15')->getValue());
        $this->assertEqualsWithDelta(50 / 600, (float) $data->getCell('N15')->getValue(), 0.0001);

        // GROWTH MEMBER (K17:M19)
        $this->assertSame('GROWTH MEMBER', $data->getCell('K17')->getValue());
        $this->assertSame(['OKTOBER 2026', 'NOVEMBER 2026', 'DIFF'], [
            $data->getCell('K18')->getValue(),
            $data->getCell('L18')->getValue(),
            $data->getCell('M18')->getValue(),
        ]);
        $this->assertSame(50, $data->getCell('K19')->getValue());
        $this->assertSame(75, $data->getCell('L19')->getValue());
        $this->assertEqualsWithDelta(0.5, (float) $data->getCell('M19')->getValue(), 0.0001);

        // PENJUALAN HARIAN sheet: 31 days from max(31, 30), day 31 has Oct total and null for Nov
        $daily = $spreadsheet->getSheetByName('PENJUALAN HARIAN');
        $this->assertSame(['HARI', 'OKTOBER 2026', 'NOVEMBER 2026'], $daily->rangeToArray('A1:C1')[0]);
        $this->assertSame(32, $daily->getHighestDataRow());
        $this->assertSame([1, 1_500_000, 1_500_000], [$daily->getCell('A2')->getValue(), $daily->getCell('B2')->getValue(), $daily->getCell('C2')->getValue()]);
        $this->assertSame([31, 2_500_000, null], [$daily->getCell('A32')->getValue(), $daily->getCell('B32')->getValue(), $daily->getCell('C32')->getValue()]);
    }

    // -------------------------------------------------------------------------
    // Single-branch exports (ampera & pal)
    // -------------------------------------------------------------------------

    #[DataProvider('singleBranchProvider')]
    public function test_single_branch_export_omits_branch_breakdown_columns(string $jenisLaporan): void
    {
        // November 2026: 30 days * 1,000,000 = 30,000,000 (with day 1 = 500k, day 2 = 1.5M)
        $dailySales = $this->buildMonthlyDailySales($jenisLaporan, 11, 2026, 1_000_000, 0, [
            1 => ['total' => 500_000],
            2 => ['total' => 1_500_000],
        ]);
        $laporan = $this->seedReportWithDailySales($jenisLaporan, 11, 2026, $dailySales, 180, 120, 60);

        $spreadsheet = $this->loadExportedSpreadsheet($laporan);

        $view = $spreadsheet->getSheetByName('VIEW');
        $this->assertSame(30_000_000, $view->getCell('G7')->getValue());
        $this->assertSame(300, $view->getCell('Q7')->getValue());
        $this->assertSame(60, $view->getCell('V7')->getValue());
        $this->assertSame(500_000, $view->getCell('X47')->getValue());
        $this->assertSame(1_500_000, $view->getCell('X48')->getValue());

        // Single-branch DATA sheet uses columns A..B for daily table and shifts summary tables to D..E
        $data = $spreadsheet->getSheetByName('DATA');
        $this->assertSame('NOVEMBER 2026', $data->getCell('A1')->getValue());
        $this->assertSame(['HARI', 'TOTAL'], [
            $data->getCell('A2')->getValue(),
            $data->getCell('B2')->getValue(),
        ]);
        $this->assertNull($data->getCell('C2')->getValue());
        $this->assertNull($data->getCell('D1')->getValue());

        $this->assertSame([1, 500_000], [$data->getCell('A3')->getValue(), $data->getCell('B3')->getValue()]);
        $this->assertSame([2, 1_500_000], [$data->getCell('A4')->getValue(), $data->getCell('B4')->getValue()]);
        $this->assertSame([30, 1_000_000], [$data->getCell('A32')->getValue(), $data->getCell('B32')->getValue()]);

        $this->assertSame('TOTAL PENJUALAN', $data->getCell('D2')->getValue());
        $this->assertSame(['TOTAL', 30_000_000], [$data->getCell('D4')->getValue(), $data->getCell('E4')->getValue()]);
        $this->assertSame(['LOWEST', 500_000], [$data->getCell('D5')->getValue(), $data->getCell('E5')->getValue()]);
        $this->assertSame(['HIGHEST', 1_500_000], [$data->getCell('D6')->getValue(), $data->getCell('E6')->getValue()]);
        $this->assertSame('TRANSACTION COUNT', $data->getCell('D9')->getValue());
        $this->assertSame(['TOTAL', 300], [$data->getCell('D13')->getValue(), $data->getCell('E13')->getValue()]);
        $this->assertSame(['GROWTH MEMBER', 60], [$data->getCell('D15')->getValue(), $data->getCell('E15')->getValue()]);

        $daily = $spreadsheet->getSheetByName('PENJUALAN HARIAN');
        $this->assertSame(['HARI', 'NOVEMBER 2026'], $daily->rangeToArray('A1:B1')[0]);
        $this->assertSame([1, 500_000], [$daily->getCell('A2')->getValue(), $daily->getCell('B2')->getValue()]);
    }

    public static function singleBranchProvider(): array
    {
        return [
            'ampera' => ['ampera'],
            'pal' => ['pal'],
        ];
    }

    // -------------------------------------------------------------------------
    // KPI growth edge cases (zero growth & negative growth)
    // -------------------------------------------------------------------------

    public function test_zero_growth_across_consecutive_months_writes_numeric_zeros(): void
    {
        // Oct 2026 (31 days): 31 * 1,000,000 = 31,000,000
        $octSales = $this->buildMonthlyDailySales('ampera', 10, 2026, 1_000_000);
        $this->seedReportWithDailySales('ampera', 10, 2026, $octSales, 250, 150, 40);

        // Nov 2026 (30 days): 29 * 1,000,000 + day 30 = 2,000,000 -> exact 31,000,000 total
        $novSales = $this->buildMonthlyDailySales('ampera', 11, 2026, 1_000_000, 0, [
            30 => ['total' => 2_000_000],
        ]);
        $novLaporan = $this->seedReportWithDailySales('ampera', 11, 2026, $novSales, 250, 150, 40);

        $spreadsheet = $this->loadExportedSpreadsheet($novLaporan);

        $view = $spreadsheet->getSheetByName('VIEW');
        $this->assertSame(31_000_000, $view->getCell('G7')->getValue());
        $this->assertNotNull($view->getCell('L7')->getValue());
        $this->assertNotNull($view->getCell('L10')->getValue());
        $this->assertEqualsWithDelta(0.0, (float) $view->getCell('L7')->getValue(), 0.00001);
        $this->assertSame(0, (int) $view->getCell('L10')->getValue());

        // Single-branch with prev month: prev is A..B, curr is D..E, summary is G..J
        $data = $spreadsheet->getSheetByName('DATA');
        $this->assertSame('SALES GROWTH', $data->getCell('G9')->getValue());
        $this->assertNotNull($data->getCell('H9')->getValue());
        $this->assertEqualsWithDelta(0.0, (float) $data->getCell('H9')->getValue(), 0.00001);

        $this->assertNotNull($data->getCell('J13')->getValue());
        $this->assertNotNull($data->getCell('J14')->getValue());
        $this->assertNotNull($data->getCell('J15')->getValue());
        $this->assertNotNull($data->getCell('I19')->getValue());
        $this->assertEqualsWithDelta(0.0, (float) $data->getCell('J13')->getValue(), 0.00001);
        $this->assertEqualsWithDelta(0.0, (float) $data->getCell('J14')->getValue(), 0.00001);
        $this->assertEqualsWithDelta(0.0, (float) $data->getCell('J15')->getValue(), 0.00001);
        $this->assertEqualsWithDelta(0.0, (float) $data->getCell('I19')->getValue(), 0.00001);
    }

    public function test_negative_growth_across_consecutive_months_calculates_exact_declines(): void
    {
        // Oct 2026 (31 days): day 1 = 10,000,000 + 30 * 1,000,000 = 40,000,000
        $octSales = $this->buildMonthlyDailySales('pal', 10, 2026, 1_000_000, 0, [
            1 => ['total' => 10_000_000],
        ]);
        $this->seedReportWithDailySales('pal', 10, 2026, $octSales, 500, 300, 80);

        // Nov 2026 (30 days): 30 * 1,000,000 = 30,000,000 -> -25% (-10,000,000)
        // Tx: member 400 (-20%), non-member 240 (-20%), total 640 (-20%)
        // Member baru: 40 (-50%)
        $novSales = $this->buildMonthlyDailySales('pal', 11, 2026, 1_000_000);
        $novLaporan = $this->seedReportWithDailySales('pal', 11, 2026, $novSales, 400, 240, 40);

        $spreadsheet = $this->loadExportedSpreadsheet($novLaporan);

        $view = $spreadsheet->getSheetByName('VIEW');
        $this->assertEqualsWithDelta(-0.25, (float) $view->getCell('L7')->getValue(), 0.0001);
        $this->assertSame(-10_000_000, (int) $view->getCell('L10')->getValue());

        $data = $spreadsheet->getSheetByName('DATA');
        $this->assertEqualsWithDelta(-0.25, (float) $data->getCell('H9')->getValue(), 0.0001);
        $this->assertEqualsWithDelta(-0.20, (float) $data->getCell('J13')->getValue(), 0.0001);
        $this->assertEqualsWithDelta(-0.20, (float) $data->getCell('J14')->getValue(), 0.0001);
        $this->assertEqualsWithDelta(-0.20, (float) $data->getCell('J15')->getValue(), 0.0001);
        $this->assertEqualsWithDelta(-0.50, (float) $data->getCell('I19')->getValue(), 0.0001);
    }

    // -------------------------------------------------------------------------
    // Products: sorting, return items, nullable fields, text formatting
    // -------------------------------------------------------------------------

    public function test_product_sorting_return_items_nullable_fields_and_string_code_formatting(): void
    {
        $dailySales = $this->buildMonthlyDailySales('gabungan', 11, 2026, 1_000_000, 1_000_000);
        $laporan = $this->seedReportWithDailySales('gabungan', 11, 2026, $dailySales, 100, 100, 10);

        // Insert tertinggi out of order to verify qty DESC sorting + string preservation
        ProdukPenjualan::create([
            'upload_id' => $laporan->id,
            'kategori' => 'tertinggi',
            'kode_brg' => '000200',
            'nama_brg' => 'Produk Tengah',
            'satuan' => 'PCS',
            'qty' => 150,
            'harga_jual' => 10_000,
            'jumlah' => 1_500_000,
            'barcode' => '0000123456789',
            'stok' => 0,
        ]);
        ProdukPenjualan::create([
            'upload_id' => $laporan->id,
            'kategori' => 'tertinggi',
            'kode_brg' => '000300',
            'nama_brg' => 'Produk Terlaris',
            'satuan' => null,
            'qty' => 500,
            'harga_jual' => 20_000,
            'jumlah' => 10_000_000,
            'barcode' => '0899111222333',
            'stok' => null,
        ]);
        ProdukPenjualan::create([
            'upload_id' => $laporan->id,
            'kategori' => 'tertinggi',
            'kode_brg' => '000100',
            'nama_brg' => 'Produk Ketiga',
            'satuan' => 'BOX',
            'qty' => 50,
            'harga_jual' => 5_000,
            'jumlah' => 250_000,
            'barcode' => null,
            'stok' => 8,
        ]);

        // Insert terendah out of order including negative return item & zero-qty item
        ProdukPenjualan::create([
            'upload_id' => $laporan->id,
            'kategori' => 'terendah',
            'kode_brg' => '009002',
            'nama_brg' => 'Produk Nol',
            'satuan' => 'PCS',
            'qty' => 0,
            'harga_jual' => 12_000,
            'jumlah' => 0,
            'barcode' => '0099887766554',
            'stok' => 5,
        ]);
        ProdukPenjualan::create([
            'upload_id' => $laporan->id,
            'kategori' => 'terendah',
            'kode_brg' => '009001',
            'nama_brg' => 'Barang Retur',
            'satuan' => null,
            'qty' => -15,
            'harga_jual' => 30_000,
            'jumlah' => -450_000,
            'barcode' => '0001122334455',
            'stok' => null,
        ]);
        ProdukPenjualan::create([
            'upload_id' => $laporan->id,
            'kategori' => 'terendah',
            'kode_brg' => '009003',
            'nama_brg' => 'Produk Lambat',
            'satuan' => 'KG',
            'qty' => 3,
            'harga_jual' => 15_000,
            'jumlah' => 45_000,
            'barcode' => '0777888999000',
            'stok' => 20,
        ]);

        // Unrelated upload product must not appear
        $otherLaporan = $this->seedReportWithDailySales(
            'gabungan',
            12,
            2026,
            $this->buildMonthlyDailySales('gabungan', 12, 2026, 500_000, 500_000),
            10,
            10,
            1,
        );
        ProdukPenjualan::create([
            'upload_id' => $otherLaporan->id,
            'kategori' => 'tertinggi',
            'kode_brg' => '888888',
            'nama_brg' => 'Other Month Product',
            'satuan' => 'PCS',
            'qty' => 9999,
            'harga_jual' => 1_000,
            'jumlah' => 9_999_000,
            'barcode' => '8888888888888',
            'stok' => 1,
        ]);

        $spreadsheet = $this->loadExportedSpreadsheet($laporan);

        // TOP 300 HIGHEST: strictly qty DESC (500, 150, 50)
        $highest = $spreadsheet->getSheetByName('TOP 300 HIGHEST');
        $this->assertSame(4, $highest->getHighestDataRow());
        $this->assertSame('000300', $highest->getCell('A2')->getValue());
        $this->assertSame(DataType::TYPE_STRING, $highest->getCell('A2')->getDataType());
        $this->assertNull($highest->getCell('C2')->getValue());
        $this->assertSame(500, $highest->getCell('D2')->getValue());
        $this->assertSame('0899111222333', $highest->getCell('G2')->getValue());
        $this->assertSame(DataType::TYPE_STRING, $highest->getCell('G2')->getDataType());
        $this->assertNull($highest->getCell('H2')->getValue());

        $this->assertSame('000200', $highest->getCell('A3')->getValue());
        $this->assertSame(150, $highest->getCell('D3')->getValue());
        $this->assertSame('0000123456789', $highest->getCell('G3')->getValue());
        $this->assertSame(DataType::TYPE_STRING, $highest->getCell('G3')->getDataType());
        $this->assertSame(0, $highest->getCell('H3')->getValue());

        $this->assertSame('000100', $highest->getCell('A4')->getValue());
        $this->assertSame(50, $highest->getCell('D4')->getValue());
        $this->assertSame('', $highest->getCell('G4')->getValue());

        // TOP 300 LOWEST: strictly qty ASC (-15, 0, 3)
        $lowest = $spreadsheet->getSheetByName('TOP 300 LOWEST');
        $this->assertSame(4, $lowest->getHighestDataRow());
        $this->assertSame('009001', $lowest->getCell('A2')->getValue());
        $this->assertSame(DataType::TYPE_STRING, $lowest->getCell('A2')->getDataType());
        $this->assertSame('Barang Retur', $lowest->getCell('B2')->getValue());
        $this->assertNull($lowest->getCell('C2')->getValue());
        $this->assertSame(-15, $lowest->getCell('D2')->getValue());
        $this->assertEquals(-450_000, $lowest->getCell('F2')->getValue());
        $this->assertSame('0001122334455', $lowest->getCell('G2')->getValue());
        $this->assertSame(DataType::TYPE_STRING, $lowest->getCell('G2')->getDataType());
        $this->assertNull($lowest->getCell('H2')->getValue());

        $this->assertSame('009002', $lowest->getCell('A3')->getValue());
        $this->assertSame(0, $lowest->getCell('D3')->getValue());

        $this->assertSame('009003', $lowest->getCell('A4')->getValue());
        $this->assertSame(3, $lowest->getCell('D4')->getValue());
    }

    // -------------------------------------------------------------------------
    // Suppliers: scoping & column accuracy
    // -------------------------------------------------------------------------

    public function test_supplier_sheet_exports_only_matching_upload_suppliers_with_expected_columns(): void
    {
        $dailySales = $this->buildMonthlyDailySales('gabungan', 11, 2026, 1_000_000, 1_000_000);
        $laporan = $this->seedReportWithDailySales('gabungan', 11, 2026, $dailySales, 100, 100, 10);

        SupplierPenjualan::create([
            'upload_id' => $laporan->id,
            'kode_supp' => 'S0001',
            'nama_supp' => 'PT Jaya Abadi',
            'gross_total' => 50_000_000,
            'net_sales_total' => 45_000_000,
        ]);
        SupplierPenjualan::create([
            'upload_id' => $laporan->id,
            'kode_supp' => 'S0002',
            'nama_supp' => 'CV Sentosa',
            'gross_total' => 12_500_000,
            'net_sales_total' => 11_000_000,
        ]);

        // Supplier belonging to a different upload must not be exported
        $otherLaporan = $this->seedReportWithDailySales(
            'ampera',
            11,
            2026,
            $this->buildMonthlyDailySales('ampera', 11, 2026, 500_000),
            50,
            50,
            5,
        );
        SupplierPenjualan::create([
            'upload_id' => $otherLaporan->id,
            'kode_supp' => 'S9999',
            'nama_supp' => 'PT Cabang Lain',
            'gross_total' => 99_000_000,
            'net_sales_total' => 88_000_000,
        ]);

        $spreadsheet = $this->loadExportedSpreadsheet($laporan);
        $suppliers = $spreadsheet->getSheetByName('TOP SUPPLIER');

        $this->assertSame(3, $suppliers->getHighestDataRow());
        $this->assertSame(['KODE_SUPP', 'NAMA_SUPP', 'GROSS_TOTAL', 'NET_SALES_TOTAL'], $suppliers->rangeToArray('A1:D1', null, true, false)[0]);
        $this->assertEquals(['S0001', 'PT Jaya Abadi', 50_000_000, 45_000_000], $suppliers->rangeToArray('A2:D2', null, true, false)[0]);
        $this->assertEquals(['S0002', 'CV Sentosa', 12_500_000, 11_000_000], $suppliers->rangeToArray('A3:D3', null, true, false)[0]);
    }

    // -------------------------------------------------------------------------
    // Remaining edge cases: zero denominators, empty data, calendar, month names
    // -------------------------------------------------------------------------

    public function test_zero_denominators_fall_back_to_dashes_across_view_and_data_sheets(): void
    {
        // Oct 2026 (ampera): all daily sales are 0, 0 transactions, 0 new members
        $octSales = $this->buildMonthlyDailySales('ampera', 10, 2026, 0);
        $this->seedReportWithDailySales('ampera', 10, 2026, $octSales, 0, 0, 0);

        // Nov 2026 (ampera): positive sales (30,000,000), 0 transactions (basket size denominator = 0), 15 new members
        $novSales = $this->buildMonthlyDailySales('ampera', 11, 2026, 1_000_000);
        $novLaporan = $this->seedReportWithDailySales('ampera', 11, 2026, $novSales, 0, 0, 15);

        $spreadsheet = $this->loadExportedSpreadsheet($novLaporan);

        // VIEW: zero-denominator growth and basket size fall back to '---'
        $view = $spreadsheet->getSheetByName('VIEW');
        $this->assertSame(30_000_000, $view->getCell('G7')->getValue());
        $this->assertSame('---', $view->getCell('L7')->getValue());
        $this->assertSame('---', $view->getCell('L10')->getValue());
        $this->assertSame(0, $view->getCell('Q7')->getValue());
        $this->assertSame(15, $view->getCell('V7')->getValue());
        $this->assertSame(0, $view->getCell('Q13')->getValue());
        $this->assertSame(0, $view->getCell('L53')->getValue());
        $this->assertEquals(0, $view->getCell('X44')->getValue());
        $this->assertSame('---', $view->getCell('X45')->getValue());

        // DATA: single-branch with prev -> summary starts at G
        $data = $spreadsheet->getSheetByName('DATA');
        $this->assertSame('SALES GROWTH', $data->getCell('G9')->getValue());
        $this->assertSame('---', $data->getCell('H9')->getValue());
        $this->assertSame('---', $data->getCell('J13')->getValue());
        $this->assertSame('---', $data->getCell('J14')->getValue());
        $this->assertSame('---', $data->getCell('J15')->getValue());
        $this->assertSame('---', $data->getCell('I19')->getValue());

        // PENJUALAN HARIAN: Oct zero sales preserved as numeric 0 rather than null
        $daily = $spreadsheet->getSheetByName('PENJUALAN HARIAN');
        $this->assertSame(0, $daily->getCell('B2')->getValue());
        $this->assertSame(1_000_000, $daily->getCell('C2')->getValue());
        $this->assertSame(0, $daily->getCell('B32')->getValue());
        $this->assertNull($daily->getCell('C32')->getValue());
    }

    public function test_empty_datasets_export_cleanly_with_header_only_product_and_supplier_sheets(): void
    {
        // Create an upload with zero child rows (no SalesHarian, DataTransaksi, MemberCount, ProdukPenjualan, SupplierPenjualan)
        $laporan = LaporanUpload::factory()
            ->jenis_laporan('gabungan')
            ->periode(11, 2026)
            ->create();

        $spreadsheet = $this->loadExportedSpreadsheet($laporan);

        $this->assertSame(6, $spreadsheet->getSheetCount());

        // VIEW: safe 0 totals & '---' for undefined ratios
        $view = $spreadsheet->getSheetByName('VIEW');
        $this->assertSame(0, $view->getCell('G7')->getValue());
        $this->assertSame('---', $view->getCell('L7')->getValue());
        $this->assertSame('---', $view->getCell('L10')->getValue());
        $this->assertSame(0, $view->getCell('Q7')->getValue());
        $this->assertSame(0, $view->getCell('V7')->getValue());
        $this->assertEquals(0, $view->getCell('X43')->getValue());
        $this->assertEquals(0, $view->getCell('X44')->getValue());
        $this->assertSame('---', $view->getCell('X45')->getValue());
        $this->assertEquals(0, $view->getCell('X46')->getValue());
        $this->assertSame(0, $view->getCell('X47')->getValue());
        $this->assertSame(0, $view->getCell('X48')->getValue());

        // DATA: daily table has headers only, summary tables hold safe zeros
        $data = $spreadsheet->getSheetByName('DATA');
        $this->assertSame('NOVEMBER 2026', $data->getCell('A1')->getValue());
        $this->assertSame(['HARI', 'TOTAL', 'AMPERA', 'PAL'], $data->rangeToArray('A2:D2', null, true, false)[0]);
        $this->assertNull($data->getCell('A3')->getValue());
        $this->assertSame(['TOTAL', 0], [$data->getCell('F4')->getValue(), $data->getCell('G4')->getValue()]);
        $this->assertSame(['LOWEST', 0], [$data->getCell('F5')->getValue(), $data->getCell('G5')->getValue()]);
        $this->assertSame(['HIGHEST', 0], [$data->getCell('F6')->getValue(), $data->getCell('G6')->getValue()]);
        $this->assertEquals(0, $data->getCell('G7')->getValue());
        $this->assertSame(['TOTAL', 0], [$data->getCell('F13')->getValue(), $data->getCell('G13')->getValue()]);
        $this->assertSame(['GROWTH MEMBER', 0], [$data->getCell('F15')->getValue(), $data->getCell('G15')->getValue()]);

        // PENJUALAN HARIAN: 30 numbered days with null values
        $daily = $spreadsheet->getSheetByName('PENJUALAN HARIAN');
        $this->assertSame(31, $daily->getHighestDataRow());
        $this->assertSame([1, null], [$daily->getCell('A2')->getValue(), $daily->getCell('B2')->getValue()]);
        $this->assertSame([30, null], [$daily->getCell('A31')->getValue(), $daily->getCell('B31')->getValue()]);

        // Header-only product & supplier sheets
        $highest = $spreadsheet->getSheetByName('TOP 300 HIGHEST');
        $this->assertSame(1, $highest->getHighestDataRow());
        $this->assertSame(['KODE_BRG', 'NAMA_BRG', 'SATUAN', 'QTY', 'HRG_JUAL', 'JUMLAH', 'BARCODE', 'STOK'], $highest->rangeToArray('A1:H1', null, true, false)[0]);

        $lowest = $spreadsheet->getSheetByName('TOP 300 LOWEST');
        $this->assertSame(1, $lowest->getHighestDataRow());
        $this->assertSame(['KODE_BRG', 'NAMA_BRG', 'SATUAN', 'QTY', 'HRG_JUAL', 'JUMLAH', 'BARCODE', 'STOK'], $lowest->rangeToArray('A1:H1', null, true, false)[0]);

        $suppliers = $spreadsheet->getSheetByName('TOP SUPPLIER');
        $this->assertSame(1, $suppliers->getHighestDataRow());
        $this->assertSame(['KODE_SUPP', 'NAMA_SUPP', 'GROSS_TOTAL', 'NET_SALES_TOTAL'], $suppliers->rangeToArray('A1:D1', null, true, false)[0]);
    }

    public function test_cross_year_january_export_resolves_december_of_previous_year(): void
    {
        // Dec 2025 (31 days): 31 * (1,000,000 + 1,000,000) = 62,000,000
        $decSales = $this->buildMonthlyDailySales('gabungan', 12, 2025, 1_000_000, 1_000_000);
        $this->seedReportWithDailySales('gabungan', 12, 2025, $decSales, 300, 200, 40);

        // Jan 2026 (31 days): 31 * (1,500,000 + 1,000,000) = 77,500,000 (+25% growth)
        $janSales = $this->buildMonthlyDailySales('gabungan', 1, 2026, 1_500_000, 1_000_000);
        $janLaporan = $this->seedReportWithDailySales('gabungan', 1, 2026, $janSales, 360, 240, 60);

        $spreadsheet = $this->loadExportedSpreadsheet($janLaporan);

        $view = $spreadsheet->getSheetByName('VIEW');
        $this->assertSame('JANUARI', $view->getCell('B7')->getValue());
        $this->assertSame(2026, $view->getCell('B10')->getValue());
        $this->assertSame(77_500_000, $view->getCell('G7')->getValue());
        $this->assertEqualsWithDelta(0.25, (float) $view->getCell('L7')->getValue(), 0.0001);
        $this->assertSame(15_500_000, (int) $view->getCell('L10')->getValue());
        $this->assertSame('JANUARI', $view->getCell('P12')->getValue());
        $this->assertSame('DESEMBER', $view->getCell('P13')->getValue());
        $this->assertSame('DESEMBER 2025', $view->getCell('B31')->getValue());
        $this->assertSame('JANUARI 2026', $view->getCell('B32')->getValue());
        $this->assertSame('DESEMBER', $view->getCell('K53')->getValue());
        $this->assertSame(40, $view->getCell('L53')->getValue());
        $this->assertSame('JANUARI', $view->getCell('K54')->getValue());
        $this->assertSame(60, $view->getCell('L54')->getValue());

        $data = $spreadsheet->getSheetByName('DATA');
        $this->assertSame('DESEMBER 2025', $data->getCell('A1')->getValue());
        $this->assertSame('JANUARI 2026', $data->getCell('F1')->getValue());
        $this->assertSame(31, $data->getCell('A33')->getValue());
        $this->assertSame(31, $data->getCell('F33')->getValue());

        $daily = $spreadsheet->getSheetByName('PENJUALAN HARIAN');
        $this->assertSame(['HARI', 'DESEMBER 2025', 'JANUARI 2026'], $daily->rangeToArray('A1:C1', null, true, false)[0]);
        $this->assertSame(32, $daily->getHighestDataRow());
        $this->assertSame([31, 2_000_000, 2_500_000], [$daily->getCell('A32')->getValue(), $daily->getCell('B32')->getValue(), $daily->getCell('C32')->getValue()]);
    }

    public function test_leap_year_february_export_handles_29_days_and_january_comparison(): void
    {
        // Jan 2024 (31 days): 31 * 1,000,000 = 31,000,000
        $janSales = $this->buildMonthlyDailySales('ampera', 1, 2024, 1_000_000);
        $this->seedReportWithDailySales('ampera', 1, 2024, $janSales, 200, 100, 30);

        // Feb 2024 (leap year, 29 days): 29 * 1,000,000 = 29,000,000
        $febSales = $this->buildMonthlyDailySales('ampera', 2, 2024, 1_000_000);
        $febLaporan = $this->seedReportWithDailySales('ampera', 2, 2024, $febSales, 200, 100, 30);

        $spreadsheet = $this->loadExportedSpreadsheet($febLaporan);

        $view = $spreadsheet->getSheetByName('VIEW');
        $this->assertSame('FEBRUARI', $view->getCell('B7')->getValue());
        $this->assertSame(2024, $view->getCell('B10')->getValue());
        $this->assertSame(29_000_000, $view->getCell('G7')->getValue());
        $this->assertEquals(1_000_000, $view->getCell('X43')->getValue());

        $data = $spreadsheet->getSheetByName('DATA');
        $this->assertSame('JANUARI 2024', $data->getCell('A1')->getValue());
        $this->assertSame(31, $data->getCell('A33')->getValue());
        $this->assertSame('FEBRUARI 2024', $data->getCell('D1')->getValue());
        $this->assertSame([29, 1_000_000], [$data->getCell('D31')->getValue(), $data->getCell('E31')->getValue()]);
        $this->assertNull($data->getCell('D32')->getValue());

        $daily = $spreadsheet->getSheetByName('PENJUALAN HARIAN');
        $this->assertSame(['HARI', 'JANUARI 2024', 'FEBRUARI 2024'], $daily->rangeToArray('A1:C1', null, true, false)[0]);
        $this->assertSame(32, $daily->getHighestDataRow());
        $this->assertSame([29, 1_000_000, 1_000_000], [$daily->getCell('A30')->getValue(), $daily->getCell('B30')->getValue(), $daily->getCell('C30')->getValue()]);
        $this->assertSame([30, 1_000_000, null], [$daily->getCell('A31')->getValue(), $daily->getCell('B31')->getValue(), $daily->getCell('C31')->getValue()]);
        $this->assertSame([31, 1_000_000, null], [$daily->getCell('A32')->getValue(), $daily->getCell('B32')->getValue(), $daily->getCell('C32')->getValue()]);
    }

    public function test_may_month_label_is_consistently_mei_across_all_sheets(): void
    {
        $aprSales = $this->buildMonthlyDailySales('pal', 4, 2026, 1_000_000);
        $this->seedReportWithDailySales('pal', 4, 2026, $aprSales, 100, 100, 20);

        $maySales = $this->buildMonthlyDailySales('pal', 5, 2026, 1_200_000);
        $mayLaporan = $this->seedReportWithDailySales('pal', 5, 2026, $maySales, 120, 110, 25);

        $spreadsheet = $this->loadExportedSpreadsheet($mayLaporan);

        $view = $spreadsheet->getSheetByName('VIEW');
        $this->assertSame('MEI', $view->getCell('B7')->getValue());
        $this->assertSame('MEI', $view->getCell('P12')->getValue());
        $this->assertSame('MEI 2026', $view->getCell('B32')->getValue());
        $this->assertSame('MEI', $view->getCell('K54')->getValue());

        $data = $spreadsheet->getSheetByName('DATA');
        $this->assertSame('MEI 2026', $data->getCell('D1')->getValue());
        $this->assertSame('MEI 2026', $data->getCell('I3')->getValue());
        $this->assertSame('MEI 2026', $data->getCell('I12')->getValue());
        $this->assertSame('MEI 2026', $data->getCell('H18')->getValue());

        $daily = $spreadsheet->getSheetByName('PENJUALAN HARIAN');
        $this->assertSame(['HARI', 'APRIL 2026', 'MEI 2026'], $daily->rangeToArray('A1:C1', null, true, false)[0]);
    }
}
