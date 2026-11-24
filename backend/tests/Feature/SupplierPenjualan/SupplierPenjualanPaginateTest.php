<?php

namespace Tests\Feature\SupplierPenjualan;

use App\Models\LaporanUpload;
use App\Models\SupplierPenjualan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SupplierPenjualanPaginateTest extends TestCase
{
    use RefreshDatabase;
    use SupplierPenjualanTestHelpers;

    private string $ownerToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ownerToken = User::factory()->create([
            'role' => 'owner',
        ])->createToken('auth_token')->plainTextToken;
    }

    // ── Page number validation ──────────────────────────────

    public static function supplierPenjualanAcceptedPageNumberProvider(): array
    {
        $pageNumberValidInputCases = [1, 2, 3];

        $cases = [];

        foreach ($pageNumberValidInputCases as $value) {
            $cases["page set to '{$value}' (accepted)"] = [
                $value,
            ];
        }

        return $cases;
    }

    public static function supplierPenjualanRejectedPageNumberProvider(): array
    {
        $pageNumberInvalidInputCases = ['', 0, -5, 'dua', 'seven', '&'];

        $cases = [];

        foreach ($pageNumberInvalidInputCases as $value) {
            $cases["page set to '{$value}' (rejected)"] = [
                $value,
            ];
        }

        return $cases;
    }

    #[DataProvider('supplierPenjualanAcceptedPageNumberProvider')]
    public function test_can_paginate_between_pages(mixed $page): void
    {
        $itemCount = 50;
        LaporanUpload::factory()->jenis_laporan('gabungan')->withSupplierPenjualan($itemCount)->create();

        $response = $this->getJson($this->supplierUrl([
            'page' => $page,
            'jenis_laporan' => 'gabungan',
        ]), [
            'Authorization' => 'Bearer '.$this->ownerToken,
        ]);

        $response->assertOk();
        $response->assertJsonStructure(self::FETCH_SUPPLIER_PENJUALAN_JSON_STRUCTURE);
        $this->assertEquals($itemCount, $response->json('total'));
    }

    #[DataProvider('supplierPenjualanRejectedPageNumberProvider')]
    public function test_cannot_paginate_between_pages(mixed $page): void
    {
        LaporanUpload::factory()->jenis_laporan('gabungan')->withSupplierPenjualan()->create();

        $response = $this->getJson($this->supplierUrl([
            'page' => $page,
            'jenis_laporan' => 'gabungan',
        ]), [
            'Authorization' => 'Bearer '.$this->ownerToken,
        ]);

        $response->assertUnprocessable();
    }

    // ── per_page validation ─────────────────────────────────

    public static function supplierPenjualanAcceptedPerPageProvider(): array
    {
        return [
            "per_page set to '10' (accepted)" => [10],
            "per_page set to '20' (accepted)" => [20],
            "per_page set to '50' (accepted)" => [50],
        ];
    }

    public static function supplierPenjualanRejectedPerPageProvider(): array
    {
        $rejectedValues = [1, 5, 100, 0, -10, 'sepuluh', ''];

        $cases = [];
        foreach ($rejectedValues as $value) {
            $cases["per_page set to '{$value}' (rejected)"] = [$value];
        }

        return $cases;
    }

    #[DataProvider('supplierPenjualanAcceptedPerPageProvider')]
    public function test_can_set_per_page(int $perPage): void
    {
        LaporanUpload::factory()->jenis_laporan('gabungan')->withSupplierPenjualan(60)->create();

        $response = $this->getJson($this->supplierUrl([
            'per_page' => $perPage,
            'jenis_laporan' => 'gabungan',
        ]), [
            'Authorization' => 'Bearer '.$this->ownerToken,
        ]);

        $response->assertOk();
        $response->assertJsonPath('per_page', $perPage);
    }

    #[DataProvider('supplierPenjualanRejectedPerPageProvider')]
    public function test_cannot_set_invalid_per_page(mixed $perPage): void
    {
        LaporanUpload::factory()->jenis_laporan('gabungan')->withSupplierPenjualan(10)->create();

        $response = $this->getJson($this->supplierUrl([
            'per_page' => $perPage,
            'jenis_laporan' => 'gabungan',
        ]), [
            'Authorization' => 'Bearer '.$this->ownerToken,
        ]);

        $response->assertUnprocessable();
    }

    // ── Pagination behaviour ────────────────────────────────

    public function test_can_return_data_less_than_one_page(): void
    {
        $itemCount = 5;
        LaporanUpload::factory()->jenis_laporan('gabungan')->withSupplierPenjualan($itemCount)->create();
        $perPage = 10;

        $response = $this->getJson($this->supplierUrl([
            'per_page' => $perPage,
            'jenis_laporan' => 'gabungan',
        ]), [
            'Authorization' => 'Bearer '.$this->ownerToken,
        ]);

        $response->assertOk();
        $response->assertJsonStructure(self::FETCH_SUPPLIER_PENJUALAN_JSON_STRUCTURE);
        $this->assertEquals($itemCount, count($response->json('data')));
    }

    public function test_last_page_possibly_return_less_data(): void
    {
        $itemCount = 30;
        LaporanUpload::factory()->jenis_laporan('gabungan')->withSupplierPenjualan($itemCount)->create();
        $perPage = 10;

        $expectedLastPage = ceil($itemCount / $perPage);
        $expectedLastPageItemCount = $itemCount % $perPage;

        if ($expectedLastPage !== 0 && $expectedLastPageItemCount === 0) {
            $expectedLastPageItemCount = $perPage;
        }

        $response = $this->getJson($this->supplierUrl([
            'page' => $expectedLastPage,
            'per_page' => $perPage,
            'jenis_laporan' => 'gabungan',
        ]), [
            'Authorization' => 'Bearer '.$this->ownerToken,
        ]);

        $response->assertOk();
        $response->assertJsonStructure(self::FETCH_SUPPLIER_PENJUALAN_JSON_STRUCTURE);
        $this->assertEquals($expectedLastPageItemCount, count($response->json('data')));
    }

    public function test_return_no_data_beyond_last_page(): void
    {
        $itemCount = 20;
        LaporanUpload::factory()->jenis_laporan('gabungan')->withSupplierPenjualan($itemCount)->create();
        $perPage = 10;

        $beyondLastPage = ceil($itemCount / $perPage) + 5;

        $response = $this->getJson($this->supplierUrl([
            'page' => $beyondLastPage,
            'per_page' => $perPage,
            'jenis_laporan' => 'gabungan',
        ]), [
            'Authorization' => 'Bearer '.$this->ownerToken,
        ]);

        $response->assertOk();
        $response->assertJsonStructure(self::FETCH_SUPPLIER_PENJUALAN_JSON_STRUCTURE);
        $this->assertEquals(0, count($response->json('data')));
    }

    // ── Ordering ────────────────────────────────────────────

    public function test_results_are_ordered_by_gross_total_descending(): void
    {
        $laporan = LaporanUpload::factory()->jenis_laporan('gabungan')->create();
        SupplierPenjualan::factory()->upload_id($laporan->id)->gross_total(300_000_000)->create();
        SupplierPenjualan::factory()->upload_id($laporan->id)->gross_total(900_000_000)->create();
        SupplierPenjualan::factory()->upload_id($laporan->id)->gross_total(600_000_000)->create();

        $response = $this->getJson($this->supplierUrl([
            'jenis_laporan' => 'gabungan',
        ]), [
            'Authorization' => 'Bearer '.$this->ownerToken,
        ]);

        $response->assertOk();
        $grossTotals = collect($response->json('data'))->pluck('gross_total')->values()->all();

        $this->assertEquals([900_000_000, 600_000_000, 300_000_000], $grossTotals);
    }
}
