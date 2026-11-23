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

    private string $adminToken;

    private string $ownerToken;

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

    /**
     * Helper to seed a diverse collection of reports across types, years, and months.
     */
    private function seedSampleReports(): void
    {
        // 5 Ampera reports (2026, months 1 to 5)
        for ($m = 1; $m <= 5; $m++) {
            LaporanUpload::factory()
                ->jenis_laporan('ampera')
                ->periode($m, 2026)
                ->create();
        }

        // 5 Pal reports (2026, months 6 to 10)
        for ($m = 6; $m <= 10; $m++) {
            LaporanUpload::factory()
                ->jenis_laporan('pal')
                ->periode($m, 2026)
                ->create();
        }

        // 15 Gabungan reports (2025: months 1 to 12; 2026: months 11 to 12; 2027: month 1)
        for ($m = 1; $m <= 12; $m++) {
            LaporanUpload::factory()
                ->jenis_laporan('gabungan')
                ->periode($m, 2025)
                ->create();
        }
        for ($m = 11; $m <= 12; $m++) {
            LaporanUpload::factory()
                ->jenis_laporan('gabungan')
                ->periode($m, 2026)
                ->create();
        }
        LaporanUpload::factory()
            ->jenis_laporan('gabungan')
            ->periode(1, 2027)
            ->create();
    }

    public static function validPerPageProvider(): array
    {
        return [
            '10 reports per page' => [10],
            '20 reports per page' => [20],
            '50 reports per page' => [50],
        ];
    }

    public static function invalidPerPageProvider(): array
    {
        return [
            '-10 reports (negative)' => [-10],
            '-1 reports (negative)' => [-1],
            '0 reports (zero)' => [0],
            '15 reports (not in allowed list)' => [15],
            '40 reports (not in allowed list)' => [40],
            '100 reports (not in allowed list)' => [100],
            '2000000000 reports (too large)' => [2000000000],
            'string value' => ['abc'],
        ];
    }

    public static function invalidPageProvider(): array
    {
        return [
            'page -1' => [-1],
            'page 0' => [0],
            'string page' => ['abc'],
        ];
    }

    public static function validJenisLaporanProvider(): array
    {
        return [
            'ampera' => ['ampera', 5],
            'pal' => ['pal', 5],
            'gabungan' => ['gabungan', 15],
        ];
    }

    public static function invalidJenisLaporanProvider(): array
    {
        return [
            'invalid string' => ['invalid'],
            'ampera with suffix' => ['ampera1'],
            'uppercase' => ['AMPERA'],
            'numeric' => [123],
        ];
    }

    public static function validMonthProvider(): array
    {
        return [
            'month 1' => [1],
            'month 5' => [5],
            'month 6' => [6],
            'month 12' => [12],
        ];
    }

    public static function invalidMonthProvider(): array
    {
        return [
            'month 0' => [0],
            'month 13' => [13],
            'month -1' => [-1],
            'string month' => ['abc'],
            'empty string' => [''],
            'special symbol' => ['#'],
        ];
    }

    public static function validYearProvider(): array
    {
        return [
            'year 2025' => [2025, 12],
            'year 2026' => [2026, 12],
            'year 2027' => [2027, 1],
        ];
    }

    public static function invalidYearProvider(): array
    {
        return [
            'year -1' => [-1],
            'year 65536' => [65536],
            'string year' => ['abc'],
            'empty string' => [''],
            'special symbol' => ['@'],
        ];
    }

    public function test_list_reports_default_pagination_and_structure()
    {
        $this->seedSampleReports();

        $response = $this->getJson('/api/list-reports', [
            'Authorization' => 'Bearer '.$this->adminToken,
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'jenis_laporan',
                        'periode_bulan',
                        'periode_tahun',
                        'jumlah_hari',
                        'format_data',
                        'catatan',
                    ],
                ],
                'current_page',
                'per_page',
                'total',
                'last_page',
            ]);

        $this->assertEquals(1, $response->json('current_page'));
        $this->assertEquals(10, $response->json('per_page'));
        $this->assertEquals(25, $response->json('total'));
        $this->assertCount(10, $response->json('data'));
    }

    public function test_list_reports_ordered_by_descending_year_and_month()
    {
        LaporanUpload::factory()->create(['periode_tahun' => 2025, 'periode_bulan' => 12]);
        LaporanUpload::factory()->create(['periode_tahun' => 2026, 'periode_bulan' => 3]);
        LaporanUpload::factory()->create(['periode_tahun' => 2026, 'periode_bulan' => 8]);

        $response = $this->getJson('/api/list-reports', [
            'Authorization' => 'Bearer '.$this->adminToken,
        ]);

        $response->assertOk();
        $data = $response->json('data');

        $this->assertEquals(2026, $data[0]['periode_tahun']);
        $this->assertEquals(8, $data[0]['periode_bulan']);

        $this->assertEquals(2026, $data[1]['periode_tahun']);
        $this->assertEquals(3, $data[1]['periode_bulan']);

        $this->assertEquals(2025, $data[2]['periode_tahun']);
        $this->assertEquals(12, $data[2]['periode_bulan']);
    }

    #[DataProvider('validPerPageProvider')]
    public function test_list_reports_valid_per_page(int $perPage)
    {
        $this->seedSampleReports();

        $response = $this->getJson('/api/list-reports?per_page='.$perPage, [
            'Authorization' => 'Bearer '.$this->adminToken,
        ]);

        $response->assertOk();
        $this->assertEquals($perPage, $response->json('per_page'));

        $expectedCount = min($perPage, 25);
        $this->assertCount($expectedCount, $response->json('data'));
    }

    #[DataProvider('invalidPerPageProvider')]
    public function test_list_reports_invalid_per_page(mixed $perPage)
    {
        $response = $this->getJson('/api/list-reports?per_page='.$perPage, [
            'Authorization' => 'Bearer '.$this->adminToken,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');
    }

    public function test_list_reports_page_navigation()
    {
        $this->seedSampleReports();

        $responsePage1 = $this->getJson('/api/list-reports?per_page=10&page=1', [
            'Authorization' => 'Bearer '.$this->adminToken,
        ]);
        $responsePage2 = $this->getJson('/api/list-reports?per_page=10&page=2', [
            'Authorization' => 'Bearer '.$this->adminToken,
        ]);

        $responsePage1->assertOk();
        $responsePage2->assertOk();

        $page1Ids = collect($responsePage1->json('data'))->pluck('id');
        $page2Ids = collect($responsePage2->json('data'))->pluck('id');

        $this->assertEquals(1, $responsePage1->json('current_page'));
        $this->assertEquals(2, $responsePage2->json('current_page'));

        $this->assertCount(10, $page1Ids);
        $this->assertCount(10, $page2Ids);
        $this->assertEmpty($page1Ids->intersect($page2Ids), 'Page 1 and Page 2 should not share reports.');
    }

    #[DataProvider('invalidPageProvider')]
    public function test_list_reports_invalid_page(mixed $page)
    {
        $response = $this->getJson('/api/list-reports?page='.$page, [
            'Authorization' => 'Bearer '.$this->adminToken,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('page');
    }

    #[DataProvider('validJenisLaporanProvider')]
    public function test_list_reports_filter_by_valid_jenis_laporan(string $jenis, int $expectedTotal)
    {
        $this->seedSampleReports();

        $response = $this->getJson('/api/list-reports?per_page=50&jenis_laporan='.$jenis, [
            'Authorization' => 'Bearer '.$this->adminToken,
        ]);

        $response->assertOk();
        $reports = collect($response->json('data'));

        $this->assertCount($expectedTotal, $reports);
        $this->assertTrue(
            $reports->every(fn ($report) => $report['jenis_laporan'] === $jenis),
            "Expected all returned reports to have jenis_laporan '{$jenis}'"
        );
    }

    #[DataProvider('invalidJenisLaporanProvider')]
    public function test_list_reports_filter_by_invalid_jenis_laporan(mixed $jenis)
    {
        $response = $this->getJson('/api/list-reports?jenis_laporan='.$jenis, [
            'Authorization' => 'Bearer '.$this->adminToken,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('jenis_laporan');
    }

    #[DataProvider('validMonthProvider')]
    public function test_list_reports_filter_by_valid_month(int $bulan)
    {
        $this->seedSampleReports();

        $response = $this->getJson('/api/list-reports?per_page=50&periode_bulan='.$bulan, [
            'Authorization' => 'Bearer '.$this->adminToken,
        ]);

        $response->assertOk();
        $reports = collect($response->json('data'));

        $this->assertNotEmpty($reports, "Reports for month {$bulan} should not be empty.");
        $this->assertTrue(
            $reports->every(fn ($report) => (int) $report['periode_bulan'] === $bulan),
            "Expected all returned reports to match month {$bulan}"
        );
    }

    #[DataProvider('invalidMonthProvider')]
    public function test_list_reports_filter_by_invalid_month(mixed $bulan)
    {
        $response = $this->getJson('/api/list-reports?periode_bulan='.$bulan, [
            'Authorization' => 'Bearer '.$this->adminToken,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('periode_bulan');
    }

    #[DataProvider('validYearProvider')]
    public function test_list_reports_filter_by_valid_year(int $tahun, int $expectedCount)
    {
        $this->seedSampleReports();

        $response = $this->getJson('/api/list-reports?per_page=50&periode_tahun='.$tahun, [
            'Authorization' => 'Bearer '.$this->adminToken,
        ]);

        $response->assertOk();
        $reports = collect($response->json('data'));

        $this->assertCount($expectedCount, $reports);
        $this->assertTrue(
            $reports->every(fn ($report) => (int) $report['periode_tahun'] === $tahun),
            "Expected all returned reports to match year {$tahun}"
        );
    }

    #[DataProvider('invalidYearProvider')]
    public function test_list_reports_filter_by_invalid_year(mixed $tahun)
    {
        $response = $this->getJson('/api/list-reports?periode_tahun='.$tahun, [
            'Authorization' => 'Bearer '.$this->adminToken,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('periode_tahun');
    }

    public function test_list_reports_combined_filters()
    {
        $this->seedSampleReports();

        $response = $this->getJson('/api/list-reports?jenis_laporan=ampera&periode_tahun=2026&periode_bulan=3', [
            'Authorization' => 'Bearer '.$this->adminToken,
        ]);

        $response->assertOk();
        $reports = collect($response->json('data'));

        $this->assertCount(1, $reports);
        $report = $reports->first();
        $this->assertEquals('ampera', $report['jenis_laporan']);
        $this->assertEquals(2026, $report['periode_tahun']);
        $this->assertEquals(3, $report['periode_bulan']);
    }

    public function test_cannot_list_reports_if_user_is_not_admin()
    {
        $response = $this->getJson('/api/list-reports', [
            'Authorization' => 'Bearer '.$this->ownerToken,
        ]);

        $response->assertForbidden();
    }

    public function test_cannot_list_reports_if_user_is_not_logged_in()
    {
        $response = $this->getJson('/api/list-reports');

        $response->assertUnauthorized();
    }
}
