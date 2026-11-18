<?php

namespace Tests\Feature\ManageReports;

use App\Models\LaporanUpload;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ListReportsTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private int $fakeReportsCount = 70;

    private string $adminToken;

    private string $ownerToken;

    private const OUTCOME_SUCCESS = 0;

    private const OUTCOME_NOT_FOUND = 1;

    private const OUTCOME_INVALID_INPUT = 2;

    private const OUTCOME_NOT_INVALID_INPUT = 3;

    protected function setUp(): void
    {
        parent::setUp();

        $date = Carbon::create(2026, 11);

        for($i = 0; $i < $this->fakeReportsCount; $i++) {
            LaporanUpload::factory()->periode($date->month, $date->year)->create();
            $date->addMonthNoOverflow();
        }

        $this->adminToken = User::factory()->create([
            'role' => 'it_staff',
        ])->createToken('auth_token')->plainTextToken;
        $this->ownerToken = User::factory()->create([
            'role' => 'owner',
        ])->createToken('auth_token')->plainTextToken;
    }

    public static function reportsPerPageParamsProvider(): array
    {
        return [
            '-10 report (rejected)' => [-10, self::OUTCOME_INVALID_INPUT],
            '-1 report (rejected)' => [-1, self::OUTCOME_INVALID_INPUT],
            '0 reports (accepted)' => [0, self::OUTCOME_INVALID_INPUT],
            '10 reports (accepted)' => [10, self::OUTCOME_NOT_INVALID_INPUT],
            '20 reports (accepted)' => [20, self::OUTCOME_NOT_INVALID_INPUT],
            '40 reports (rejected)' => [40, self::OUTCOME_INVALID_INPUT],
            '50 reports (accepted)' => [50, self::OUTCOME_NOT_INVALID_INPUT],
            '2000000000 reports (rejected)' => [2000000000, self::OUTCOME_INVALID_INPUT],
        ];
    }

    public static function monthInputDataProvider(): array
    {
        $monthAcceptedInputCases = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12];
        $monthInvalidInputCases = array_merge([0, -1, 13, -12, 'a', '', '#']);

        $cases = [];

        foreach ($monthAcceptedInputCases as $value) {
            $cases["periode_bulan has '{$value}' symbol (accepted)"] = [
                $value,
                self::OUTCOME_NOT_INVALID_INPUT,
            ];
        }

        foreach ($monthInvalidInputCases as $value) {
            $cases["periode_bulan has '{$value}' symbol (rejected)"] = [
                $value,
                self::OUTCOME_INVALID_INPUT,
            ];
        }

        return $cases;
    }

    public static function yearInputDataProvider(): array
    {
        $monthAcceptedInputCases = [2026, 2025, 1000, 9, 50000];
        $monthInvalidInputCases = array_merge([-1, -12, -2026, 'a', '', '@']);

        $cases = [];

        foreach ($monthAcceptedInputCases as $value) {
            $cases["periode_tahun has '{$value}' symbol (accepted)"] = [
                $value,
                self::OUTCOME_NOT_INVALID_INPUT,
            ];
        }

        foreach ($monthInvalidInputCases as $value) {
            $cases["periode_tahun has '{$value}' symbol (rejected)"] = [
                $value,
                self::OUTCOME_INVALID_INPUT,
            ];
        }

        return $cases;
    }

    public static function jenisLaporanInputDataProvider(): array
    {
        $jenisLaporanAcceptedInputCases = ['ampera', 'pal', 'gabungan'];
        $jenisLaporanInvalidInputCases = ['ampera1', 'paI', 1];

        $cases = [];

        foreach ($jenisLaporanAcceptedInputCases as $value) {
            $cases["jenis_laporan has '{$value}' symbol (accepted)"] = [
                $value,
                self::OUTCOME_NOT_INVALID_INPUT,
            ];
        }

        foreach ($jenisLaporanInvalidInputCases as $value) {
            $cases["jenis_laporan has '{$value}' symbol (rejected)"] = [
                $value,
                self::OUTCOME_INVALID_INPUT,
            ];
        }

        return $cases;
    }

    public function test_list_reports()
    {
        $readAccountsResponse = $this->getJson('/api/list-reports', [
            'Authorization' => 'Bearer '.$this->adminToken,
        ]);
        $readAccountsResponse->assertOk();
    }

    #[DataProvider('reportsPerPageParamsProvider')]
    public function test_list_reports_per_page(int $perPage, int $exptectedOutcome)
    {
        $listAccountsResponse = $this->getJson('/api/list-reports?per_page='.($perPage), [
            'Authorization' => 'Bearer '.$this->adminToken,
        ]);
        if ($exptectedOutcome === self::OUTCOME_NOT_INVALID_INPUT) {
            $listAccountsResponse->assertOk()->assertJsonCount($perPage, 'data');
        } else if ($exptectedOutcome === self::OUTCOME_INVALID_INPUT) {
            $listAccountsResponse->assertUnprocessable();
        } else {
            $this->fail('Unexpected outcome');
        }
    }

    #[DataProvider('monthInputDataProvider')]
    public function test_list_reports_month_filter(mixed $bulan, int $exptectedOutcome)
    {
        $listAccountsResponse = $this->getJson('/api/list-reports?periode_bulan='.($bulan), [
            'Authorization' => 'Bearer '.$this->adminToken,
        ]);
        if ($exptectedOutcome === self::OUTCOME_NOT_INVALID_INPUT) {
            $listAccountsResponse->assertOk();
            // Extract the data and convert to a Laravel Collection
            $reports = collect($listAccountsResponse->json('data'));

            // Assert that EVERY item matches the filter
            $this->assertTrue(
                $reports->every(fn($report) => $report['periode_bulan'] == $bulan),
                "Response contains reports that do not match the month filter: {$bulan}"
            );
        } else if ($exptectedOutcome === self::OUTCOME_INVALID_INPUT) {
            $listAccountsResponse->assertUnprocessable();
        } else {
            $this->fail('Unexpected outcome');
        }
    }

    #[DataProvider('yearInputDataProvider')]
    public function test_list_reports_year_filter(mixed $tahun, int $exptectedOutcome)
    {
        $listAccountsResponse = $this->getJson('/api/list-reports?periode_tahun='.($tahun), [
            'Authorization' => 'Bearer '.$this->adminToken,
        ]);
        if ($exptectedOutcome === self::OUTCOME_NOT_INVALID_INPUT) {
            $listAccountsResponse->assertOk();
        } else if ($exptectedOutcome === self::OUTCOME_INVALID_INPUT) {
            $listAccountsResponse->assertUnprocessable();
        } else {
            $this->fail('Unexpected outcome');
        }
    }

    #[DataProvider('jenisLaporanInputDataProvider')]
    public function test_list_reports_jenis_laporan_filter(mixed $jenis_laporan, int $exptectedOutcome)
    {
        $listAccountsResponse = $this->getJson('/api/list-reports?jenis_laporan='.($jenis_laporan), [
            'Authorization' => 'Bearer '.$this->adminToken,
        ]);
        if ($exptectedOutcome === self::OUTCOME_NOT_INVALID_INPUT) {
            $listAccountsResponse->assertOk();
        } else if ($exptectedOutcome === self::OUTCOME_INVALID_INPUT) {
            $listAccountsResponse->assertUnprocessable();
        } else {
            $this->fail('Unexpected outcome');
        }
    }

    public function test_cannot_list_reports_if_user_is_not_admin()
    {
        $readAccountsResponse = $this->getJson('/api/list-reports', [
            'Authorization' => 'Bearer '.$this->ownerToken,
        ]);
        $readAccountsResponse->assertForbidden();
    }

    public function test_cannot_list_reports_if_user_is_not_logged_in()
    {
        $readAccountsResponse = $this->getJson('/api/list-reports');
        $readAccountsResponse->assertUnauthorized();
    }
}
