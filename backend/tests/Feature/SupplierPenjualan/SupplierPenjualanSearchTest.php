<?php

namespace Tests\Feature\SupplierPenjualan;

use App\Models\LaporanUpload;
use App\Models\SupplierPenjualan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierPenjualanSearchTest extends TestCase
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

    // ── Helpers ────────────────────────────────────────────

    private function searchUrl(string $keyword): string
    {
        return $this->supplierUrl([
            'jenis_laporan' => 'gabungan',
            'search' => $keyword,
        ]);
    }

    // ── Search by field ────────────────────────────────────

    public function test_search_by_nama_supp_returns_matching_records(): void
    {
        $laporan = LaporanUpload::factory()->jenis_laporan('gabungan')->create();
        SupplierPenjualan::factory()->upload_id($laporan->id)->nama_supp('PT Maju Bersama')->create();
        SupplierPenjualan::factory()->upload_id($laporan->id)->nama_supp('CV Jaya Abadi')->create();

        $response = $this->getJson($this->searchUrl('Maju'), [
            'Authorization' => 'Bearer '.$this->ownerToken,
        ]);

        $response->assertOk();
        $response->assertJsonStructure(self::FETCH_SUPPLIER_PENJUALAN_JSON_STRUCTURE);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertStringContainsStringIgnoringCase('Maju', $data[0]['nama_supp']);
    }

    public function test_search_by_kode_supp_returns_matching_records(): void
    {
        $laporan = LaporanUpload::factory()->jenis_laporan('gabungan')->create();
        SupplierPenjualan::factory()->upload_id($laporan->id)->kode_supp('S0001')->create();
        SupplierPenjualan::factory()->upload_id($laporan->id)->kode_supp('S0999')->create();

        $response = $this->getJson($this->searchUrl('S0001'), [
            'Authorization' => 'Bearer '.$this->ownerToken,
        ]);

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('S0001', $data[0]['kode_supp']);
    }

    // ── Multi-keyword ──────────────────────────────────────

    public function test_search_with_multiple_keywords_requires_all_to_match(): void
    {
        $laporan = LaporanUpload::factory()->jenis_laporan('gabungan')->create();
        // Matches both keywords
        SupplierPenjualan::factory()->upload_id($laporan->id)->nama_supp('PT Maju Bersama Jaya')->create();
        // Matches only one keyword
        SupplierPenjualan::factory()->upload_id($laporan->id)->nama_supp('PT Maju Sentosa')->create();
        // Matches neither
        SupplierPenjualan::factory()->upload_id($laporan->id)->nama_supp('CV Abadi Makmur')->create();

        $response = $this->getJson($this->searchUrl('Maju Bersama'), [
            'Authorization' => 'Bearer '.$this->ownerToken,
        ]);

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertStringContainsStringIgnoringCase('Maju', $data[0]['nama_supp']);
        $this->assertStringContainsStringIgnoringCase('Bersama', $data[0]['nama_supp']);
    }

    // ── No results ─────────────────────────────────────────

    public function test_search_with_no_match_returns_empty_data(): void
    {
        $laporan = LaporanUpload::factory()->jenis_laporan('gabungan')->create();
        SupplierPenjualan::factory()->upload_id($laporan->id)->nama_supp('PT Maju Bersama')->create();

        $response = $this->getJson($this->searchUrl('XYZ_TIDAK_ADA_SUPPLIER_INI'), [
            'Authorization' => 'Bearer '.$this->ownerToken,
        ]);

        $response->assertOk();
        $this->assertCount(0, $response->json('data'));
        $this->assertEquals(0, $response->json('total'));
    }

    // ── LIKE special characters ────────────────────────────

    public function test_search_with_percent_sign_is_treated_as_literal(): void
    {
        $laporan = LaporanUpload::factory()->jenis_laporan('gabungan')->create();
        // Supplier whose name contains a literal '%'
        SupplierPenjualan::factory()->upload_id($laporan->id)->nama_supp('Diskon 50% Supplier')->create();
        SupplierPenjualan::factory()->upload_id($laporan->id)->nama_supp('Supplier Biasa')->create();

        $response = $this->getJson($this->searchUrl('50%'), [
            'Authorization' => 'Bearer '.$this->ownerToken,
        ]);

        $response->assertOk();
        // Should find exactly the supplier with '%' in its name, not everything
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertStringContainsString('%', $data[0]['nama_supp']);
    }

    public function test_search_with_underscore_is_treated_as_literal(): void
    {
        $laporan = LaporanUpload::factory()->jenis_laporan('gabungan')->create();
        SupplierPenjualan::factory()->upload_id($laporan->id)->nama_supp('Kode_Supplier_A')->create();
        SupplierPenjualan::factory()->upload_id($laporan->id)->nama_supp('Kode Supplier B')->create();

        $response = $this->getJson($this->searchUrl('Kode_Supplier'), [
            'Authorization' => 'Bearer '.$this->ownerToken,
        ]);

        $response->assertOk();
        // Should match only the one with literal underscores
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertStringContainsString('_', $data[0]['nama_supp']);
    }
}
