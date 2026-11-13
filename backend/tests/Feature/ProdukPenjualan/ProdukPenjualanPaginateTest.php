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
    use RefreshDatabase;

    protected $seed = true;

    private string $ownerToken;

    private const FETCH_PRODUK_PENJUALAN_JSON_STRUCTURE = [
        'current_page',
        'data' => [
            '*' => [
                'id',
                'upload_id',
                'kategori',
                'barcode',
                'kode_brg',
                'nama_brg',
                'satuan',
                'harga_jual',
                'jumlah',
                'qty',
                'stok',
                'created_at',
                'updated_at',
            ],
        ],
        'first_page_url',
        'from',
        'last_page',
        'last_page_url',
        'links',
        'next_page_url',
        'path',
        'per_page',
        'prev_page_url',
        'to',
        'total',
    ];

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

        foreach($pageNumberValidInputCases as $value) {
            $cases["page set to '{$value}' (accepted)"] = [
                $value,
            ];
        }

        return $cases;
    }

    public static function produkPenjualanRejectedPageNumberProvider(): array
    {
        $pageNumberInvalidInputCases = ['', 0, -5];

        $cases = [];

        foreach($pageNumberInvalidInputCases as $value) {
            $cases["page set to '{$value}' (rejected)"] = [
                $value,
            ];
        }

        return $cases;
    }

    #[DataProvider('produkPenjualanAcceptedPageNumberProvider')]
    public function test_can_paginate_between_pages(mixed $page): void
    {
        $newLaporan = LaporanUpload::factory()->jenis_laporan('gabungan')->top_products_count(1)->supplier_count(1)->generateFullReport()->create();
        $itemCount = ProdukPenjualan::where('upload_id', $newLaporan->id)->where('kategori', 'terendah')->count();

        $requestParams = [
            'page' => $page,
            'jenis_laporan' => 'gabungan',
            'kategori' => 'terendah',
        ];

        $url = '/api/produk-penjualan';

        if(!empty($requestParams)) {
            $url = $url.'?'.http_build_query($requestParams);
        }

        $getProdukPenjualanResponse = $this->getJson($url, [
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
        LaporanUpload::factory()->jenis_laporan('gabungan')->top_products_count(1)->supplier_count(1)->generateFullReport()->create();

        $requestParams = [
            'page' => $page,
            'jenis_laporan' => 'gabungan',
            'kategori' => 'terendah',
        ];

        $url = '/api/produk-penjualan';

        if(!empty($requestParams)) {
            $url = $url.'?'.http_build_query($requestParams);
        }

        $getProdukPenjualanResponse = $this->getJson($url, [
            'Authorization' => 'Bearer '.($this->ownerToken),
        ]);

        $getProdukPenjualanResponse->assertUnprocessable();
    }

    public function test_can_return_data_less_than_one_page(): void
    {
        $itemCount = 5;
        LaporanUpload::factory()->jenis_laporan('gabungan')->top_products_count(1)->worst_products_count($itemCount)->supplier_count(1)->generateFullReport()->create();
        $perPage = 10;

        $requestParams = [
            'per_page' => $perPage,
            'jenis_laporan' => 'gabungan',
            'kategori' => 'terendah',
        ];

        $url = '/api/produk-penjualan';

        if(!empty($requestParams)) {
            $url = $url.'?'.http_build_query($requestParams);
        }

        $getProdukPenjualanResponse = $this->getJson($url, [
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
        LaporanUpload::factory()->jenis_laporan('gabungan')->top_products_count(1)->worst_products_count($itemCount)->supplier_count(1)->generateFullReport()->create();
        $perPage = 10;

        $exptectedLastPage = ceil($itemCount/$perPage);
        $exptectedLastPageItemCount = $itemCount % $perPage;

        if($exptectedLastPage !== 0 && $exptectedLastPageItemCount === 0 ) {
            $exptectedLastPageItemCount = $perPage;
        }

        $requestParams = [
            'page' => $exptectedLastPage,
            'per_page' => $perPage,
            'jenis_laporan' => 'gabungan',
            'kategori' => 'terendah',
        ];

        $url = '/api/produk-penjualan';

        if(!empty($requestParams)) {
            $url = $url.'?'.http_build_query($requestParams);
        }

        $getProdukPenjualanResponse = $this->getJson($url, [
            'Authorization' => 'Bearer '.($this->ownerToken),
        ]);

        $getProdukPenjualanResponse->assertOk();

        $getProdukPenjualanResponse->assertJsonStructure(self::FETCH_PRODUK_PENJUALAN_JSON_STRUCTURE);
        $getProdukPenjualanResponseJson = $getProdukPenjualanResponse->json();

        $this->assertEquals($exptectedLastPageItemCount, count($getProdukPenjualanResponseJson['data']));
    }

    public function test_return_no_data_beyond_last_page(): void
    {
        $itemCount = 20;
        LaporanUpload::factory()->jenis_laporan('gabungan')->top_products_count(1)->worst_products_count($itemCount)->supplier_count(1)->generateFullReport()->create();
        $perPage = 10;

        $exptectedLastPage = ceil($itemCount/$perPage) + 5;

        $requestParams = [
            'page' => $exptectedLastPage,
            'per_page' => $perPage,
            'jenis_laporan' => 'gabungan',
            'kategori' => 'terendah',
        ];

        $url = '/api/produk-penjualan';

        if(!empty($requestParams)) {
            $url = $url.'?'.http_build_query($requestParams);
        }

        $getProdukPenjualanResponse = $this->getJson($url, [
            'Authorization' => 'Bearer '.($this->ownerToken),
        ]);

        $getProdukPenjualanResponse->assertOk();

        $getProdukPenjualanResponse->assertJsonStructure(self::FETCH_PRODUK_PENJUALAN_JSON_STRUCTURE);
        $getProdukPenjualanResponseJson = $getProdukPenjualanResponse->json();

        $this->assertEquals(0, count($getProdukPenjualanResponseJson['data']));
    }
}
