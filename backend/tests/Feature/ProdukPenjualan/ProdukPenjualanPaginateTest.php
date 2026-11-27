<?php

namespace Tests\Feature\ProdukPenjualan;

use App\Models\LaporanUpload;
use App\Models\ProdukPenjualan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProdukPenjualanPaginateTest extends TestCase
{
    use ProdukPenjualanTestHelpers;
    use RefreshDatabase;

    private string $ownerToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ownerToken = User::factory()->create([
            'role' => 'owner',
        ])->createToken('auth_token')->plainTextToken;
    }

    public static function produkPenjualanAcceptedPageNumberProvider(): array
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

    public static function produkPenjualanRejectedPageNumberProvider(): array
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

    #[DataProvider('produkPenjualanAcceptedPageNumberProvider')]
    public function test_can_paginate_between_pages(mixed $page): void
    {
        $newLaporan = LaporanUpload::factory()->jenis_laporan('gabungan')->withProdukPenjualan(1, 300)->create();
        $itemCount = ProdukPenjualan::where('upload_id', $newLaporan->id)->where('kategori', 'terendah')->count();

        $getProdukPenjualanResponse = $this->getJson($this->produkUrl([
            'page' => $page,
            'jenis_laporan' => 'gabungan',
            'kategori' => 'terendah',
        ]), [
            'Authorization' => 'Bearer '.($this->ownerToken),
        ]);

        $getProdukPenjualanResponse->assertOk();

        $getProdukPenjualanResponse->assertJsonStructure(self::FETCH_PRODUK_PENJUALAN_JSON_STRUCTURE);
        $getProdukPenjualanResponseJson = $getProdukPenjualanResponse->json();

        $this->assertEquals($itemCount, $getProdukPenjualanResponseJson['total']);
    }

    #[DataProvider('produkPenjualanRejectedPageNumberProvider')]
    public function test_cannot_paginate_between_pages(mixed $page): void
    {
        LaporanUpload::factory()->jenis_laporan('gabungan')->withProdukPenjualan(1, 300)->create();

        $getProdukPenjualanResponse = $this->getJson($this->produkUrl([
            'page' => $page,
            'jenis_laporan' => 'gabungan',
            'kategori' => 'terendah',
        ]), [
            'Authorization' => 'Bearer '.($this->ownerToken),
        ]);

        $getProdukPenjualanResponse->assertUnprocessable();
    }

    public function test_can_return_data_less_than_one_page(): void
    {
        $itemCount = 5;
        LaporanUpload::factory()->jenis_laporan('gabungan')->withProdukPenjualan(1, $itemCount)->create();
        $perPage = 10;

        $getProdukPenjualanResponse = $this->getJson($this->produkUrl([
            'per_page' => $perPage,
            'jenis_laporan' => 'gabungan',
            'kategori' => 'terendah',
        ]), [
            'Authorization' => 'Bearer '.($this->ownerToken),
        ]);

        $getProdukPenjualanResponse->assertOk();

        $getProdukPenjualanResponse->assertJsonStructure(self::FETCH_PRODUK_PENJUALAN_JSON_STRUCTURE);
        $getProdukPenjualanResponseJson = $getProdukPenjualanResponse->json();

        $this->assertEquals($itemCount, count($getProdukPenjualanResponseJson['data']));
    }

    public function test_last_page_possibly_return_less_data(): void
    {
        $itemCount = 30;
        LaporanUpload::factory()->jenis_laporan('gabungan')->withProdukPenjualan(1, $itemCount)->create();
        $perPage = 10;

        $expectedLastPage = ceil($itemCount / $perPage);
        $expectedLastPageItemCount = $itemCount % $perPage;

        if ($expectedLastPage !== 0 && $expectedLastPageItemCount === 0) {
            $expectedLastPageItemCount = $perPage;
        }

        $getProdukPenjualanResponse = $this->getJson($this->produkUrl([
            'page' => $expectedLastPage,
            'per_page' => $perPage,
            'jenis_laporan' => 'gabungan',
            'kategori' => 'terendah',
        ]), [
            'Authorization' => 'Bearer '.($this->ownerToken),
        ]);

        $getProdukPenjualanResponse->assertOk();

        $getProdukPenjualanResponse->assertJsonStructure(self::FETCH_PRODUK_PENJUALAN_JSON_STRUCTURE);
        $getProdukPenjualanResponseJson = $getProdukPenjualanResponse->json();

        $this->assertEquals($expectedLastPageItemCount, count($getProdukPenjualanResponseJson['data']));
    }

    public static function produkPenjualanAcceptedPerPageProvider(): array
    {
        return [
            "per_page set to '10' (accepted)" => [10],
            "per_page set to '20' (accepted)" => [20],
            "per_page set to '50' (accepted)" => [50],
        ];
    }

    public static function produkPenjualanRejectedPerPageProvider(): array
    {
        $rejectedValues = [1, 5, 100, 0, -10, 'sepuluh', ''];

        $cases = [];
        foreach ($rejectedValues as $value) {
            $cases["per_page set to '{$value}' (rejected)"] = [$value];
        }

        return $cases;
    }

    #[DataProvider('produkPenjualanAcceptedPerPageProvider')]
    public function test_can_set_per_page(int $perPage): void
    {
        LaporanUpload::factory()->jenis_laporan('gabungan')->withProdukPenjualan(1, 60)->create();

        $getProdukPenjualanResponse = $this->getJson($this->produkUrl([
            'per_page' => $perPage,
            'jenis_laporan' => 'gabungan',
            'kategori' => 'terendah',
        ]), [
            'Authorization' => 'Bearer '.($this->ownerToken),
        ]);

        $getProdukPenjualanResponse->assertOk();
        $getProdukPenjualanResponse->assertJsonPath('per_page', $perPage);
    }

    #[DataProvider('produkPenjualanRejectedPerPageProvider')]
    public function test_cannot_set_invalid_per_page(mixed $perPage): void
    {
        LaporanUpload::factory()->jenis_laporan('gabungan')->withProdukPenjualan(1, 10)->create();

        $getProdukPenjualanResponse = $this->getJson($this->produkUrl([
            'per_page' => $perPage,
            'jenis_laporan' => 'gabungan',
            'kategori' => 'terendah',
        ]), [
            'Authorization' => 'Bearer '.($this->ownerToken),
        ]);

        $getProdukPenjualanResponse->assertUnprocessable();
    }

    public function test_kategori_tertinggi_only_returns_tertinggi_records(): void
    {
        $laporan = LaporanUpload::factory()->jenis_laporan('gabungan')->withProdukPenjualan(5, 5)->create();

        $getProdukPenjualanResponse = $this->getJson($this->produkUrl([
            'jenis_laporan' => 'gabungan',
            'kategori' => 'tertinggi',
        ]), [
            'Authorization' => 'Bearer '.($this->ownerToken),
        ]);

        $getProdukPenjualanResponse->assertOk();
        $data = $getProdukPenjualanResponse->json('data');

        $this->assertNotEmpty($data);
        foreach ($data as $item) {
            $this->assertEquals('tertinggi', $item['kategori']);
        }
    }

    public function test_kategori_terendah_only_returns_terendah_records(): void
    {
        LaporanUpload::factory()->jenis_laporan('gabungan')->withProdukPenjualan(5, 5)->create();

        $getProdukPenjualanResponse = $this->getJson($this->produkUrl([
            'jenis_laporan' => 'gabungan',
            'kategori' => 'terendah',
        ]), [
            'Authorization' => 'Bearer '.($this->ownerToken),
        ]);

        $getProdukPenjualanResponse->assertOk();
        $data = $getProdukPenjualanResponse->json('data');

        $this->assertNotEmpty($data);
        foreach ($data as $item) {
            $this->assertEquals('terendah', $item['kategori']);
        }
    }

    public function test_tertinggi_results_are_ordered_by_qty_descending(): void
    {
        $laporan = LaporanUpload::factory()->jenis_laporan('gabungan')->create();
        ProdukPenjualan::factory()->upload_id($laporan->id)->kategori('tertinggi')->qty(10)->create();
        ProdukPenjualan::factory()->upload_id($laporan->id)->kategori('tertinggi')->qty(50)->create();
        ProdukPenjualan::factory()->upload_id($laporan->id)->kategori('tertinggi')->qty(30)->create();
        ProdukPenjualan::factory()->upload_id($laporan->id)->kategori('terendah')->qty(1)->create();

        $getProdukPenjualanResponse = $this->getJson($this->produkUrl([
            'jenis_laporan' => 'gabungan',
            'kategori' => 'tertinggi',
        ]), [
            'Authorization' => 'Bearer '.($this->ownerToken),
        ]);

        $getProdukPenjualanResponse->assertOk();
        $qtys = collect($getProdukPenjualanResponse->json('data'))->pluck('qty')->values()->all();

        $this->assertEquals([50, 30, 10], $qtys);
    }

    public function test_terendah_results_are_ordered_by_qty_ascending(): void
    {
        $laporan = LaporanUpload::factory()->jenis_laporan('gabungan')->create();
        ProdukPenjualan::factory()->upload_id($laporan->id)->kategori('terendah')->qty(30)->create();
        ProdukPenjualan::factory()->upload_id($laporan->id)->kategori('terendah')->qty(5)->create();
        ProdukPenjualan::factory()->upload_id($laporan->id)->kategori('terendah')->qty(20)->create();
        ProdukPenjualan::factory()->upload_id($laporan->id)->kategori('tertinggi')->qty(100)->create();

        $getProdukPenjualanResponse = $this->getJson($this->produkUrl([
            'jenis_laporan' => 'gabungan',
            'kategori' => 'terendah',
        ]), [
            'Authorization' => 'Bearer '.($this->ownerToken),
        ]);

        $getProdukPenjualanResponse->assertOk();
        $qtys = collect($getProdukPenjualanResponse->json('data'))->pluck('qty')->values()->all();

        $this->assertEquals([5, 20, 30], $qtys);
    }

    public function test_return_no_data_beyond_last_page(): void
    {
        $itemCount = 20;
        LaporanUpload::factory()->jenis_laporan('gabungan')->withProdukPenjualan(1, $itemCount)->create();
        $perPage = 10;

        $expectedLastPage = ceil($itemCount / $perPage) + 5;

        $getProdukPenjualanResponse = $this->getJson($this->produkUrl([
            'page' => $expectedLastPage,
            'per_page' => $perPage,
            'jenis_laporan' => 'gabungan',
            'kategori' => 'terendah',
        ]), [
            'Authorization' => 'Bearer '.($this->ownerToken),
        ]);

        $getProdukPenjualanResponse->assertOk();

        $getProdukPenjualanResponse->assertJsonStructure(self::FETCH_PRODUK_PENJUALAN_JSON_STRUCTURE);
        $getProdukPenjualanResponseJson = $getProdukPenjualanResponse->json();

        $this->assertEquals(0, count($getProdukPenjualanResponseJson['data']));
    }
}
