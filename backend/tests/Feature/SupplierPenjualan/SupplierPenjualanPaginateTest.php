<?php

namespace Tests\Feature\SupplierPenjualan;

use App\Models\LaporanUpload;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SupplierPenjualanPaginateTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private string $ownerToken;

    private const FETCH_SUPPLIER_PENJUALAN_JSON_STRUCTURE = [
        'current_page',
        'data' => [
            '*' => [
                'id',
                'upload_id',
                'kode_supp',
                'nama_supp',
                'gross_total',
                'net_sales_total',
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
        LaporanUpload::factory()->jenis_laporan('gabungan')->top_products_count(1)->worst_products_count(1)->supplier_count($itemCount)->generateFullReport()->create();

        $requestParams = [
            'page' => $page,
            'jenis_laporan' => 'gabungan',
            'kategori' => 'terendah',
        ];

        $url = '/api/supplier-penjualan';

        if (! empty($requestParams)) {
            $url = $url.'?'.http_build_query($requestParams);
        }

        $getSupplierPenjualanResponse = $this->getJson($url, [
            'Authorization' => 'Bearer '.($this->ownerToken),
        ]);

        $getSupplierPenjualanResponse->assertOk();

        $getSupplierPenjualanResponse->assertJsonStructure(self::FETCH_SUPPLIER_PENJUALAN_JSON_STRUCTURE);
        $getSupplierPenjualanResponseJson = $getSupplierPenjualanResponse->json();

        $this->assertEquals($itemCount, $getSupplierPenjualanResponseJson['total']);
    }

    #[DataProvider('supplierPenjualanRejectedPageNumberProvider')]
    public function test_cannot_paginate_between_pages(mixed $page): void
    {
        LaporanUpload::factory()->jenis_laporan('gabungan')->top_products_count(1)->worst_products_count(1)->generateFullReport()->create();

        $requestParams = [
            'page' => $page,
            'jenis_laporan' => 'gabungan',
            'kategori' => 'terendah',
        ];

        $url = '/api/supplier-penjualan';

        if (! empty($requestParams)) {
            $url = $url.'?'.http_build_query($requestParams);
        }

        $getSupplierPenjualanResponse = $this->getJson($url, [
            'Authorization' => 'Bearer '.($this->ownerToken),
        ]);

        $getSupplierPenjualanResponse->assertUnprocessable();
    }

    public function test_can_return_data_less_than_one_page(): void
    {
        $itemCount = 5;
        LaporanUpload::factory()->jenis_laporan('gabungan')->top_products_count(1)->worst_products_count(1)->supplier_count($itemCount)->generateFullReport()->create();
        $perPage = 10;

        $requestParams = [
            'per_page' => $perPage,
            'jenis_laporan' => 'gabungan',
        ];

        $url = '/api/supplier-penjualan';

        if (! empty($requestParams)) {
            $url = $url.'?'.http_build_query($requestParams);
        }

        $getSupplierPenjualanResponse = $this->getJson($url, [
            'Authorization' => 'Bearer '.($this->ownerToken),
        ]);

        $getSupplierPenjualanResponse->assertOk();

        $getSupplierPenjualanResponse->assertJsonStructure(self::FETCH_SUPPLIER_PENJUALAN_JSON_STRUCTURE);
        $getSupplierPenjualanResponseJson = $getSupplierPenjualanResponse->json();

        $this->assertEquals($itemCount, count($getSupplierPenjualanResponseJson['data']));
    }

    public function test_last_page_possibly_return_less_data(): void
    {
        $itemCount = 30;
        LaporanUpload::factory()->jenis_laporan('gabungan')->top_products_count(1)->worst_products_count(1)->supplier_count($itemCount)->generateFullReport()->create();
        $perPage = 10;

        $exptectedLastPage = ceil($itemCount / $perPage);
        $exptectedLastPageItemCount = $itemCount % $perPage;

        if ($exptectedLastPage !== 0 && $exptectedLastPageItemCount === 0) {
            $exptectedLastPageItemCount = $perPage;
        }

        $requestParams = [
            'page' => $exptectedLastPage,
            'per_page' => $perPage,
            'jenis_laporan' => 'gabungan',
            'kategori' => 'terendah',
        ];

        $url = '/api/supplier-penjualan';

        if (! empty($requestParams)) {
            $url = $url.'?'.http_build_query($requestParams);
        }

        $getSupplierPenjualanResponse = $this->getJson($url, [
            'Authorization' => 'Bearer '.($this->ownerToken),
        ]);

        $getSupplierPenjualanResponse->assertOk();

        $getSupplierPenjualanResponse->assertJsonStructure(self::FETCH_SUPPLIER_PENJUALAN_JSON_STRUCTURE);
        $getSupplierPenjualanResponseJson = $getSupplierPenjualanResponse->json();

        $this->assertEquals($exptectedLastPageItemCount, count($getSupplierPenjualanResponseJson['data']));
    }

    public function test_return_no_data_beyond_last_page(): void
    {
        $itemCount = 20;
        LaporanUpload::factory()->jenis_laporan('gabungan')->top_products_count(1)->worst_products_count(1)->supplier_count($itemCount)->generateFullReport()->create();
        $perPage = 10;

        $exptectedLastPage = ceil($itemCount / $perPage) + 5;

        $requestParams = [
            'page' => $exptectedLastPage,
            'per_page' => $perPage,
            'jenis_laporan' => 'gabungan',
            'kategori' => 'terendah',
        ];

        $url = '/api/supplier-penjualan';

        if (! empty($requestParams)) {
            $url = $url.'?'.http_build_query($requestParams);
        }

        $getSupplierPenjualanResponse = $this->getJson($url, [
            'Authorization' => 'Bearer '.($this->ownerToken),
        ]);

        $getSupplierPenjualanResponse->assertOk();

        $getSupplierPenjualanResponse->assertJsonStructure(self::FETCH_SUPPLIER_PENJUALAN_JSON_STRUCTURE);
        $getSupplierPenjualanResponseJson = $getSupplierPenjualanResponse->json();

        $this->assertEquals(0, count($getSupplierPenjualanResponseJson['data']));
    }
}
