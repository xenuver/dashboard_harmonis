<?php

namespace Tests\Feature\Dashboard;

use App\Models\Cabang;
use App\Models\DataTransaksi;
use App\Models\LaporanUpload;
use App\Models\MemberCount;
use App\Models\SalesHarian;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private string $adminToken;

    private string $ownerToken;

    private const REPORT_TEST_JENIS = 'gabungan';

    private const REPORT_TEST_MONTH = 11;

    private const REPORT_TEST_YEAR = 2026;

    private const OUTCOME_INVALID_INPUT = 2;

    private const OUTCOME_NOT_INVALID_INPUT = 3;

    private const FETCH_DASHBOARD_JSON_TOP_KEYS = [
        'kpi',
        'trend',
        'transaksi',
        'top_produk_terlaris',
        'top_produk_terendah',
        'top_supplier',
    ];

    private const FETCH_DASHBOARD_JSON_STRUCTURE = [
        'kpi' => [
            'total_sales',
            'jumlah_transaksi',
            'growth_member',
        ],
        'trend',
        'transaksi' => [
            'transaksi_member',
            'transaksi_non_member',
        ],
        'top_produk_terlaris' => [
            '*' => [
                'id',
                'upload_id',
                'kategori',
                'nama_brg',
                'qty',
                'harga_jual',
            ],
        ],
        'top_produk_terendah' => [
            '*' => [
                'id',
                'upload_id',
                'kategori',
                'nama_brg',
                'qty',
                'harga_jual',
            ],
        ],
        'top_supplier' => [
            '*' => [
                'id',
                'upload_id',
                'kode_supp',
                'nama_supp',
                'gross_total',
            ],
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $this->adminToken = User::factory()->create([
            'role' => 'it_staff',
        ])->createToken('auth_token')->plainTextToken;

        $this->ownerToken = User::factory()->create([
            'role' => 'owner',
        ])->createToken('auth_token')->plainTextToken;
    }

    public static function viewDashboardDataProvider(): array
    {
        return [
            // Combinations of missing / present inputs (all fallback to accepted defaults)
            'all fields filled (accepted)' => [
                self::REPORT_TEST_JENIS,
                self::REPORT_TEST_MONTH,
                self::REPORT_TEST_YEAR,
                self::OUTCOME_NOT_INVALID_INPUT,
            ],
            'only jenis laporan and periode bulan (accepted)' => [
                self::REPORT_TEST_JENIS,
                self::REPORT_TEST_MONTH,
                null,
                self::OUTCOME_NOT_INVALID_INPUT,
            ],
            'only jenis laporan and periode tahun (accepted)' => [
                self::REPORT_TEST_JENIS,
                null,
                self::REPORT_TEST_YEAR,
                self::OUTCOME_NOT_INVALID_INPUT,
            ],
            'only periode bulan and periode tahun (accepted)' => [
                null,
                self::REPORT_TEST_MONTH,
                self::REPORT_TEST_YEAR,
                self::OUTCOME_NOT_INVALID_INPUT,
            ],
            'empty query params (accepted)' => [
                null,
                null,
                null,
                self::OUTCOME_NOT_INVALID_INPUT,
            ],

            // jenis_laporan valid values
            'jenis_laporan ampera (accepted)' => [
                'ampera',
                self::REPORT_TEST_MONTH,
                self::REPORT_TEST_YEAR,
                self::OUTCOME_NOT_INVALID_INPUT,
            ],
            'jenis_laporan pal (accepted)' => [
                'pal',
                self::REPORT_TEST_MONTH,
                self::REPORT_TEST_YEAR,
                self::OUTCOME_NOT_INVALID_INPUT,
            ],

            // jenis_laporan invalid values
            'jenis_laporan empty string (rejected)' => [
                '',
                self::REPORT_TEST_MONTH,
                self::REPORT_TEST_YEAR,
                self::OUTCOME_INVALID_INPUT,
            ],
            'jenis_laporan invalid enum (rejected)' => [
                'invalid_cabang',
                self::REPORT_TEST_MONTH,
                self::REPORT_TEST_YEAR,
                self::OUTCOME_INVALID_INPUT,
            ],
            'jenis_laporan special chars (rejected)' => [
                'ampera!',
                self::REPORT_TEST_MONTH,
                self::REPORT_TEST_YEAR,
                self::OUTCOME_INVALID_INPUT,
            ],

            // periode_bulan boundary and valid values
            'periode_bulan lower boundary 1 (accepted)' => [
                self::REPORT_TEST_JENIS,
                1,
                self::REPORT_TEST_YEAR,
                self::OUTCOME_NOT_INVALID_INPUT,
            ],
            'periode_bulan upper boundary 12 (accepted)' => [
                self::REPORT_TEST_JENIS,
                12,
                self::REPORT_TEST_YEAR,
                self::OUTCOME_NOT_INVALID_INPUT,
            ],

            // periode_bulan invalid values
            'periode_bulan below boundary 0 (rejected)' => [
                self::REPORT_TEST_JENIS,
                0,
                self::REPORT_TEST_YEAR,
                self::OUTCOME_INVALID_INPUT,
            ],
            'periode_bulan above boundary 13 (rejected)' => [
                self::REPORT_TEST_JENIS,
                13,
                self::REPORT_TEST_YEAR,
                self::OUTCOME_INVALID_INPUT,
            ],
            'periode_bulan negative (rejected)' => [
                self::REPORT_TEST_JENIS,
                -1,
                self::REPORT_TEST_YEAR,
                self::OUTCOME_INVALID_INPUT,
            ],
            'periode_bulan non-numeric string (rejected)' => [
                self::REPORT_TEST_JENIS,
                'invalid',
                self::REPORT_TEST_YEAR,
                self::OUTCOME_INVALID_INPUT,
            ],

            // periode_tahun boundary and valid values
            'periode_tahun lower boundary 0 (accepted)' => [
                self::REPORT_TEST_JENIS,
                self::REPORT_TEST_MONTH,
                0,
                self::OUTCOME_NOT_INVALID_INPUT,
            ],
            'periode_tahun upper boundary 65535 (accepted)' => [
                self::REPORT_TEST_JENIS,
                self::REPORT_TEST_MONTH,
                65535,
                self::OUTCOME_NOT_INVALID_INPUT,
            ],

            // periode_tahun invalid values
            'periode_tahun negative (rejected)' => [
                self::REPORT_TEST_JENIS,
                self::REPORT_TEST_MONTH,
                -1,
                self::OUTCOME_INVALID_INPUT,
            ],
            'periode_tahun above 65535 (rejected)' => [
                self::REPORT_TEST_JENIS,
                self::REPORT_TEST_MONTH,
                65536,
                self::OUTCOME_INVALID_INPUT,
            ],
            'periode_tahun non-numeric string (rejected)' => [
                self::REPORT_TEST_JENIS,
                self::REPORT_TEST_MONTH,
                'invalid_year',
                self::OUTCOME_INVALID_INPUT,
            ],
        ];
    }

    #[DataProvider('viewDashboardDataProvider')]
    public function test_validate_fetch_dashboard_data_input_with(mixed $jenisLaporan, mixed $periodeBulan, mixed $periodeTahun, mixed $expectedOutcome): void
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

        $url = '/api/dashboard';

        if (! empty($requestParams)) {
            $url = $url.'?'.http_build_query($requestParams);
        }

        $getDashboardResponse = $this->getJson($url, [
            'Authorization' => 'Bearer '.$this->adminToken,
        ]);

        if ($expectedOutcome === self::OUTCOME_INVALID_INPUT) {
            $getDashboardResponse->assertUnprocessable();
        } elseif ($expectedOutcome === self::OUTCOME_NOT_INVALID_INPUT) {
            $status = $getDashboardResponse->status();
            $this->assertNotEquals(422, $status);
            $this->assertFalse($status >= 500 && $status <= 599);
        } else {
            $this->fail('Unexpected outcome');
        }
    }

    public function test_can_get_dashboard_data_as_admin(): void
    {
        $report = LaporanUpload::factory()
            ->jenis_laporan('gabungan')
            ->withSalesHarian()
            ->withDataTransaksi()
            ->withMemberCount()
            ->withProdukPenjualan(15, 15)
            ->withSupplierPenjualan(15)
            ->create();

        $getDashboardResponse = $this->getJson('/api/dashboard', [
            'Authorization' => 'Bearer '.$this->adminToken,
        ]);

        $getDashboardResponse->assertOk();
        $getDashboardResponse->assertJsonStructure(self::FETCH_DASHBOARD_JSON_STRUCTURE);

        // Verify top rankings are limited to 10
        $getDashboardResponse->assertJsonCount(10, 'top_produk_terlaris');
        $getDashboardResponse->assertJsonCount(10, 'top_produk_terendah');
        $getDashboardResponse->assertJsonCount(10, 'top_supplier');

        // Verify KPI and transaction semantic accuracy
        $expectedTotalSales = (int) SalesHarian::where('upload_id', $report->id)->sum('total_penjualan');
        $dataTransaksi = DataTransaksi::where('upload_id', $report->id)->first();
        $memberCount = MemberCount::where('upload_id', $report->id)->first();

        $getDashboardResponse->assertJsonPath('kpi.total_sales', $expectedTotalSales)
            ->assertJsonPath('kpi.jumlah_transaksi', $dataTransaksi->getTotalTransaction())
            ->assertJsonPath('kpi.growth_member', $memberCount->member_baru)
            ->assertJsonPath('transaksi.transaksi_member', $dataTransaksi->getTotalMemberTransaction())
            ->assertJsonPath('transaksi.transaksi_non_member', $dataTransaksi->getTotalNonMemberTransaction());
    }

    public function test_can_get_dashboard_data_as_owner(): void
    {
        LaporanUpload::factory()
            ->jenis_laporan('gabungan')
            ->withSalesHarian()
            ->withDataTransaksi()
            ->withMemberCount()
            ->withProdukPenjualan(12, 12)
            ->withSupplierPenjualan(12)
            ->create();

        $getDashboardResponse = $this->getJson('/api/dashboard', [
            'Authorization' => 'Bearer '.$this->ownerToken,
        ]);

        $getDashboardResponse->assertOk();
        $getDashboardResponse->assertJsonStructure(self::FETCH_DASHBOARD_JSON_STRUCTURE);
        $getDashboardResponse->assertJsonCount(10, 'top_produk_terlaris');
        $getDashboardResponse->assertJsonCount(10, 'top_supplier');
    }

    public function test_can_get_dashboard_data_with_explicit_query_params(): void
    {
        $cabangAmpera = Cabang::where('kode', 'AMPERA')->firstOrFail();

        $reportAmpera = LaporanUpload::factory()
            ->jenis_laporan('ampera')
            ->periode(5, 2025)
            ->withDataTransaksi()
            ->withMemberCount()
            ->create();

        SalesHarian::factory()
            ->upload_id($reportAmpera->id)
            ->cabang_id($cabangAmpera->id)
            ->tanggal(Carbon::create(2025, 5, 1))
            ->total_penjualan(2500000)
            ->create();

        $response = $this->getJson('/api/dashboard?jenis_laporan=ampera&periode_bulan=5&periode_tahun=2025', [
            'Authorization' => 'Bearer '.$this->adminToken,
        ]);

        $response->assertOk()
            ->assertJsonPath('kpi.total_sales', 2500000);
    }

    public function test_calculates_total_growth_with_previous_month(): void
    {
        $cabang = Cabang::firstOrFail();

        // Previous month (Oct 2026) with 1,000,000 total sales
        $prevReport = LaporanUpload::factory()
            ->jenis_laporan('gabungan')
            ->periode(10, 2026)
            ->create();

        SalesHarian::factory()
            ->upload_id($prevReport->id)
            ->cabang_id($cabang->id)
            ->tanggal(Carbon::create(2026, 10, 1))
            ->total_penjualan(1000000)
            ->create();

        // Current month (Nov 2026) with 1,500,000 total sales (+50% growth)
        $currentReport = LaporanUpload::factory()
            ->jenis_laporan('gabungan')
            ->periode(11, 2026)
            ->withDataTransaksi()
            ->withMemberCount()
            ->create();

        SalesHarian::factory()
            ->upload_id($currentReport->id)
            ->cabang_id($cabang->id)
            ->tanggal(Carbon::create(2026, 11, 1))
            ->total_penjualan(1500000)
            ->create();

        $response = $this->getJson('/api/dashboard?jenis_laporan=gabungan&periode_bulan=11&periode_tahun=2026', [
            'Authorization' => 'Bearer '.$this->adminToken,
        ]);

        $response->assertOk()
            ->assertJsonPath('kpi.total_sales', 1500000)
            ->assertJsonPath('kpi.total_growth', '50');
    }

    public function test_calculates_growth_across_year_boundary(): void
    {
        $cabang = Cabang::firstOrFail();

        // December 2025: 2,000,000 sales
        $decReport = LaporanUpload::factory()
            ->jenis_laporan('gabungan')
            ->periode(12, 2025)
            ->create();

        SalesHarian::factory()
            ->upload_id($decReport->id)
            ->cabang_id($cabang->id)
            ->tanggal(Carbon::create(2025, 12, 1))
            ->total_penjualan(2000000)
            ->create();

        // January 2026: 2,200,000 sales (+10% growth)
        $janReport = LaporanUpload::factory()
            ->jenis_laporan('gabungan')
            ->periode(1, 2026)
            ->withDataTransaksi()
            ->withMemberCount()
            ->create();

        SalesHarian::factory()
            ->upload_id($janReport->id)
            ->cabang_id($cabang->id)
            ->tanggal(Carbon::create(2026, 1, 1))
            ->total_penjualan(2200000)
            ->create();

        $response = $this->getJson('/api/dashboard?jenis_laporan=gabungan&periode_bulan=1&periode_tahun=2026', [
            'Authorization' => 'Bearer '.$this->adminToken,
        ]);

        $response->assertOk()
            ->assertJsonPath('kpi.total_sales', 2200000)
            ->assertJsonPath('kpi.total_growth', '10');
    }

    public function test_omits_total_growth_when_previous_month_has_zero_sales(): void
    {
        $cabang = Cabang::firstOrFail();

        // Prev month with 0 total sales
        $prevReport = LaporanUpload::factory()
            ->jenis_laporan('gabungan')
            ->periode(10, 2026)
            ->create();

        SalesHarian::factory()
            ->upload_id($prevReport->id)
            ->cabang_id($cabang->id)
            ->tanggal(Carbon::create(2026, 10, 1))
            ->total_penjualan(0)
            ->create();

        // Current month
        $currentReport = LaporanUpload::factory()
            ->jenis_laporan('gabungan')
            ->periode(11, 2026)
            ->withDataTransaksi()
            ->withMemberCount()
            ->create();

        SalesHarian::factory()
            ->upload_id($currentReport->id)
            ->cabang_id($cabang->id)
            ->tanggal(Carbon::create(2026, 11, 1))
            ->total_penjualan(500000)
            ->create();

        $response = $this->getJson('/api/dashboard?jenis_laporan=gabungan&periode_bulan=11&periode_tahun=2026', [
            'Authorization' => 'Bearer '.$this->adminToken,
        ]);

        $response->assertOk()
            ->assertJsonMissingPath('kpi.total_growth');
    }

    public function test_omits_total_growth_when_no_previous_month_report_exists(): void
    {
        $report = LaporanUpload::factory()
            ->jenis_laporan('gabungan')
            ->periode(11, 2026)
            ->withSalesHarian()
            ->withDataTransaksi()
            ->withMemberCount()
            ->create();

        $response = $this->getJson('/api/dashboard?jenis_laporan=gabungan&periode_bulan=11&periode_tahun=2026', [
            'Authorization' => 'Bearer '.$this->adminToken,
        ]);

        $response->assertOk()
            ->assertJsonMissingPath('kpi.total_growth');
    }

    public function test_trend_data_grouped_by_date(): void
    {
        $cabang = Cabang::firstOrFail();

        $report = LaporanUpload::factory()
            ->jenis_laporan('ampera')
            ->periode(5, 2026)
            ->withDataTransaksi()
            ->withMemberCount()
            ->create();

        $date1 = Carbon::create(2026, 5, 1);
        $date2 = Carbon::create(2026, 5, 2);

        SalesHarian::factory()
            ->upload_id($report->id)
            ->cabang_id($cabang->id)
            ->tanggal($date1)
            ->total_penjualan(100000)
            ->create();

        SalesHarian::factory()
            ->upload_id($report->id)
            ->cabang_id($cabang->id)
            ->tanggal($date2)
            ->total_penjualan(200000)
            ->create();

        $response = $this->getJson('/api/dashboard?jenis_laporan=ampera&periode_bulan=5&periode_tahun=2026', [
            'Authorization' => 'Bearer '.$this->adminToken,
        ]);

        $response->assertOk()
            ->assertJsonPath('trend.2026-05-01.0.cabang', $cabang->nama)
            ->assertJsonPath('trend.2026-05-01.0.total', 100000)
            ->assertJsonPath('trend.2026-05-02.0.cabang', $cabang->nama)
            ->assertJsonPath('trend.2026-05-02.0.total', 200000);
    }

    public function test_return_not_found_if_report_unavailable(): void
    {
        $getDashboardResponse = $this->getJson('/api/dashboard', [
            'Authorization' => 'Bearer '.$this->ownerToken,
        ]);

        $getDashboardResponse->assertNotFound();
        $getDashboardResponse->assertJsonMissingPaths(self::FETCH_DASHBOARD_JSON_TOP_KEYS);
    }

    public function test_return_not_found_if_specific_report_unavailable(): void
    {
        $getDashboardResponse = $this->getJson('/api/dashboard?periode_bulan=1&periode_tahun=1990', [
            'Authorization' => 'Bearer '.$this->ownerToken,
        ]);

        $getDashboardResponse->assertNotFound();
        $getDashboardResponse->assertJsonMissingPaths(self::FETCH_DASHBOARD_JSON_TOP_KEYS);
    }

    public function test_cannot_show_data_while_logged_out(): void
    {
        $getDashboardResponse = $this->getJson('/api/dashboard?periode_bulan=11&periode_tahun=2026');

        $getDashboardResponse->assertUnauthorized();
        $getDashboardResponse->assertJsonMissingPaths(self::FETCH_DASHBOARD_JSON_TOP_KEYS);
    }

    public function test_disabled_user_cannot_access_dashboard(): void
    {
        $disabledUser = User::factory()->create([
            'enabled' => false,
        ]);
        $disabledToken = $disabledUser->createToken('auth_token')->plainTextToken;

        $response = $this->getJson('/api/dashboard', [
            'Authorization' => 'Bearer '.$disabledToken,
        ]);

        $response->assertUnauthorized()
            ->assertJson(['message' => 'Unauthenticated.']);
    }
}
