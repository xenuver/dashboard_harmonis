<?php

namespace Tests\Feature\ExcelExport;

use App\Models\LaporanUpload;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ExportDataTest extends TestCase
{
    use RefreshDatabase;

    private const EXPORT_URL = '/api/export-xlsx';

    private const TEST_JENIS = 'gabungan';

    private const TEST_BULAN = 11;

    private const TEST_TAHUN = 2026;

    private string $adminToken;

    private string $ownerToken;

    protected function setUp(): void
    {
        parent::setUp();

        // DatabaseSeeder creates Cabang rows needed by LaporanUploadFactory
        $this->seed();

        $this->adminToken = User::factory()->create([
            'role' => 'it_staff',
        ])->createToken('auth_token')->plainTextToken;

        $this->ownerToken = User::factory()->create([
            'role' => 'owner',
        ])->createToken('auth_token')->plainTextToken;
    }

    // -------------------------------------------------------------------------
    // Helper
    // -------------------------------------------------------------------------

    private function exportUrl(array $params = []): string
    {
        $defaults = [
            'jenis_laporan' => self::TEST_JENIS,
            'periode_bulan' => self::TEST_BULAN,
            'periode_tahun' => self::TEST_TAHUN,
        ];

        $query = http_build_query(array_merge($defaults, $params));

        return self::EXPORT_URL.'?'.$query;
    }

    private function createLaporan(
        string $jenis = self::TEST_JENIS,
        int $bulan = self::TEST_BULAN,
        int $tahun = self::TEST_TAHUN,
    ): LaporanUpload {
        return LaporanUpload::factory()
            ->jenis_laporan($jenis)
            ->periode($bulan, $tahun)
            ->create();
    }

    // -------------------------------------------------------------------------
    // Authentication & authorization
    // -------------------------------------------------------------------------

    public function test_cannot_export_while_logged_out(): void
    {
        $this->createLaporan();

        $this->getJson($this->exportUrl())
            ->assertUnauthorized();
    }

    public function test_cannot_export_if_user_is_disabled(): void
    {
        $this->createLaporan();

        $disabledToken = User::factory()->create([
            'role' => 'it_staff',
            'enabled' => false,
        ])->createToken('auth_token')->plainTextToken;

        $this->withToken($disabledToken)
            ->getJson($this->exportUrl())
            ->assertUnauthorized();
    }

    // -------------------------------------------------------------------------
    // Successful export
    // -------------------------------------------------------------------------

    public function test_can_export_xlsx_as_admin(): void
    {
        Excel::fake();

        $this->createLaporan();

        $this->withToken($this->adminToken)
            ->get($this->exportUrl())
            ->assertOk();

        // When Excel is faked the real file stream is suppressed, so we verify
        // the correct export class and filename were dispatched instead.
        $expectedFilename = 'sales-report-'.self::TEST_JENIS.'-'.self::TEST_BULAN.'-'.self::TEST_TAHUN.'.xlsx';
        Excel::assertDownloaded($expectedFilename);
    }

    public function test_can_export_xlsx_as_owner(): void
    {
        Excel::fake();

        $this->createLaporan();

        $this->withToken($this->ownerToken)
            ->get($this->exportUrl())
            ->assertOk();

        $expectedFilename = 'sales-report-'.self::TEST_JENIS.'-'.self::TEST_BULAN.'-'.self::TEST_TAHUN.'.xlsx';
        Excel::assertDownloaded($expectedFilename);
    }

    public function test_export_response_contains_correct_filename(): void
    {
        Excel::fake();

        $this->createLaporan();

        $this->withToken($this->adminToken)
            ->get($this->exportUrl())
            ->assertOk();

        $expectedFilename = 'sales-report-'.self::TEST_JENIS.'-'.self::TEST_BULAN.'-'.self::TEST_TAHUN.'.xlsx';
        Excel::assertDownloaded($expectedFilename);
    }

    public function test_export_sets_access_control_expose_headers(): void
    {
        Excel::fake();

        $this->createLaporan();

        // The Access-Control-Expose-Headers header is set after Excel::download()
        // returns, so it is preserved even when Excel is faked.
        $this->withToken($this->adminToken)
            ->get($this->exportUrl())
            ->assertOk()
            ->assertHeader('Access-Control-Expose-Headers', 'Content-Disposition');
    }

    public function test_can_export_ampera_report(): void
    {
        Excel::fake();

        LaporanUpload::factory()
            ->jenis_laporan('ampera')
            ->periode(self::TEST_BULAN, self::TEST_TAHUN)
            ->create();

        $this->withToken($this->adminToken)
            ->get($this->exportUrl(['jenis_laporan' => 'ampera']))
            ->assertOk();
    }

    public function test_can_export_pal_report(): void
    {
        Excel::fake();

        LaporanUpload::factory()
            ->jenis_laporan('pal')
            ->periode(self::TEST_BULAN, self::TEST_TAHUN)
            ->create();

        $this->withToken($this->adminToken)
            ->get($this->exportUrl(['jenis_laporan' => 'pal']))
            ->assertOk();
    }

    // -------------------------------------------------------------------------
    // Not-found behaviour
    // -------------------------------------------------------------------------

    public function test_returns_404_if_no_matching_report(): void
    {
        // No LaporanUpload created — should return 404
        $this->withToken($this->adminToken)
            ->getJson($this->exportUrl())
            ->assertNotFound()
            ->assertJson(['message' => 'Data tidak ditemukan']);
    }

    public function test_returns_404_when_jenis_does_not_match_existing_report(): void
    {
        // Create a 'pal' report but request 'ampera' — different jenis, same period
        LaporanUpload::factory()
            ->jenis_laporan('pal')
            ->periode(self::TEST_BULAN, self::TEST_TAHUN)
            ->create();

        $this->withToken($this->adminToken)
            ->getJson($this->exportUrl(['jenis_laporan' => 'ampera']))
            ->assertNotFound()
            ->assertJson(['message' => 'Data tidak ditemukan']);
    }

    public function test_returns_404_when_period_does_not_match_existing_report(): void
    {
        // Create report for a different month
        $this->createLaporan(self::TEST_JENIS, 1, self::TEST_TAHUN);

        $this->withToken($this->adminToken)
            ->getJson($this->exportUrl(['periode_bulan' => 2]))
            ->assertNotFound()
            ->assertJson(['message' => 'Data tidak ditemukan']);
    }

    // -------------------------------------------------------------------------
    // Validation — invalid inputs (422)
    // -------------------------------------------------------------------------

    #[DataProvider('invalidInputProvider')]
    public function test_returns_422_on_invalid_input(array $overrides): void
    {
        $this->withToken($this->adminToken)
            ->getJson($this->exportUrl($overrides))
            ->assertUnprocessable();
    }

    public static function invalidInputProvider(): array
    {
        return [
            'jenis_laporan missing' => [['jenis_laporan' => null]],
            'jenis_laporan invalid value' => [['jenis_laporan' => 'unknown']],
            'periode_bulan missing' => [['periode_bulan' => null]],
            'periode_bulan too low (0)' => [['periode_bulan' => 0]],
            'periode_bulan too high (13)' => [['periode_bulan' => 13]],
            'periode_tahun missing' => [['periode_tahun' => null]],
            'periode_tahun too low (-1)' => [['periode_tahun' => -1]],
            'periode_tahun too high (65536)' => [['periode_tahun' => 65536]],
        ];
    }

    public function test_validation_message_for_missing_jenis_laporan(): void
    {
        $this->withToken($this->adminToken)
            ->getJson($this->exportUrl(['jenis_laporan' => null]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.jenis_laporan.0', 'Jenis laporan harus diisi');
    }

    public function test_validation_message_for_invalid_jenis_laporan(): void
    {
        $this->withToken($this->adminToken)
            ->getJson($this->exportUrl(['jenis_laporan' => 'bukan_jenis']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.jenis_laporan.0', 'Hanya menerima nilai ampera, pal, dan gabungan pada jenis laporan');
    }

    public function test_validation_message_for_missing_periode_bulan(): void
    {
        $this->withToken($this->adminToken)
            ->getJson($this->exportUrl(['periode_bulan' => null]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.periode_bulan.0', 'Periode bulan harus diisi');
    }

    public function test_validation_message_for_out_of_range_periode_bulan(): void
    {
        $this->withToken($this->adminToken)
            ->getJson($this->exportUrl(['periode_bulan' => 0]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.periode_bulan.0', 'Periode bulan harus berupa angka antara 1 sampai 12');
    }

    public function test_validation_message_for_missing_periode_tahun(): void
    {
        $this->withToken($this->adminToken)
            ->getJson($this->exportUrl(['periode_tahun' => null]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.periode_tahun.0', 'Periode tahun harus diisi');
    }

    public function test_validation_message_for_out_of_range_periode_tahun(): void
    {
        $this->withToken($this->adminToken)
            ->getJson($this->exportUrl(['periode_tahun' => 65536]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.periode_tahun.0', 'Periode tahun harus berupa angka antara 0 sampai 65535');
    }
}
