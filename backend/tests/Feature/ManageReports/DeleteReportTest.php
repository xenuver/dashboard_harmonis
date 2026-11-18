<?php

namespace Tests\Feature\ManageReports;

use App\Models\LaporanUpload;
use App\Models\ProdukPenjualan;
use App\Models\SupplierPenjualan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DeleteReportTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private User $ownerUser;

    private const REPORT_TEST_JENIS = 'gabungan';

    private const REPORT_TEST_MONTH = 11;

    private const REPORT_TEST_YEAR = 2026;

    private string $adminToken;

    private string $ownerToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create([
            'role' => 'it_staff',
        ]);
        $this->ownerUser = User::factory()->create([
            'role' => 'owner',
        ]);

        $this->adminToken = $this->adminUser->createToken('auth_token')->plainTextToken;
        $this->ownerToken = $this->ownerUser->createToken('auth_token')->plainTextToken;

        $this->seed();
    }

    public static function deleteInvalidUserIdProvider()
    {
        $invalidInputCases = ['a', 'admin', 'username', '%', '!', -1];

        $cases = [];

        foreach ($invalidInputCases as $value) {
            $cases['set to '.(json_encode($value))] = [
                $value,
            ];
        }

        return $cases;
    }

    public function test_admin_can_delete_report()
    {
        $report = LaporanUpload::factory()->jenis_laporan('pal')->periode(self::REPORT_TEST_MONTH, self::REPORT_TEST_YEAR)->withSalesHarian()->withDataTransaksi()->withMemberCount()->withProdukPenjualan(5, 5)->withSupplierPenjualan(5)->create();

        $reportId = $report->id;

        $deleteAccountResponse = $this->postJson('/api/delete-report', [
            'reportid' => $reportId,
        ], [
            'Authorization' => 'Bearer '.$this->adminToken,
        ]);
        $deleteAccountResponse->assertOk();

        $this->assertDatabaseMissing('laporan_uploads', [
            'id' => $reportId,
        ]);

        $this->assertDatabaseMissing('data_transaksi', [
            'upload_id' => $reportId,
        ]);

        $this->assertDatabaseMissing('sales_harian', [
            'upload_id' => $reportId,
        ]);

        $this->assertDatabaseMissing('total_member', [
            'upload_id' => $reportId,
        ]);

        $this->assertDatabaseMissing('produk_penjualan', [
            'upload_id' => $reportId,
        ]);

        $this->assertDatabaseMissing('supplier_penjualan', [
            'upload_id' => $reportId,
        ]);
    }

    public function test_admin_cannot_delete_report_with_invalid_id()
    {
        $deleteReportResponse = $this->postJson('/api/delete-report', [
            'reportid' => 69,
        ], [
            'Authorization' => 'Bearer '.$this->adminToken,
        ]);
        $deleteReportResponse->assertUnprocessable();
    }

    public function test_admin_cannot_delete_report_without_report_id()
    {
        $deleteReportResponse = $this->postJson('/api/delete-report', [], [
            'Authorization' => 'Bearer '.$this->adminToken,
        ]);
        $deleteReportResponse->assertUnprocessable();
    }

    #[DataProvider('deleteInvalidUserIdProvider')]
    public function test_admin_cannot_delete_report_with_report_id(mixed $reportId)
    {
        $deleteAccountResponse = $this->postJson('/api/delete-report', [
            'reportid' => $reportId,
        ], [
            'Authorization' => 'Bearer '.$this->adminToken,
        ]);
        $deleteAccountResponse->assertUnprocessable();
        $deleteAccountResponse->assertJsonValidationErrors('reportid');
    }

    public function test_admin_cannot_delete_report_with_nonexist3nt_report()
    {
        $reportId = (LaporanUpload::max('id') ?? 0) + 1;

        $deleteAccountResponse = $this->postJson('/api/delete-report', [
            'reportid' => $reportId,
        ], [
            'Authorization' => 'Bearer '.$this->adminToken,
        ]);
        $deleteAccountResponse->assertUnprocessable();
        $deleteAccountResponse->assertJsonValidationErrors('reportid');
    }

    public function test_cannot_delete_report_if_user_is_not_admin()
    {
        $topProductsCount = 5;
        $worstProductsCount = 5;
        $supplierCount = 5;

        $report = LaporanUpload::factory()->jenis_laporan('ampera')->periode(self::REPORT_TEST_MONTH, self::REPORT_TEST_YEAR)->withSalesHarian()->withDataTransaksi()->withMemberCount()->withProdukPenjualan(5, 5)->withSupplierPenjualan(5)->create();
        $reportId = $report->id;

        $deleteReportResponse = $this->postJson('/api/delete-report', [
            'reportid' => $reportId,
        ], [
            'Authorization' => 'Bearer '.$this->ownerToken,
        ]);
        $deleteReportResponse->assertForbidden();

        $this->assertDatabaseHas('laporan_uploads', [
            'id' => $reportId,
        ]);

        $this->assertDatabaseHas('data_transaksi', [
            'upload_id' => $reportId,
        ]);

        $this->assertDatabaseHas('sales_harian', [
            'upload_id' => $reportId,
        ]);

        $this->assertDatabaseHas('total_member', [
            'upload_id' => $reportId,
        ]);

        $this->assertDatabaseHas('produk_penjualan', [
            'upload_id' => $reportId,
        ]);

        $this->assertTrue(ProdukPenjualan::where('upload_id', $reportId)->where('kategori', 'tertinggi')->count() === $topProductsCount);
        $this->assertTrue(ProdukPenjualan::where('upload_id', $reportId)->where('kategori', 'terendah')->count() === $worstProductsCount);

        $this->assertDatabaseHas('supplier_penjualan', [
            'upload_id' => $reportId,
        ]);

        $this->assertTrue(SupplierPenjualan::where('upload_id', $reportId)->count() === $supplierCount);
    }

    public function test_cannot_delete_report_if_user_is_not_logged_in()
    {
        $topProductsCount = 5;
        $worstProductsCount = 5;
        $supplierCount = 5;

        $report = LaporanUpload::factory()->jenis_laporan('ampera')->periode(self::REPORT_TEST_MONTH, self::REPORT_TEST_YEAR)->withSalesHarian()->withDataTransaksi()->withMemberCount()->withProdukPenjualan($topProductsCount, $worstProductsCount)->withSupplierPenjualan($supplierCount)->create();

        $reportId = $report->id;

        $deleteReportResponse = $this->postJson('/api/delete-report', [
            'reportid' => $reportId,
        ]);
        $deleteReportResponse->assertUnauthorized();

        $this->assertDatabaseHas('laporan_uploads', [
            'id' => $reportId,
        ]);

        $this->assertDatabaseHas('data_transaksi', [
            'upload_id' => $reportId,
        ]);

        $this->assertDatabaseHas('sales_harian', [
            'upload_id' => $reportId,
        ]);

        $this->assertDatabaseHas('total_member', [
            'upload_id' => $reportId,
        ]);

        $this->assertDatabaseHas('produk_penjualan', [
            'upload_id' => $reportId,
        ]);

        $this->assertTrue(ProdukPenjualan::where('upload_id', $reportId)->where('kategori', 'tertinggi')->count() === $topProductsCount);
        $this->assertTrue(ProdukPenjualan::where('upload_id', $reportId)->where('kategori', 'terendah')->count() === $worstProductsCount);

        $this->assertDatabaseHas('supplier_penjualan', [
            'upload_id' => $reportId,
        ]);
    }
}
