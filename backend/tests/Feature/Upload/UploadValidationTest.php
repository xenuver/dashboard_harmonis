<?php

namespace Tests\Feature\Upload;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Helpers\ExcelReportFactory;
use Tests\TestCase;

class UploadValidationTest extends TestCase
{
    use RefreshDatabase;

    private const UPLOAD_URL = '/api/upload';

    private string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $this->adminToken = User::factory()->create([
            'role' => 'it_staff',
        ])->createToken('auth_token')->plainTextToken;
    }

    protected function tearDown(): void
    {
        ExcelReportFactory::cleanup();

        parent::tearDown();
    }

    private function assertNoUploadDataPersisted(): void
    {
        $this->assertDatabaseCount('laporan_uploads', 0);
        $this->assertDatabaseCount('sales_harian', 0);
        $this->assertDatabaseCount('produk_penjualan', 0);
        $this->assertDatabaseCount('supplier_penjualan', 0);
        $this->assertDatabaseCount('data_transaksi', 0);
        $this->assertDatabaseCount('total_member', 0);
    }

    private function postReport(ExcelReportFactory $factory, array $requestOverrides = [])
    {
        $payload = array_merge([
            'jenis_laporan' => 'gabungan',
            'periode_bulan' => 11,
            'periode_tahun' => 2026,
            'file' => $factory->toUploadedFile(),
        ], $requestOverrides);

        return $this->withHeaders([
            'Accept' => 'application/json',
        ])
            ->withToken($this->adminToken)
            ->post(self::UPLOAD_URL, $payload);
    }

    // -------------------------------------------------------------------------
    // INFO sheet: empty, missing, malformed & mismatch validation
    // -------------------------------------------------------------------------

    #[DataProvider('requiredInfoFieldProvider')]
    public function test_rejects_empty_required_info_parameter(string $field, mixed $emptyValue): void
    {
        $factory = ExcelReportFactory::new()
            ->jenis_laporan('gabungan')
            ->periode(11, 2026)
            ->withEmptyInfoField($field, $emptyValue);

        $response = $this->postReport($factory);

        $response->assertUnprocessable()
            ->assertJson([
                'message' => 'Validasi spreadsheet gagal.',
            ])
            ->assertJsonStructure([
                'xlsxValidationErrors' => ['INFO'],
            ]);

        $messages = collect($response->json('xlsxValidationErrors.INFO'))->pluck('message')->implode(' | ');
        $this->assertStringContainsString("{$field} tidak boleh kosong.", $messages);

        $this->assertNoUploadDataPersisted();
    }

    public static function requiredInfoFieldProvider(): array
    {
        return [
            'empty JENIS_LAPORAN (null)' => ['JENIS_LAPORAN', null],
            'empty JENIS_LAPORAN (whitespace)' => ['JENIS_LAPORAN', '   '],
            'empty BULAN (null)' => ['BULAN', null],
            'empty BULAN (whitespace)' => ['BULAN', '   '],
            'empty TAHUN (null)' => ['TAHUN', null],
            'empty TAHUN (whitespace)' => ['TAHUN', '   '],
            'empty JUMLAH_HARI (null)' => ['JUMLAH_HARI', null],
            'empty JUMLAH_HARI (whitespace)' => ['JUMLAH_HARI', '   '],
            'empty FORMAT (null)' => ['FORMAT', null],
            'empty FORMAT (whitespace)' => ['FORMAT', '   '],
        ];
    }

    #[DataProvider('missingInfoFieldProvider')]
    public function test_rejects_missing_required_info_parameter_row(string $field): void
    {
        $factory = ExcelReportFactory::new()
            ->jenis_laporan('gabungan')
            ->periode(11, 2026)
            ->withoutInfoField($field);

        $response = $this->postReport($factory);

        $response->assertUnprocessable()
            ->assertJsonStructure([
                'xlsxValidationErrors' => ['INFO'],
            ]);

        $messages = collect($response->json('xlsxValidationErrors.INFO'))->pluck('message')->implode(' | ');
        $this->assertStringContainsString("{$field} tidak ditemukan!", $messages);

        $this->assertNoUploadDataPersisted();
    }

    public static function missingInfoFieldProvider(): array
    {
        return [
            'missing JENIS_LAPORAN row' => ['JENIS_LAPORAN'],
            'missing BULAN row' => ['BULAN'],
            'missing TAHUN row' => ['TAHUN'],
            'missing JUMLAH_HARI row' => ['JUMLAH_HARI'],
            'missing FORMAT row' => ['FORMAT'],
        ];
    }

    public function test_rejects_empty_info_sheet_and_missing_info_columns(): void
    {
        $emptySheetFactory = ExcelReportFactory::new()
            ->jenis_laporan('gabungan')
            ->periode(11, 2026)
            ->withEmptySheet(ExcelReportFactory::SHEET_INFO);

        $this->postReport($emptySheetFactory)
            ->assertUnprocessable()
            ->assertJsonStructure(['xlsxValidationErrors' => ['INFO']]);

        $missingKeterangan = ExcelReportFactory::new()
            ->jenis_laporan('gabungan')
            ->periode(11, 2026)
            ->withoutColumn(ExcelReportFactory::SHEET_INFO, 'KETERANGAN');

        $this->postReport($missingKeterangan)
            ->assertUnprocessable()
            ->assertJsonPath('xlsxValidationErrors.INFO.0.message', 'Kolom KETERANGAN tidak ditemukan.');

        $missingNilai = ExcelReportFactory::new()
            ->jenis_laporan('gabungan')
            ->periode(11, 2026)
            ->withoutColumn(ExcelReportFactory::SHEET_INFO, 'NILAI');

        $this->postReport($missingNilai)
            ->assertUnprocessable()
            ->assertJsonPath('xlsxValidationErrors.INFO.0.message', 'Kolom NILAI tidak ditemukan.');

        $this->assertNoUploadDataPersisted();
    }

    #[DataProvider('invalidInfoValueProvider')]
    public function test_rejects_invalid_info_values_and_request_mismatches(string $field, mixed $value): void
    {
        $factory = ExcelReportFactory::new()
            ->jenis_laporan('gabungan')
            ->periode(11, 2026)
            ->withInfoValue($field, $value);

        $this->postReport($factory)
            ->assertUnprocessable()
            ->assertJsonStructure([
                'xlsxValidationErrors' => ['INFO'],
            ]);

        $this->assertNoUploadDataPersisted();
    }

    public static function invalidInfoValueProvider(): array
    {
        return [
            'jenis_laporan lowercase in INFO' => ['JENIS_LAPORAN', 'gabungan'],
            'jenis_laporan mixed-case in INFO' => ['JENIS_LAPORAN', 'Gabungan'],
            'jenis_laporan mismatched vs request' => ['JENIS_LAPORAN', 'AMPERA'],
            'jenis_laporan unknown in INFO' => ['JENIS_LAPORAN', 'CABANG_LAIN'],
            'bulan mismatched vs request' => ['BULAN', 10],
            'bulan below 1' => ['BULAN', 0],
            'bulan above 12' => ['BULAN', 13],
            'bulan non-integer' => ['BULAN', 'November'],
            'tahun mismatched vs request' => ['TAHUN', 2025],
            'tahun negative' => ['TAHUN', -1],
            'tahun above 65535' => ['TAHUN', 65536],
            'tahun non-integer' => ['TAHUN', '2026.5'],
            'jumlah_hari non-integer' => ['JUMLAH_HARI', '30 hari'],
        ];
    }

    // -------------------------------------------------------------------------
    // Missing & empty sheets
    // -------------------------------------------------------------------------

    #[DataProvider('requiredSheetProvider')]
    public function test_rejects_missing_required_sheet(string $sheetName): void
    {
        $factory = ExcelReportFactory::new()
            ->jenis_laporan('gabungan')
            ->periode(11, 2026)
            ->withoutSheet($sheetName);

        $response = $this->postReport($factory);

        $response->assertUnprocessable()
            ->assertJsonStructure([
                'xlsxValidationErrors' => ['SHEETS'],
            ]);

        $messages = collect($response->json('xlsxValidationErrors.SHEETS'))->pluck('message')->implode(' | ');
        $this->assertStringContainsString("Sheet {$sheetName} tidak ditemukan!", $messages);

        $this->assertNoUploadDataPersisted();
    }

    public static function requiredSheetProvider(): array
    {
        return [
            'missing INFO' => [ExcelReportFactory::SHEET_INFO],
            'missing PENJUALAN_HARIAN' => [ExcelReportFactory::SHEET_PENJUALAN_HARIAN],
            'missing TRANSAKSI' => [ExcelReportFactory::SHEET_TRANSAKSI],
            'missing PRODUK_TERTINGGI' => [ExcelReportFactory::SHEET_PRODUK_TERTINGGI],
            'missing PRODUK_TERENDAH' => [ExcelReportFactory::SHEET_PRODUK_TERENDAH],
            'missing SUPPLIER' => [ExcelReportFactory::SHEET_SUPPLIER],
        ];
    }

    // -------------------------------------------------------------------------
    // PENJUALAN_HARIAN sheet validation
    // -------------------------------------------------------------------------

    public function test_rejects_penjualan_harian_empty_or_missing_columns(): void
    {
        $empty = ExcelReportFactory::new()
            ->jenis_laporan('gabungan')
            ->periode(11, 2026)
            ->withEmptySheet(ExcelReportFactory::SHEET_PENJUALAN_HARIAN);

        $this->postReport($empty)
            ->assertUnprocessable()
            ->assertJsonStructure(['xlsxValidationErrors' => ['PENJUALAN_HARIAN']]);

        $missingAmpera = ExcelReportFactory::new()
            ->jenis_laporan('gabungan')
            ->periode(11, 2026)
            ->withoutColumn(ExcelReportFactory::SHEET_PENJUALAN_HARIAN, 'AMPERA');

        $this->postReport($missingAmpera)
            ->assertUnprocessable()
            ->assertJsonPath('xlsxValidationErrors.PENJUALAN_HARIAN.0.message', 'Kolom AMPERA tidak ditemukan');

        $missingPal = ExcelReportFactory::new()
            ->jenis_laporan('gabungan')
            ->periode(11, 2026)
            ->withoutColumn(ExcelReportFactory::SHEET_PENJUALAN_HARIAN, 'PAL');

        $this->postReport($missingPal)
            ->assertUnprocessable()
            ->assertJsonPath('xlsxValidationErrors.PENJUALAN_HARIAN.0.message', 'Kolom PAL tidak ditemukan');

        $missingTotal = ExcelReportFactory::new()
            ->jenis_laporan('ampera')
            ->periode(11, 2026)
            ->withoutColumn(ExcelReportFactory::SHEET_PENJUALAN_HARIAN, 'TOTAL_PENJUALAN');

        $this->postReport($missingTotal, ['jenis_laporan' => 'ampera'])
            ->assertUnprocessable()
            ->assertJsonPath('xlsxValidationErrors.PENJUALAN_HARIAN.0.message', 'Kolom TOTAL_PENJUALAN tidak ditemukan');

        $missingTanggal = ExcelReportFactory::new()
            ->jenis_laporan('ampera')
            ->periode(11, 2026)
            ->withoutColumn(ExcelReportFactory::SHEET_PENJUALAN_HARIAN, 'TANGGAL');

        $this->postReport($missingTanggal, ['jenis_laporan' => 'ampera'])
            ->assertUnprocessable()
            ->assertJsonPath('xlsxValidationErrors.PENJUALAN_HARIAN.0.message', 'Kolom TANGGAL tidak ditemukan');

        $this->assertNoUploadDataPersisted();
    }

    public function test_rejects_penjualan_harian_custom_mismatched_day_counts(): void
    {
        $tooFewDays = ExcelReportFactory::new()
            ->jenis_laporan('gabungan')
            ->periode(11, 2026)
            ->withSalesCount(20);

        $this->postReport($tooFewDays)
            ->assertUnprocessable()
            ->assertJsonStructure(['xlsxValidationErrors' => ['PENJUALAN_HARIAN']]);

        $tooManyDays = ExcelReportFactory::new()
            ->jenis_laporan('gabungan')
            ->periode(11, 2026)
            ->withSalesCount(32);

        $this->postReport($tooManyDays)
            ->assertUnprocessable()
            ->assertJsonStructure(['xlsxValidationErrors' => ['PENJUALAN_HARIAN']]);

        $this->assertNoUploadDataPersisted();
    }

    public function test_rejects_penjualan_harian_cell_errors_and_gabungan_sum_mismatch(): void
    {
        // Sum mismatch (AMPERA + PAL !== TOTAL_PENJUALAN)
        $sumMismatch = ExcelReportFactory::new()
            ->jenis_laporan('gabungan')
            ->periode(11, 2026)
            ->withCellValue(ExcelReportFactory::SHEET_PENJUALAN_HARIAN, 2, 'TOTAL_PENJUALAN', 999);

        $this->postReport($sumMismatch)
            ->assertUnprocessable()
            ->assertJsonStructure(['xlsxValidationErrors' => ['PENJUALAN_HARIAN']]);

        // Duplicate TANGGAL
        $duplicateDate = ExcelReportFactory::new()
            ->jenis_laporan('ampera')
            ->periode(11, 2026)
            ->withCellValue(ExcelReportFactory::SHEET_PENJUALAN_HARIAN, 3, 'TANGGAL', 1);

        $this->postReport($duplicateDate, ['jenis_laporan' => 'ampera'])
            ->assertUnprocessable()
            ->assertJsonStructure(['xlsxValidationErrors' => ['PENJUALAN_HARIAN']]);

        // Negative daily sales value
        $negativeSales = ExcelReportFactory::new()
            ->jenis_laporan('pal')
            ->periode(11, 2026)
            ->withCellValue(ExcelReportFactory::SHEET_PENJUALAN_HARIAN, 2, 'TOTAL_PENJUALAN', -500);

        $this->postReport($negativeSales, ['jenis_laporan' => 'pal'])
            ->assertUnprocessable()
            ->assertJsonStructure(['xlsxValidationErrors' => ['PENJUALAN_HARIAN']]);

        // Non-integer sales value
        $decimalSales = ExcelReportFactory::new()
            ->jenis_laporan('pal')
            ->periode(11, 2026)
            ->withCellValue(ExcelReportFactory::SHEET_PENJUALAN_HARIAN, 2, 'TOTAL_PENJUALAN', 1234.56);

        $this->postReport($decimalSales, ['jenis_laporan' => 'pal'])
            ->assertUnprocessable()
            ->assertJsonStructure(['xlsxValidationErrors' => ['PENJUALAN_HARIAN']]);

        // Empty TANGGAL cell
        $emptyDate = ExcelReportFactory::new()
            ->jenis_laporan('pal')
            ->periode(11, 2026)
            ->withCellValue(ExcelReportFactory::SHEET_PENJUALAN_HARIAN, 2, 'TANGGAL', null);

        $this->postReport($emptyDate, ['jenis_laporan' => 'pal'])
            ->assertUnprocessable()
            ->assertJsonStructure(['xlsxValidationErrors' => ['PENJUALAN_HARIAN']]);

        // Empty TOTAL_PENJUALAN cell while TANGGAL is populated
        $emptyTotalSales = ExcelReportFactory::new()
            ->jenis_laporan('pal')
            ->periode(11, 2026)
            ->withCellValue(ExcelReportFactory::SHEET_PENJUALAN_HARIAN, 2, 'TOTAL_PENJUALAN', null);

        $this->postReport($emptyTotalSales, ['jenis_laporan' => 'pal'])
            ->assertUnprocessable()
            ->assertJsonPath('xlsxValidationErrors.PENJUALAN_HARIAN.0.message', 'Nilai pada sel ini di kolom TOTAL_PENJUALAN kosong!');

        $this->assertNoUploadDataPersisted();
    }

    // -------------------------------------------------------------------------
    // TRANSAKSI sheet validation
    // -------------------------------------------------------------------------

    public function test_rejects_transaksi_sheet_structural_and_cell_errors(): void
    {
        $empty = ExcelReportFactory::new()
            ->jenis_laporan('gabungan')
            ->periode(11, 2026)
            ->withEmptySheet(ExcelReportFactory::SHEET_TRANSAKSI);

        $this->postReport($empty)
            ->assertUnprocessable()
            ->assertJsonStructure(['xlsxValidationErrors' => ['TRANSAKSI']]);

        $missingMemberHeader = ExcelReportFactory::new()
            ->jenis_laporan('gabungan')
            ->periode(11, 2026)
            ->withoutColumn(ExcelReportFactory::SHEET_TRANSAKSI, 'TRANSAKSI_MEMBER');

        $this->postReport($missingMemberHeader)
            ->assertUnprocessable()
            ->assertJsonPath('xlsxValidationErrors.TRANSAKSI.0.message', 'Kolom TRANSAKSI_MEMBER tidak ditemukan');

        $missingNonMemberHeader = ExcelReportFactory::new()
            ->jenis_laporan('gabungan')
            ->periode(11, 2026)
            ->withoutColumn(ExcelReportFactory::SHEET_TRANSAKSI, 'TRANSAKSI_NON_MEMBER');

        $this->postReport($missingNonMemberHeader)
            ->assertUnprocessable()
            ->assertJsonPath('xlsxValidationErrors.TRANSAKSI.0.message', 'Kolom TRANSAKSI_NON_MEMBER tidak ditemukan');

        $missingHeader = ExcelReportFactory::new()
            ->jenis_laporan('gabungan')
            ->periode(11, 2026)
            ->withoutColumn(ExcelReportFactory::SHEET_TRANSAKSI, 'MEMBER_BARU');

        $this->postReport($missingHeader)
            ->assertUnprocessable()
            ->assertJsonPath('xlsxValidationErrors.TRANSAKSI.0.message', 'Kolom MEMBER_BARU tidak ditemukan');

        $missingDataRow = ExcelReportFactory::new()
            ->jenis_laporan('gabungan')
            ->periode(11, 2026)
            ->withSheetRows(ExcelReportFactory::SHEET_TRANSAKSI, [
                ['TRANSAKSI_MEMBER', 'TRANSAKSI_NON_MEMBER', 'MEMBER_BARU'],
            ]);

        $this->postReport($missingDataRow)
            ->assertUnprocessable()
            ->assertJsonPath('xlsxValidationErrors.TRANSAKSI.0.message', 'Data transaksi tidak ditemukan');

        $emptyCell = ExcelReportFactory::new()
            ->jenis_laporan('gabungan')
            ->periode(11, 2026)
            ->withCellValue(ExcelReportFactory::SHEET_TRANSAKSI, 2, 'TRANSAKSI_MEMBER', null);

        $this->postReport($emptyCell)
            ->assertUnprocessable()
            ->assertJsonPath('xlsxValidationErrors.TRANSAKSI.0.message', 'transaksi_member tidak boleh kosong.');

        $negativeValue = ExcelReportFactory::new()
            ->jenis_laporan('gabungan')
            ->periode(11, 2026)
            ->withCellValue(ExcelReportFactory::SHEET_TRANSAKSI, 2, 'TRANSAKSI_NON_MEMBER', -1);

        $this->postReport($negativeValue)
            ->assertUnprocessable()
            ->assertJsonPath('xlsxValidationErrors.TRANSAKSI.0.message', 'Nilai transaksi_non_member tidak boleh di bawah 0');

        $nonInteger = ExcelReportFactory::new()
            ->jenis_laporan('gabungan')
            ->periode(11, 2026)
            ->withCellValue(ExcelReportFactory::SHEET_TRANSAKSI, 2, 'MEMBER_BARU', 'abc');

        $this->postReport($nonInteger)
            ->assertUnprocessable()
            ->assertJsonPath('xlsxValidationErrors.TRANSAKSI.0.message', 'member_baru harus berupa angka bulat');

        $this->assertNoUploadDataPersisted();
    }

    // -------------------------------------------------------------------------
    // PRODUK_TERTINGGI & PRODUK_TERENDAH sheet validation
    // -------------------------------------------------------------------------

    #[DataProvider('productSheetErrorKeyProvider')]
    public function test_rejects_product_sheet_validation_errors(string $sheetName, string $expectedErrorKey): void
    {
        $emptySheet = ExcelReportFactory::new()
            ->jenis_laporan('gabungan')
            ->periode(11, 2026)
            ->withEmptySheet($sheetName);

        $this->postReport($emptySheet)
            ->assertUnprocessable()
            ->assertJsonStructure(['xlsxValidationErrors' => [$expectedErrorKey]]);

        $missingColumn = ExcelReportFactory::new()
            ->jenis_laporan('gabungan')
            ->periode(11, 2026)
            ->withoutColumn($sheetName, 'KODE_BRG');

        $this->postReport($missingColumn)
            ->assertUnprocessable()
            ->assertJsonPath("xlsxValidationErrors.{$expectedErrorKey}.0.message", 'Kolom KODE_BRG tidak ditemukan');

        $emptyRequiredCell = ExcelReportFactory::new()
            ->jenis_laporan('gabungan')
            ->periode(11, 2026)
            ->withCellValue($sheetName, 2, 'NAMA_BRG', null);

        $this->postReport($emptyRequiredCell)
            ->assertUnprocessable()
            ->assertJsonPath("xlsxValidationErrors.{$expectedErrorKey}.0.message", 'Nilai pada sel ini di kolom NAMA_BRG kosong!');

        $nonIntegerQty = ExcelReportFactory::new()
            ->jenis_laporan('gabungan')
            ->periode(11, 2026)
            ->withCellValue($sheetName, 2, 'QTY', 12.5);

        $this->postReport($nonIntegerQty)
            ->assertUnprocessable()
            ->assertJsonStructure(['xlsxValidationErrors' => [$expectedErrorKey]]);

        $emptyRow = ExcelReportFactory::new()
            ->jenis_laporan('gabungan')
            ->periode(11, 2026)
            ->withSheetRows($sheetName, [
                ['KODE_BRG', 'NAMA_BRG', 'SATUAN', 'QTY', 'HRG_JUAL', 'JUMLAH', 'BARCODE', 'STOK'],
                [null, null, null, null, null, null, null, null],
                ['BRG-001', 'Produk Contoh', 'PCS', 5, 10_000, 50_000, '8990000000001', 20],
            ]);

        $this->postReport($emptyRow)
            ->assertUnprocessable()
            ->assertJsonPath("xlsxValidationErrors.{$expectedErrorKey}.0.location", 2)
            ->assertJsonPath("xlsxValidationErrors.{$expectedErrorKey}.0.message", 'Baris kosong!');

        $this->assertNoUploadDataPersisted();
    }

    public static function productSheetErrorKeyProvider(): array
    {
        // Note: LaporanImport::getImportErrors() maps PRODUK_TERTINGGI errors under key 'PRODUK_TERTINGGI'
        return [
            'PRODUK_TERTINGGI' => [ExcelReportFactory::SHEET_PRODUK_TERTINGGI, 'PRODUK_TERTINGGI'],
            'PRODUK_TERENDAH' => [ExcelReportFactory::SHEET_PRODUK_TERENDAH, 'PRODUK_TERENDAH'],
        ];
    }

    // -------------------------------------------------------------------------
    // SUPPLIER sheet validation
    // -------------------------------------------------------------------------

    public function test_rejects_supplier_sheet_structural_and_cell_errors(): void
    {
        $empty = ExcelReportFactory::new()
            ->jenis_laporan('gabungan')
            ->periode(11, 2026)
            ->withEmptySheet(ExcelReportFactory::SHEET_SUPPLIER);

        $this->postReport($empty)
            ->assertUnprocessable()
            ->assertJsonStructure(['xlsxValidationErrors' => ['SUPPLIER']]);

        $missingColumn = ExcelReportFactory::new()
            ->jenis_laporan('gabungan')
            ->periode(11, 2026)
            ->withoutColumn(ExcelReportFactory::SHEET_SUPPLIER, 'NET_SALES_TOTAL');

        $this->postReport($missingColumn)
            ->assertUnprocessable()
            ->assertJsonPath('xlsxValidationErrors.SUPPLIER.0.message', 'Kolom NET_SALES_TOTAL tidak ditemukan.');

        $emptyCell = ExcelReportFactory::new()
            ->jenis_laporan('gabungan')
            ->periode(11, 2026)
            ->withCellValue(ExcelReportFactory::SHEET_SUPPLIER, 2, 'NAMA_SUPP', null);

        $this->postReport($emptyCell)
            ->assertUnprocessable()
            ->assertJsonPath('xlsxValidationErrors.SUPPLIER.0.message', 'Nilai pada sel ini di kolom NAMA_SUPP kosong!');

        $nonIntegerGross = ExcelReportFactory::new()
            ->jenis_laporan('gabungan')
            ->periode(11, 2026)
            ->withCellValue(ExcelReportFactory::SHEET_SUPPLIER, 2, 'GROSS_TOTAL', 'bukan-angka');

        $this->postReport($nonIntegerGross)
            ->assertUnprocessable()
            ->assertJsonStructure(['xlsxValidationErrors' => ['SUPPLIER']]);

        $emptyRow = ExcelReportFactory::new()
            ->jenis_laporan('gabungan')
            ->periode(11, 2026)
            ->withSheetRows(ExcelReportFactory::SHEET_SUPPLIER, [
                ['KODE_SUPP', 'NAMA_SUPP', 'GROSS_TOTAL', 'NET_SALES_TOTAL'],
                [null, null, null, null],
                ['S0001', 'PT Supplier Contoh', 50_000_000, 45_000_000],
            ]);

        $this->postReport($emptyRow)
            ->assertUnprocessable()
            ->assertJsonPath('xlsxValidationErrors.SUPPLIER.0.location', 2)
            ->assertJsonPath('xlsxValidationErrors.SUPPLIER.0.message', 'Baris kosong!');

        $this->assertNoUploadDataPersisted();
    }
}
