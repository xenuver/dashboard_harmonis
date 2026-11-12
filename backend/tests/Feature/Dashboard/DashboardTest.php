<?php

namespace Tests\Feature\Dashboard;

use App\Models\LaporanUpload;
use App\Models\User;
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

    private const OUTCOME_SUCCESS = 0;

    private const OUTCOME_NOT_FOUND = 1;

    private const OUTCOME_INVALID_INPUT = 2;

    private const OUTCOME_NOT_INVALID_INPUT = 3;

    private const FETCH_DASHBOARD_JSON_STRUCTURE = [
        'kpi',
        'trend',
        'transaksi',
        'top_produk_terlaris',
        'top_produk_terendah',
        'top_supplier',
    ];  

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

    public static function viewDashboardDataProvider(): array
    {
        $invalidInputCases = ['', '.', ',', '/', '\\', '!', '~', '#', '+', '|', '$', '?', '&', ':', ';', '{', '}', '(', ')', '[', ']', '\"', '\''];

        $jenisLaporanAcceptedInputCases = ['gabungan', 'ampera', 'pal'];
        $jenisLaporanInvalidInputCases = array_merge(['ampera1', 'jeruju', 'paI'], $invalidInputCases); 

        $monthAcceptedInputCases = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12];
        $monthInvalidInputCases = array_merge([0, -1, 13, -12], $invalidInputCases);

        $yearAcceptedInputCases = [0, 2023, 6000, 50000];
        $yearInvalidInputCases = array_merge([-1000, -5, 65536, 100000], $invalidInputCases);

        $cases = [
            'periode, bulan, and tahun filled (accepted)' => [
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
            'empty (accepted)' => [
                null,
                null,
                null,
                self::OUTCOME_NOT_INVALID_INPUT,
            ],
        ];

        foreach($jenisLaporanAcceptedInputCases as $value) {
            $cases["jenis_laporan has '{$value}' symbol (accepted)"] = [
                $value,
                self::REPORT_TEST_MONTH,
                self::REPORT_TEST_YEAR,
                self::OUTCOME_NOT_INVALID_INPUT,
            ];
        }

        foreach($monthAcceptedInputCases as $value) {
            $cases["periode_bulan has '{$value}' symbol (accepted)"] = [
                self::REPORT_TEST_JENIS,
                $value,
                self::REPORT_TEST_YEAR,
                self::OUTCOME_NOT_INVALID_INPUT,
            ];
        }

        foreach($yearAcceptedInputCases as $value) {
            $cases["periode_tahun has '{$value}' symbol (accepted)"] = [
                self::REPORT_TEST_JENIS,
                self::REPORT_TEST_MONTH,
                $value,
                self::OUTCOME_NOT_INVALID_INPUT,
            ];
        }

        foreach($jenisLaporanInvalidInputCases as $value) {
            $cases["jenis_laporan has '{$value}' symbol (rejected)"] = [
                $value,
                self::REPORT_TEST_MONTH,
                self::REPORT_TEST_YEAR,
                self::OUTCOME_INVALID_INPUT,
            ];
        }

        foreach($monthInvalidInputCases as $value) {
            $cases["periode_bulan has '{$value}' symbol (rejected)"] = [
                self::REPORT_TEST_JENIS,
                $value,
                self::REPORT_TEST_YEAR,
                self::OUTCOME_INVALID_INPUT,
            ];
        }

        foreach($yearInvalidInputCases as $value) {
            $cases["periode_tahun has '{$value}' symbol (rejected)"] = [
                self::REPORT_TEST_JENIS,
                self::REPORT_TEST_MONTH,
                $value,
                self::OUTCOME_INVALID_INPUT,
            ];
        }

        return $cases;
    }

    #[DataProvider('viewDashboardDataProvider')]
    public function test_validate_fetch_dashboard_data_input_with(mixed $jenisLaporan, mixed $periodeBulan, mixed $periodeTahun, mixed $expectedOutcome)
    {
        $requestParams = [];

        if($jenisLaporan !== null) {
            $requestParams['jenis_laporan'] = $jenisLaporan;
        }
        if($periodeBulan !== null) {
            $requestParams['periode_bulan'] = $periodeBulan;
        }
        if($periodeTahun !== null) {
            $requestParams['periode_tahun'] = $periodeTahun;
        }

        $url = '/api/dashboard';

        if(!empty($requestParams)) {
            $url = $url.'?'.http_build_query($requestParams);
        }

        $getDashboardResponse = $this->getJson($url, [
            'Authorization' => 'Bearer '.$this->adminToken,
        ]);

        if ($expectedOutcome === self::OUTCOME_INVALID_INPUT) {
            $getDashboardResponse->assertUnprocessable();
        } else if ($expectedOutcome === self::OUTCOME_NOT_INVALID_INPUT) {
            $status = $getDashboardResponse->status();
            $this->assertNotEquals(422, $status);
            $this->assertFalse($status >= 500 && $status <= 599);
        } else {
            $this->assertTrue(false);
        }
    }

    public function test_can_get_dashboard_data_as_admin(): void
    {
        LaporanUpload::factory()->jenis_laporan('gabungan')->generateFullReport()->create();

        $getDashboardResponse = $this->getJson('/api/dashboard', [
            'Authorization' => 'Bearer '.($this->adminToken),
        ]);

        $getDashboardResponse->assertOk();
        $getDashboardResponse->assertJsonStructure(self::FETCH_DASHBOARD_JSON_STRUCTURE);
    }

    public function test_can_get_dashboard_data_as_owner(): void
    {
        LaporanUpload::factory()->jenis_laporan('gabungan')->generateFullReport()->create();

        $getDashboardResponse = $this->getJson('/api/dashboard', [
            'Authorization' => 'Bearer '.($this->ownerToken),
        ]);

        $getDashboardResponse->assertOk();
        $getDashboardResponse->assertJsonStructure(self::FETCH_DASHBOARD_JSON_STRUCTURE);
    }

    public function test_return_not_found_if_report_unavailable(): void
    {
        $getDashboardResponse = $this->getJson('/api/dashboard', [
            'Authorization' => 'Bearer '.($this->ownerToken),
        ]);

        $getDashboardResponse->assertNotFound();
        $getDashboardResponse->assertJsonMissingPaths(self::FETCH_DASHBOARD_JSON_STRUCTURE);
    }

    public function test_return_not_found_if_specific_report_unavailable(): void
    {
        $year = fake()->numberBetween(2000, 2026);
        $month = fake()->numberBetween(1, 12);
        $getDashboardResponse = $this->getJson("/api/dashboard?periode_bulan={$month}&periode_tahun={$year}", [
            'Authorization' => 'Bearer '.($this->ownerToken),
        ]);

        $getDashboardResponse->assertNotFound();
        $getDashboardResponse->assertJsonMissingPaths(self::FETCH_DASHBOARD_JSON_STRUCTURE);
    }

    public function test_cannot_show_data_while_logged_out(): void
    {
        $year = fake()->numberBetween(2000, 2026);
        $month = fake()->numberBetween(1, 12);
        $getDashboardResponse = $this->getJson("/api/dashboard?periode_bulan={$month}&periode_tahun={$year}");

        $getDashboardResponse->assertUnauthorized();
        $getDashboardResponse->assertJsonMissingPaths(self::FETCH_DASHBOARD_JSON_STRUCTURE);
    }
}
