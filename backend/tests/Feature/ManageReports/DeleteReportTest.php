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

    private string $adminToken;

    private string $ownerToken;

    private const REPORT_TEST_MONTH = 11;

    private const REPORT_TEST_YEAR = 2026;

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

    public static function invalidReportIdProvider(): array
    {
        return [
            'alphabetic string' => ['abc'],
            'special characters' => ['%!@'],
            'negative integer' => [-1],
            'zero' => [0],
            'decimal float' => [1.5],
        ];
    }

    public function test_admin_can_delete_report()
    {
        $report = LaporanUpload::factory()
            ->jenis_laporan('pal')
            ->periode(self::REPORT_TEST_MONTH, self::REPORT_TEST_YEAR)
            ->withSalesHarian()
            ->withDataTransaksi()
            ->withMemberCount()
            ->withProdukPenjualan(5, 5)
            ->withSupplierPenjualan(5)
            ->create();

        $reportId = $report->id;

        $response = $this->postJson('/api/delete-report', [
            'reportid' => $reportId,
        ], [
            'Authorization' => 'Bearer '.$this->adminToken,
        ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'Laporan berhasil dihapus',
            ]);

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

    public function test_admin_deleting_report_does_not_delete_other_reports()
    {
        $reportA = LaporanUpload::factory()
            ->jenis_laporan('ampera')
            ->periode(10, 2026)
            ->withSalesHarian()
            ->withDataTransaksi()
            ->withMemberCount()
            ->create();

        $reportB = LaporanUpload::factory()
            ->jenis_laporan('pal')
            ->periode(11, 2026)
            ->withSalesHarian()
            ->withDataTransaksi()
            ->withMemberCount()
            ->create();

        $response = $this->postJson('/api/delete-report', [
            'reportid' => $reportA->id,
        ], [
            'Authorization' => 'Bearer '.$this->adminToken,
        ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'Laporan berhasil dihapus',
            ]);

        // Report A and its related records should be deleted
        $this->assertDatabaseMissing('laporan_uploads', ['id' => $reportA->id]);
        $this->assertDatabaseMissing('data_transaksi', ['upload_id' => $reportA->id]);
        $this->assertDatabaseMissing('sales_harian', ['upload_id' => $reportA->id]);
        $this->assertDatabaseMissing('total_member', ['upload_id' => $reportA->id]);

        // Report B and its related records must still exist
        $this->assertDatabaseHas('laporan_uploads', ['id' => $reportB->id]);
        $this->assertDatabaseHas('data_transaksi', ['upload_id' => $reportB->id]);
        $this->assertDatabaseHas('sales_harian', ['upload_id' => $reportB->id]);
        $this->assertDatabaseHas('total_member', ['upload_id' => $reportB->id]);
    }

    public function test_admin_cannot_delete_report_without_report_id()
    {
        $response = $this->postJson('/api/delete-report', [], [
            'Authorization' => 'Bearer '.$this->adminToken,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('reportid');
    }

    public function test_admin_cannot_delete_report_with_non_existent_id()
    {
        $nonExistentId = (LaporanUpload::max('id') ?? 0) + 999;

        $response = $this->postJson('/api/delete-report', [
            'reportid' => $nonExistentId,
        ], [
            'Authorization' => 'Bearer '.$this->adminToken,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('reportid');
    }

    #[DataProvider('invalidReportIdProvider')]
    public function test_admin_cannot_delete_report_with_invalid_id_type(mixed $invalidId)
    {
        $response = $this->postJson('/api/delete-report', [
            'reportid' => $invalidId,
        ], [
            'Authorization' => 'Bearer '.$this->adminToken,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('reportid');
    }

    public function test_cannot_delete_report_if_user_is_not_admin()
    {
        $topProductsCount = 5;
        $worstProductsCount = 5;
        $supplierCount = 5;

        $report = LaporanUpload::factory()
            ->jenis_laporan('ampera')
            ->periode(self::REPORT_TEST_MONTH, self::REPORT_TEST_YEAR)
            ->withSalesHarian()
            ->withDataTransaksi()
            ->withMemberCount()
            ->withProdukPenjualan($topProductsCount, $worstProductsCount)
            ->withSupplierPenjualan($supplierCount)
            ->create();

        $reportId = $report->id;

        $response = $this->postJson('/api/delete-report', [
            'reportid' => $reportId,
        ], [
            'Authorization' => 'Bearer '.$this->ownerToken,
        ]);

        $response->assertForbidden();

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

        $this->assertEquals($topProductsCount, ProdukPenjualan::where('upload_id', $reportId)->where('kategori', 'tertinggi')->count());
        $this->assertEquals($worstProductsCount, ProdukPenjualan::where('upload_id', $reportId)->where('kategori', 'terendah')->count());

        $this->assertDatabaseHas('supplier_penjualan', [
            'upload_id' => $reportId,
        ]);

        $this->assertEquals($supplierCount, SupplierPenjualan::where('upload_id', $reportId)->count());
    }

    public function test_cannot_delete_report_if_user_is_not_logged_in()
    {
        $topProductsCount = 5;
        $worstProductsCount = 5;
        $supplierCount = 5;

        $report = LaporanUpload::factory()
            ->jenis_laporan('ampera')
            ->periode(self::REPORT_TEST_MONTH, self::REPORT_TEST_YEAR)
            ->withSalesHarian()
            ->withDataTransaksi()
            ->withMemberCount()
            ->withProdukPenjualan($topProductsCount, $worstProductsCount)
            ->withSupplierPenjualan($supplierCount)
            ->create();

        $reportId = $report->id;

        $response = $this->postJson('/api/delete-report', [
            'reportid' => $reportId,
        ]);

        $response->assertUnauthorized();

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

        $this->assertEquals($topProductsCount, ProdukPenjualan::where('upload_id', $reportId)->where('kategori', 'tertinggi')->count());
        $this->assertEquals($worstProductsCount, ProdukPenjualan::where('upload_id', $reportId)->where('kategori', 'terendah')->count());

        $this->assertDatabaseHas('supplier_penjualan', [
            'upload_id' => $reportId,
        ]);

        $this->assertEquals($supplierCount, SupplierPenjualan::where('upload_id', $reportId)->count());
    }
}
