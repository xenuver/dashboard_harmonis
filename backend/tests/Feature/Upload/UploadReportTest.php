<?php

namespace Tests\Feature\Upload;

use App\Models\Cabang;
use App\Models\LaporanUpload;
use App\Models\ProdukPenjualan;
use App\Models\SalesHarian;
use App\Models\SupplierPenjualan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\PersonalAccessToken;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Helpers\ExcelReportFactory;
use Tests\TestCase;

class UploadReportTest extends TestCase
{
    use RefreshDatabase;

    private const UPLOAD_URL = '/api/upload';

    private User $adminUser;

    private User $ownerUser;

    private string $adminToken;

    private string $ownerToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $this->adminUser = User::factory()->create([
            'role' => 'it_staff',
        ]);
        $this->ownerUser = User::factory()->create([
            'role' => 'owner',
        ]);

        $this->adminToken = $this->adminUser->createToken('auth_token')->plainTextToken;
        $this->ownerToken = $this->ownerUser->createToken('auth_token')->plainTextToken;
    }

    protected function tearDown(): void
    {
        ExcelReportFactory::cleanup();

        parent::tearDown();
    }

    private function postUpload(array $data, ?string $token = null)
    {
        $request = $this->withHeaders([
            'Accept' => 'application/json',
        ]);

        if ($token !== null) {
            $request = $request->withToken($token);
        }

        return $request->post(self::UPLOAD_URL, $data);
    }

    // -------------------------------------------------------------------------
    // Authentication & authorization
    // -------------------------------------------------------------------------

    public function test_cannot_upload_report_when_logged_out(): void
    {
        $file = ExcelReportFactory::new()
            ->jenis_laporan('gabungan')
            ->periode(11, 2026)
            ->toUploadedFile();

        $this->postUpload([
            'jenis_laporan' => 'gabungan',
            'periode_bulan' => 11,
            'periode_tahun' => 2026,
            'file' => $file,
        ])->assertUnauthorized();

        $this->assertDatabaseCount('laporan_uploads', 0);
    }

    public function test_cannot_upload_report_when_user_is_disabled(): void
    {
        $disabledUser = User::factory()->create([
            'role' => 'it_staff',
            'enabled' => false,
        ]);
        $disabledToken = $disabledUser->createToken('auth_token')->plainTextToken;

        $file = ExcelReportFactory::new()
            ->jenis_laporan('ampera')
            ->periode(11, 2026)
            ->toUploadedFile();

        $this->postUpload([
            'jenis_laporan' => 'ampera',
            'periode_bulan' => 11,
            'periode_tahun' => 2026,
            'file' => $file,
        ], $disabledToken)
            ->assertUnauthorized()
            ->assertJson([
                'message' => 'Unauthenticated.',
            ]);

        $tokenRecord = PersonalAccessToken::findToken($disabledToken);
        $this->assertNotNull($tokenRecord);
        $this->assertTrue($tokenRecord->expires_at->isPast());
        $this->assertDatabaseCount('laporan_uploads', 0);
    }

    public function test_owner_cannot_upload_report(): void
    {
        $file = ExcelReportFactory::new()
            ->jenis_laporan('gabungan')
            ->periode(11, 2026)
            ->toUploadedFile();

        $this->postUpload([
            'jenis_laporan' => 'gabungan',
            'periode_bulan' => 11,
            'periode_tahun' => 2026,
            'file' => $file,
        ], $this->ownerToken)
            ->assertForbidden();

        $this->assertDatabaseCount('laporan_uploads', 0);
        $this->assertDatabaseCount('sales_harian', 0);
        $this->assertDatabaseCount('produk_penjualan', 0);
        $this->assertDatabaseCount('supplier_penjualan', 0);
        $this->assertDatabaseCount('data_transaksi', 0);
        $this->assertDatabaseCount('total_member', 0);
    }

    // -------------------------------------------------------------------------
    // Upload inspector & request parameter validation
    // -------------------------------------------------------------------------

    public function test_rejects_request_with_more_than_one_uploaded_file(): void
    {
        $fileA = ExcelReportFactory::new()->jenis_laporan('ampera')->periode(11, 2026)->toUploadedFile('a.xlsx');
        $fileB = ExcelReportFactory::new()->jenis_laporan('pal')->periode(11, 2026)->toUploadedFile('b.xlsx');

        $this->postUpload([
            'jenis_laporan' => 'ampera',
            'periode_bulan' => 11,
            'periode_tahun' => 2026,
            'file' => $fileA,
            'extra_file' => $fileB,
        ], $this->adminToken)
            ->assertStatus(400)
            ->assertJson([
                'message' => 'Anda hanya bisa menggunggah 1 file',
            ]);

        $this->assertDatabaseCount('laporan_uploads', 0);
    }

    public function test_rejects_non_xlsx_file_upload(): void
    {
        $nonXlsx = UploadedFile::fake()->create('laporan.csv', 10, 'text/csv');

        $this->postUpload([
            'jenis_laporan' => 'gabungan',
            'periode_bulan' => 11,
            'periode_tahun' => 2026,
            'file' => $nonXlsx,
        ], $this->adminToken)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file'])
            ->assertJsonPath('errors.file.0', 'File lampiran harus dalam format .xlsx');

        $this->assertDatabaseCount('laporan_uploads', 0);
    }

    public function test_rejects_missing_file_upload(): void
    {
        $this->postUpload([
            'jenis_laporan' => 'gabungan',
            'periode_bulan' => 11,
            'periode_tahun' => 2026,
        ], $this->adminToken)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file'])
            ->assertJsonPath('errors.file.0', 'File harus dilampirkan');
    }

    #[DataProvider('invalidRequestParameterProvider')]
    public function test_rejects_invalid_request_parameters(array $overrides, string $errorField, string $expectedMessage): void
    {
        $file = ExcelReportFactory::new()
            ->jenis_laporan('gabungan')
            ->periode(11, 2026)
            ->toUploadedFile();

        $payload = array_merge([
            'jenis_laporan' => 'gabungan',
            'periode_bulan' => 11,
            'periode_tahun' => 2026,
            'file' => $file,
        ], $overrides);

        foreach ($overrides as $key => $value) {
            if ($value === null) {
                unset($payload[$key]);
            }
        }

        $this->postUpload($payload, $this->adminToken)
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$errorField])
            ->assertJsonPath("errors.{$errorField}.0", $expectedMessage);

        $this->assertDatabaseCount('laporan_uploads', 0);
    }

    public static function invalidRequestParameterProvider(): array
    {
        return [
            'jenis_laporan missing' => [
                ['jenis_laporan' => null],
                'jenis_laporan',
                'Jenis laporan harus diisi',
            ],
            'jenis_laporan uppercase rejected by request validator' => [
                ['jenis_laporan' => 'GABUNGAN'],
                'jenis_laporan',
                'Hanya menerima nilai ampera, pal, dan gabungan pada jenis laporan',
            ],
            'jenis_laporan unknown' => [
                ['jenis_laporan' => 'cabang_baru'],
                'jenis_laporan',
                'Hanya menerima nilai ampera, pal, dan gabungan pada jenis laporan',
            ],
            'periode_bulan missing' => [
                ['periode_bulan' => null],
                'periode_bulan',
                'Periode bulan harus diisi',
            ],
            'periode_bulan non-integer' => [
                ['periode_bulan' => 'november'],
                'periode_bulan',
                'Periode bulan harus merupakan angka dalam format integer',
            ],
            'periode_bulan below 1' => [
                ['periode_bulan' => 0],
                'periode_bulan',
                'Periode bulan harus berupa angka antara 1 sampai 12',
            ],
            'periode_bulan above 12' => [
                ['periode_bulan' => 13],
                'periode_bulan',
                'Periode bulan harus berupa angka antara 1 sampai 12',
            ],
            'periode_tahun missing' => [
                ['periode_tahun' => null],
                'periode_tahun',
                'Periode tahun harus diisi',
            ],
            'periode_tahun non-integer' => [
                ['periode_tahun' => '2026abc'],
                'periode_tahun',
                'Periode tahun harus merupakan angka dalam format integer',
            ],
            'periode_tahun below 0' => [
                ['periode_tahun' => -1],
                'periode_tahun',
                'Periode tahun harus berupa angka antara 0 sampai 65535',
            ],
            'periode_tahun above 65535' => [
                ['periode_tahun' => 65536],
                'periode_tahun',
                'Periode tahun harus berupa angka antara 0 sampai 65535',
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Successful uploads & full multi-table persistence
    // -------------------------------------------------------------------------

    public function test_admin_can_upload_gabungan_report_and_persist_all_tables(): void
    {
        $topCount = 7;
        $worstCount = 4;
        $supplierCount = 6;
        $salesMin = 15_000_000;
        $salesMax = 45_000_000;

        $factory = ExcelReportFactory::new()
            ->jenis_laporan('gabungan')
            ->periode(11, 2026)
            ->format('DATA MENTAH v2')
            ->catatan('Laporan gabungan bulan November')
            ->withTopProductCount($topCount)
            ->withWorstProductCount($worstCount)
            ->withSupplierCount($supplierCount)
            ->withSalesRange($salesMin, $salesMax);

        $file = $factory->toUploadedFile('laporan-gabungan.xlsx');
        $generated = $factory->getLastBuiltData();

        $response = $this->postUpload([
            'jenis_laporan' => 'gabungan',
            'periode_bulan' => 11,
            'periode_tahun' => 2026,
            'file' => $file,
        ], $this->adminToken);

        $response->assertOk()
            ->assertJsonStructure(['message', 'upload_id'])
            ->assertJson([
                'message' => 'Upload berhasil',
            ]);

        $uploadId = $response->json('upload_id');
        $upload = LaporanUpload::findOrFail($uploadId);

        // Controller normalizes INFO.JENIS_LAPORAN ('GABUNGAN') via strtolower() right before saving
        $this->assertSame('gabungan', $upload->jenis_laporan);
        $this->assertSame(11, $upload->periode_bulan);
        $this->assertSame(2026, $upload->periode_tahun);
        $this->assertSame(30, $upload->jumlah_hari);
        $this->assertSame('DATA MENTAH v2', $upload->format_data);
        $this->assertSame('Laporan gabungan bulan November', $upload->catatan);

        $cabangAmpera = Cabang::where('kode', 'AMPERA')->firstOrFail();
        $cabangPal = Cabang::where('kode', 'PAL')->firstOrFail();

        // Gabungan inserts 2 rows per day (AMPERA + PAL) = 60 rows
        $salesRows = SalesHarian::where('upload_id', $uploadId)->get();
        $this->assertCount(60, $salesRows);

        foreach ($generated['sales'] as $sale) {
            $expectedDate = sprintf('2026-11-%02d', $sale['tanggal']);

            $amperaRow = $salesRows->first(fn (SalesHarian $row) => $row->cabang_id === $cabangAmpera->id && $row->tanggal->format('Y-m-d') === $expectedDate);
            $palRow = $salesRows->first(fn (SalesHarian $row) => $row->cabang_id === $cabangPal->id && $row->tanggal->format('Y-m-d') === $expectedDate);

            $this->assertNotNull($amperaRow);
            $this->assertNotNull($palRow);
            $this->assertSame($sale['ampera'], $amperaRow->total_penjualan);
            $this->assertSame($sale['pal'], $palRow->total_penjualan);
            $this->assertGreaterThanOrEqual($salesMin, $amperaRow->total_penjualan);
            $this->assertLessThanOrEqual($salesMax, $amperaRow->total_penjualan);
            $this->assertGreaterThanOrEqual($salesMin, $palRow->total_penjualan);
            $this->assertLessThanOrEqual($salesMax, $palRow->total_penjualan);
        }

        // Data transaksi & member count
        $this->assertDatabaseHas('data_transaksi', [
            'upload_id' => $uploadId,
            'transaksi_member' => $generated['transaksi']['transaksi_member'],
            'transaksi_non_member' => $generated['transaksi']['transaksi_non_member'],
        ]);
        $this->assertDatabaseHas('total_member', [
            'upload_id' => $uploadId,
            'member_baru' => $generated['transaksi']['member_baru'],
        ]);

        // PRODUK_TERTINGGI -> kategori 'tertinggi', PRODUK_TERENDAH -> kategori 'terendah'
        $this->assertSame($topCount, ProdukPenjualan::where('upload_id', $uploadId)->where('kategori', 'tertinggi')->count());
        $this->assertSame($worstCount, ProdukPenjualan::where('upload_id', $uploadId)->where('kategori', 'terendah')->count());
        $this->assertSame($topCount + $worstCount, ProdukPenjualan::where('upload_id', $uploadId)->count());

        foreach ($generated['top_products'] as $product) {
            $this->assertDatabaseHas('produk_penjualan', [
                'upload_id' => $uploadId,
                'kategori' => 'tertinggi',
                'kode_brg' => $product['kode_brg'],
                'nama_brg' => $product['nama_brg'],
                'satuan' => $product['satuan'],
                'harga_jual' => $product['harga_jual'],
                'jumlah' => $product['jumlah'],
                'qty' => $product['qty'],
                'barcode' => $product['barcode'],
                'stok' => $product['stok'],
            ]);
        }

        foreach ($generated['worst_products'] as $product) {
            $this->assertDatabaseHas('produk_penjualan', [
                'upload_id' => $uploadId,
                'kategori' => 'terendah',
                'kode_brg' => $product['kode_brg'],
                'nama_brg' => $product['nama_brg'],
                'satuan' => $product['satuan'],
                'harga_jual' => $product['harga_jual'],
                'jumlah' => $product['jumlah'],
                'qty' => $product['qty'],
                'barcode' => $product['barcode'],
                'stok' => $product['stok'],
            ]);
        }

        // Supplier penjualan
        $this->assertSame($supplierCount, SupplierPenjualan::where('upload_id', $uploadId)->count());

        foreach ($generated['suppliers'] as $supplier) {
            $this->assertDatabaseHas('supplier_penjualan', [
                'upload_id' => $uploadId,
                'kode_supp' => $supplier['kode_supp'],
                'nama_supp' => $supplier['nama_supp'],
                'gross_total' => $supplier['gross_total'],
                'net_sales_total' => $supplier['net_sales_total'],
            ]);
        }
    }

    #[DataProvider('singleBranchReportProvider')]
    public function test_admin_can_upload_single_branch_report_and_persist_all_tables(string $jenisLaporan, string $expectedCabangKode, int $bulan, int $tahun, int $expectedDays): void
    {
        $topCount = 5;
        $worstCount = 3;
        $supplierCount = 4;
        $salesMin = 5_000_000;
        $salesMax = 12_000_000;

        $factory = ExcelReportFactory::new()
            ->jenis_laporan($jenisLaporan)
            ->periode($bulan, $tahun)
            ->withTopProductCount($topCount)
            ->withWorstProductCount($worstCount)
            ->withSupplierCount($supplierCount)
            ->withSalesRange($salesMin, $salesMax);

        $file = $factory->toUploadedFile("laporan-{$jenisLaporan}.xlsx");
        $generated = $factory->getLastBuiltData();

        $response = $this->postUpload([
            'jenis_laporan' => $jenisLaporan,
            'periode_bulan' => $bulan,
            'periode_tahun' => $tahun,
            'file' => $file,
        ], $this->adminToken);

        $response->assertOk()
            ->assertJson([
                'message' => 'Upload berhasil',
            ]);

        $uploadId = $response->json('upload_id');
        $cabang = Cabang::where('kode', $expectedCabangKode)->firstOrFail();

        $this->assertDatabaseHas('laporan_uploads', [
            'id' => $uploadId,
            'jenis_laporan' => $jenisLaporan,
            'periode_bulan' => $bulan,
            'periode_tahun' => $tahun,
            'jumlah_hari' => $expectedDays,
        ]);

        $salesRows = SalesHarian::where('upload_id', $uploadId)->get();
        $this->assertCount($expectedDays, $salesRows);
        $this->assertTrue($salesRows->every(fn (SalesHarian $row) => $row->cabang_id === $cabang->id));

        foreach ($generated['sales'] as $sale) {
            $expectedDate = sprintf('%04d-%02d-%02d', $tahun, $bulan, $sale['tanggal']);
            $row = $salesRows->first(fn (SalesHarian $item) => $item->tanggal->format('Y-m-d') === $expectedDate);

            $this->assertNotNull($row);
            $this->assertSame($sale['total'], $row->total_penjualan);
            $this->assertGreaterThanOrEqual($salesMin, $row->total_penjualan);
            $this->assertLessThanOrEqual($salesMax, $row->total_penjualan);
        }

        $this->assertSame($topCount, ProdukPenjualan::where('upload_id', $uploadId)->where('kategori', 'tertinggi')->count());
        $this->assertSame($worstCount, ProdukPenjualan::where('upload_id', $uploadId)->where('kategori', 'terendah')->count());
        $this->assertSame($supplierCount, SupplierPenjualan::where('upload_id', $uploadId)->count());
        $this->assertDatabaseHas('data_transaksi', [
            'upload_id' => $uploadId,
            'transaksi_member' => $generated['transaksi']['transaksi_member'],
            'transaksi_non_member' => $generated['transaksi']['transaksi_non_member'],
        ]);
        $this->assertDatabaseHas('total_member', [
            'upload_id' => $uploadId,
            'member_baru' => $generated['transaksi']['member_baru'],
        ]);
    }

    public static function singleBranchReportProvider(): array
    {
        return [
            'ampera 31-day month' => ['ampera', 'AMPERA', 10, 2026, 31],
            'pal leap-year february' => ['pal', 'PAL', 2, 2024, 29],
            'ampera non-leap february' => ['ampera', 'AMPERA', 2, 2026, 28],
        ];
    }

    public function test_upload_succeeds_when_optional_catatan_is_empty_or_omitted(): void
    {
        $fileEmptyCatatan = ExcelReportFactory::new()
            ->jenis_laporan('ampera')
            ->periode(8, 2026)
            ->withEmptyInfoField('CATATAN')
            ->withTopProductCount(2)
            ->withWorstProductCount(2)
            ->withSupplierCount(2)
            ->toUploadedFile('empty-catatan.xlsx');

        $responseA = $this->postUpload([
            'jenis_laporan' => 'ampera',
            'periode_bulan' => 8,
            'periode_tahun' => 2026,
            'file' => $fileEmptyCatatan,
        ], $this->adminToken);

        $responseA->assertOk();
        $this->assertNull(LaporanUpload::findOrFail($responseA->json('upload_id'))->catatan);

        $fileOmittedCatatan = ExcelReportFactory::new()
            ->jenis_laporan('pal')
            ->periode(8, 2026)
            ->withoutInfoField('CATATAN')
            ->withTopProductCount(2)
            ->withWorstProductCount(2)
            ->withSupplierCount(2)
            ->toUploadedFile('omitted-catatan.xlsx');

        $responseB = $this->postUpload([
            'jenis_laporan' => 'pal',
            'periode_bulan' => 8,
            'periode_tahun' => 2026,
            'file' => $fileOmittedCatatan,
        ], $this->adminToken);

        $responseB->assertOk();
        $this->assertNull(LaporanUpload::findOrFail($responseB->json('upload_id'))->catatan);
    }

    public function test_upload_preserves_negative_return_products_and_nullable_product_columns(): void
    {
        $worstProducts = [
            [
                'kode_brg' => 'RET-0001',
                'nama_brg' => 'Produk Retur',
                'satuan' => null,
                'qty' => -12,
                'harga_jual' => 25_000,
                'jumlah' => -300_000,
                'barcode' => null,
                'stok' => -5,
            ],
            [
                'kode_brg' => 'NUL-0002',
                'nama_brg' => 'Produk Tanpa Stok',
                'satuan' => 'PCS',
                'qty' => 0,
                'harga_jual' => 10_000,
                'jumlah' => 0,
                'barcode' => null,
                'stok' => null,
            ],
        ];

        $file = ExcelReportFactory::new()
            ->jenis_laporan('pal')
            ->periode(9, 2026)
            ->withTopProductCount(1)
            ->withWorstProducts($worstProducts)
            ->withSupplierCount(1)
            ->toUploadedFile();

        $response = $this->postUpload([
            'jenis_laporan' => 'pal',
            'periode_bulan' => 9,
            'periode_tahun' => 2026,
            'file' => $file,
        ], $this->adminToken);

        $response->assertOk();
        $uploadId = $response->json('upload_id');

        $retur = ProdukPenjualan::where('upload_id', $uploadId)
            ->where('kode_brg', 'RET-0001')
            ->firstOrFail();

        $this->assertSame('terendah', $retur->kategori);
        $this->assertSame(-12, $retur->qty);
        $this->assertSame(-300_000, $retur->jumlah);
        $this->assertSame(-5, $retur->stok);
        $this->assertNull($retur->barcode);
        $this->assertNull($retur->satuan);
        $this->assertTrue($retur->isRetur());

        $zeroQty = ProdukPenjualan::where('upload_id', $uploadId)
            ->where('kode_brg', 'NUL-0002')
            ->firstOrFail();

        $this->assertSame('terendah', $zeroQty->kategori);
        $this->assertSame(0, $zeroQty->qty);
        $this->assertSame(0, $zeroQty->jumlah);
        $this->assertNull($zeroQty->stok);
    }

    public function test_upload_succeeds_with_zero_counts_for_transactions_products_and_suppliers(): void
    {
        $file = ExcelReportFactory::new()
            ->jenis_laporan('ampera')
            ->periode(7, 2026)
            ->withTransactionData(0, 0, 0)
            ->withTopProductCount(0)
            ->withWorstProductCount(0)
            ->withSupplierCount(0)
            ->toUploadedFile('zero-counts.xlsx');

        $response = $this->postUpload([
            'jenis_laporan' => 'ampera',
            'periode_bulan' => 7,
            'periode_tahun' => 2026,
            'file' => $file,
        ], $this->adminToken);

        $response->assertOk();
        $uploadId = $response->json('upload_id');

        $this->assertDatabaseHas('data_transaksi', [
            'upload_id' => $uploadId,
            'transaksi_member' => 0,
            'transaksi_non_member' => 0,
        ]);
        $this->assertDatabaseHas('total_member', [
            'upload_id' => $uploadId,
            'member_baru' => 0,
        ]);
        $this->assertSame(0, ProdukPenjualan::where('upload_id', $uploadId)->count());
        $this->assertSame(0, SupplierPenjualan::where('upload_id', $uploadId)->count());
    }

    // -------------------------------------------------------------------------
    // Duplicate period conflict (409)
    // -------------------------------------------------------------------------

    #[DataProvider('jenisLaporanProvider')]
    public function test_cannot_upload_duplicate_report_for_same_jenis_and_periode(string $jenisLaporan): void
    {
        LaporanUpload::factory()
            ->jenis_laporan($jenisLaporan)
            ->periode(11, 2026)
            ->create();

        $file = ExcelReportFactory::new()
            ->jenis_laporan($jenisLaporan)
            ->periode(11, 2026)
            ->toUploadedFile();

        $this->postUpload([
            'jenis_laporan' => $jenisLaporan,
            'periode_bulan' => 11,
            'periode_tahun' => 2026,
            'file' => $file,
        ], $this->adminToken)
            ->assertStatus(409)
            ->assertJson([
                'message' => 'Laporan untuk periode dan jenis ini sudah pernah diunggah.',
            ]);

        $this->assertSame(1, LaporanUpload::count());
        $this->assertDatabaseCount('sales_harian', 0);
        $this->assertDatabaseCount('produk_penjualan', 0);
        $this->assertDatabaseCount('supplier_penjualan', 0);
        $this->assertDatabaseCount('data_transaksi', 0);
        $this->assertDatabaseCount('total_member', 0);
    }

    public static function jenisLaporanProvider(): array
    {
        return [
            'gabungan' => ['gabungan'],
            'ampera' => ['ampera'],
            'pal' => ['pal'],
        ];
    }

    public function test_can_upload_different_jenis_laporan_for_same_periode(): void
    {
        LaporanUpload::factory()
            ->jenis_laporan('ampera')
            ->periode(11, 2026)
            ->create();

        $file = ExcelReportFactory::new()
            ->jenis_laporan('pal')
            ->periode(11, 2026)
            ->withTopProductCount(2)
            ->withWorstProductCount(2)
            ->withSupplierCount(2)
            ->toUploadedFile();

        $this->postUpload([
            'jenis_laporan' => 'pal',
            'periode_bulan' => 11,
            'periode_tahun' => 2026,
            'file' => $file,
        ], $this->adminToken)
            ->assertOk();

        $this->assertSame(2, LaporanUpload::count());
    }
}
