<?php

namespace Tests\Feature\ProdukPenjualan;

use App\Models\LaporanUpload;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProdukPenjualanMainTest extends TestCase
{
    use ProdukPenjualanTestHelpers;
    use RefreshDatabase;

    private string $adminToken;

    private string $ownerToken;

    private const REPORT_TEST_JENIS = 'gabungan';

    private const REPORT_TEST_MONTH = 11;

    private const REPORT_TEST_YEAR = 2026;

    private const REPORT_TEST_KATEGORI = 'tertinggi';

    private const OUTCOME_INVALID_INPUT = 2;

    private const OUTCOME_NOT_INVALID_INPUT = 3;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminToken = User::factory()->create([
            'role' => 'it_staff',
        ])->createToken('auth_token')->plainTextToken;
        $this->ownerToken = User::factory()->create([
            'role' => 'owner',
        ])->createToken('auth_token')->plainTextToken;
    }

    public static function produkPenjualanMonthYearKategoriProvider(): array
    {
        $jenisLaporanAcceptedInputCases = ['gabungan', 'ampera', 'pal'];
        $jenisLaporanInvalidInputCases = ['ampera1', 'jeruju', 'paI', ''];

        $monthAcceptedInputCases = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12];
        $monthInvalidInputCases = [0, -1, 13, -12, ''];

        $yearAcceptedInputCases = [0, 2023, 6000, 50000];
        $yearInvalidInputCases = [-1000, -5, 65536, 100000, ''];

        $kategoriAcceptedInputCases = ['tertinggi', 'terendah'];
        $kategoriInvalidInputCases = ['sedang', 'bagus', 'makanan', 'elektronik', ''];

        $cases = [
            'periode_bulan, periode_tahun, and kategori filled (accepted)' => [
                self::REPORT_TEST_JENIS,
                self::REPORT_TEST_MONTH,
                self::REPORT_TEST_YEAR,
                self::REPORT_TEST_KATEGORI,
                self::OUTCOME_NOT_INVALID_INPUT,
            ],
            'only periode_bulan (accepted)' => [
                self::REPORT_TEST_JENIS,
                self::REPORT_TEST_MONTH,
                null,
                self::REPORT_TEST_KATEGORI,
                self::OUTCOME_NOT_INVALID_INPUT,
            ],
            'only periode_tahun (accepted)' => [
                self::REPORT_TEST_JENIS,
                null,
                self::REPORT_TEST_YEAR,
                self::REPORT_TEST_KATEGORI,
                self::OUTCOME_NOT_INVALID_INPUT,
            ],
            'only periode_bulan and periode_tahun (accepted)' => [
                null,
                self::REPORT_TEST_MONTH,
                self::REPORT_TEST_YEAR,
                self::REPORT_TEST_KATEGORI,
                self::OUTCOME_NOT_INVALID_INPUT,
            ],
            'not filled (accepted)' => [
                null,
                null,
                null,
                null,
                self::OUTCOME_NOT_INVALID_INPUT,
            ],
        ];

        foreach ($jenisLaporanAcceptedInputCases as $value) {
            $cases["jenis_laporan has '{$value}' symbol (accepted)"] = [
                $value,
                self::REPORT_TEST_MONTH,
                self::REPORT_TEST_YEAR,
                self::REPORT_TEST_KATEGORI,
                self::OUTCOME_NOT_INVALID_INPUT,
            ];
        }

        foreach ($monthAcceptedInputCases as $value) {
            $cases["periode_bulan has '{$value}' symbol (accepted)"] = [
                self::REPORT_TEST_JENIS,
                $value,
                self::REPORT_TEST_YEAR,
                self::REPORT_TEST_KATEGORI,
                self::OUTCOME_NOT_INVALID_INPUT,
            ];
        }

        foreach ($yearAcceptedInputCases as $value) {
            $cases["periode_tahun has '{$value}' symbol (accepted)"] = [
                self::REPORT_TEST_JENIS,
                self::REPORT_TEST_MONTH,
                $value,
                self::REPORT_TEST_KATEGORI,
                self::OUTCOME_NOT_INVALID_INPUT,
            ];
        }

        foreach ($kategoriAcceptedInputCases as $value) {
            $cases["kategori has '{$value}' symbol (accepted)"] = [
                self::REPORT_TEST_JENIS,
                self::REPORT_TEST_MONTH,
                self::REPORT_TEST_YEAR,
                $value,
                self::OUTCOME_NOT_INVALID_INPUT,
            ];
        }

        foreach ($jenisLaporanInvalidInputCases as $value) {
            $cases["jenis_laporan has '{$value}' symbol (rejected)"] = [
                $value,
                self::REPORT_TEST_MONTH,
                self::REPORT_TEST_YEAR,
                self::REPORT_TEST_KATEGORI,
                self::OUTCOME_INVALID_INPUT,
            ];
        }

        foreach ($monthInvalidInputCases as $value) {
            $cases["periode_bulan has '{$value}' symbol (rejected)"] = [
                self::REPORT_TEST_JENIS,
                $value,
                self::REPORT_TEST_YEAR,
                self::REPORT_TEST_KATEGORI,
                self::OUTCOME_INVALID_INPUT,
            ];
        }

        foreach ($yearInvalidInputCases as $value) {
            $cases["periode_tahun has '{$value}' symbol (rejected)"] = [
                self::REPORT_TEST_JENIS,
                self::REPORT_TEST_MONTH,
                $value,
                self::REPORT_TEST_KATEGORI,
                self::OUTCOME_INVALID_INPUT,
            ];
        }

        foreach ($kategoriInvalidInputCases as $value) {
            $cases["kategori has '{$value}' symbol (rejected)"] = [
                self::REPORT_TEST_JENIS,
                self::REPORT_TEST_MONTH,
                self::REPORT_TEST_YEAR,
                $value,
                self::OUTCOME_INVALID_INPUT,
            ];
        }

        return $cases;
    }

    #[DataProvider('produkPenjualanMonthYearKategoriProvider')]
    public function test_validate_produk_penjualan_input_with(mixed $jenisLaporan, mixed $periodeBulan, mixed $periodeTahun, mixed $kategori, mixed $expectedOutcome)
    {
        $requestParams = [];

        if ($jenisLaporan !== null) {
            $requestParams['jenis_laporan'] = $jenisLaporan;
        }
        if ($periodeBulan !== null) {
            $requestParams['periode_bulan'] = $periodeBulan;
        }
        if ($periodeTahun !== null) {
            $requestParams['periode_tahun'] = $periodeTahun;
        }
        if ($kategori !== null) {
            $requestParams['kategori'] = $kategori;
        }

        $getProdukPenjualanResponse = $this->getJson($this->produkUrl($requestParams), [
            'Authorization' => 'Bearer '.$this->adminToken,
        ]);

        if ($expectedOutcome === self::OUTCOME_INVALID_INPUT) {
            $getProdukPenjualanResponse->assertUnprocessable();
        } elseif ($expectedOutcome === self::OUTCOME_NOT_INVALID_INPUT) {
            $getProdukPenjualanResponse->assertNotFound();
        } else {
            $this->fail('Unexpected outcome');
        }
    }

    public function test_can_get_produk_penjualan_as_admin(): void
    {
        LaporanUpload::factory()->jenis_laporan('gabungan')->withProdukPenjualan(10, 10)->create();

        $getProdukPenjualanResponse = $this->getJson($this->produkUrl(), [
            'Authorization' => 'Bearer '.($this->adminToken),
        ]);

        $getProdukPenjualanResponse->assertOk();
        $getProdukPenjualanResponse->assertJsonStructure(self::FETCH_PRODUK_PENJUALAN_JSON_STRUCTURE);
    }

    public function test_can_get_produk_penjualan_as_owner(): void
    {
        LaporanUpload::factory()->jenis_laporan('gabungan')->withProdukPenjualan(10, 10)->create();

        $getProdukPenjualanResponse = $this->getJson($this->produkUrl(), [
            'Authorization' => 'Bearer '.($this->ownerToken),
        ]);

        $getProdukPenjualanResponse->assertOk();
        $getProdukPenjualanResponse->assertJsonStructure(self::FETCH_PRODUK_PENJUALAN_JSON_STRUCTURE);
    }

    public function test_return_not_found_if_report_not_found(): void
    {
        LaporanUpload::factory()->jenis_laporan('gabungan')->periode(self::REPORT_TEST_MONTH, self::REPORT_TEST_YEAR)->withProdukPenjualan(10, 10)->create();

        $prevMonthDate = Carbon::create(self::REPORT_TEST_YEAR, self::REPORT_TEST_MONTH, 1)->subMonth();
        $lastMonth = $prevMonthDate->month;
        $lastYear = $prevMonthDate->year;

        $getProdukPenjualanResponse = $this->getJson($this->produkUrl([
            'periode_bulan' => $lastMonth,
            'periode_tahun' => $lastYear,
            'kategori' => self::REPORT_TEST_KATEGORI,
        ]), [
            'Authorization' => 'Bearer '.($this->adminToken),
        ]);

        $getProdukPenjualanResponse->assertNotFound();
    }

    public function test_cannot_get_produk_penjualan_while_logged_out(): void
    {
        LaporanUpload::factory()->jenis_laporan('gabungan')->withProdukPenjualan(10, 10)->create();

        $getProdukPenjualanResponse = $this->getJson($this->produkUrl());

        $getProdukPenjualanResponse->assertUnauthorized();
        $getProdukPenjualanResponse->assertJsonMissing(self::FETCH_PRODUK_PENJUALAN_JSON_STRUCTURE);
    }
}
