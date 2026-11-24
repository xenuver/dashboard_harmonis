<?php

namespace Tests\Feature\ProdukPenjualan;

use App\Models\LaporanUpload;
use App\Models\ProdukPenjualan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProdukPenjualanSearchTest extends TestCase
{
    use RefreshDatabase;

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

    // ── Helpers ────────────────────────────────────────────

    private function searchUrl(string $keyword, string $kategori = 'tertinggi'): string
    {
        return '/api/produk-penjualan?'.http_build_query([
            'jenis_laporan' => 'gabungan',
            'kategori'      => $kategori,
            'search'        => $keyword,
        ]);
    }

    // ── Search by field ────────────────────────────────────

    public function test_search_by_nama_brg_returns_matching_records(): void
    {
        $laporan = LaporanUpload::factory()->jenis_laporan('gabungan')->create();
        ProdukPenjualan::factory()->upload_id($laporan->id)->kategori('tertinggi')->nama_brg('Minyak Goreng Curah')->create();
        ProdukPenjualan::factory()->upload_id($laporan->id)->kategori('tertinggi')->nama_brg('Gula Pasir')->create();

        $response = $this->getJson($this->searchUrl('Minyak'), [
            'Authorization' => 'Bearer '.$this->ownerToken,
        ]);

        $response->assertOk();
        $response->assertJsonStructure(self::FETCH_PRODUK_PENJUALAN_JSON_STRUCTURE);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertStringContainsStringIgnoringCase('Minyak', $data[0]['nama_brg']);
    }

    public function test_search_by_kode_brg_returns_matching_records(): void
    {
        $laporan = LaporanUpload::factory()->jenis_laporan('gabungan')->create();
        ProdukPenjualan::factory()->upload_id($laporan->id)->kategori('tertinggi')->kode_brg('KD-001')->create();
        ProdukPenjualan::factory()->upload_id($laporan->id)->kategori('tertinggi')->kode_brg('KD-999')->create();

        $response = $this->getJson($this->searchUrl('KD-001'), [
            'Authorization' => 'Bearer '.$this->ownerToken,
        ]);

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('KD-001', $data[0]['kode_brg']);
    }

    public function test_search_by_barcode_returns_matching_records(): void
    {
        $laporan = LaporanUpload::factory()->jenis_laporan('gabungan')->create();
        ProdukPenjualan::factory()->upload_id($laporan->id)->kategori('tertinggi')->barcode('1234567890')->create();
        ProdukPenjualan::factory()->upload_id($laporan->id)->kategori('tertinggi')->barcode('9999999999')->create();

        $response = $this->getJson($this->searchUrl('1234567890'), [
            'Authorization' => 'Bearer '.$this->ownerToken,
        ]);

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('1234567890', $data[0]['barcode']);
    }

    // ── Multi-keyword ──────────────────────────────────────

    public function test_search_with_multiple_keywords_requires_all_to_match(): void
    {
        $laporan = LaporanUpload::factory()->jenis_laporan('gabungan')->create();
        // Matches both keywords
        ProdukPenjualan::factory()->upload_id($laporan->id)->kategori('tertinggi')->nama_brg('Minyak Goreng Curah')->create();
        // Matches only one keyword
        ProdukPenjualan::factory()->upload_id($laporan->id)->kategori('tertinggi')->nama_brg('Minyak Kelapa')->create();
        // Matches neither
        ProdukPenjualan::factory()->upload_id($laporan->id)->kategori('tertinggi')->nama_brg('Gula Pasir')->create();

        $response = $this->getJson($this->searchUrl('Minyak Goreng'), [
            'Authorization' => 'Bearer '.$this->ownerToken,
        ]);

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertStringContainsStringIgnoringCase('Minyak', $data[0]['nama_brg']);
        $this->assertStringContainsStringIgnoringCase('Goreng', $data[0]['nama_brg']);
    }

    // ── No results ─────────────────────────────────────────

    public function test_search_with_no_match_returns_empty_data(): void
    {
        $laporan = LaporanUpload::factory()->jenis_laporan('gabungan')->create();
        ProdukPenjualan::factory()->upload_id($laporan->id)->kategori('tertinggi')->nama_brg('Gula Pasir')->create();

        $response = $this->getJson($this->searchUrl('XYZ_TIDAK_ADA_PRODUK_INI'), [
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
        // Product whose name contains a literal '%'
        ProdukPenjualan::factory()->upload_id($laporan->id)->kategori('tertinggi')->nama_brg('Diskon 50% Off')->create();
        ProdukPenjualan::factory()->upload_id($laporan->id)->kategori('tertinggi')->nama_brg('Produk Biasa')->create();

        $response = $this->getJson($this->searchUrl('50%'), [
            'Authorization' => 'Bearer '.$this->ownerToken,
        ]);

        $response->assertOk();
        // Should find exactly the product with '%' in its name, not everything
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertStringContainsString('%', $data[0]['nama_brg']);
    }

    public function test_search_with_underscore_is_treated_as_literal(): void
    {
        $laporan = LaporanUpload::factory()->jenis_laporan('gabungan')->create();
        ProdukPenjualan::factory()->upload_id($laporan->id)->kategori('tertinggi')->nama_brg('Kode_Barang_A')->create();
        ProdukPenjualan::factory()->upload_id($laporan->id)->kategori('tertinggi')->nama_brg('Kode Barang B')->create();

        $response = $this->getJson($this->searchUrl('Kode_Barang'), [
            'Authorization' => 'Bearer '.$this->ownerToken,
        ]);

        $response->assertOk();
        // Should match only the one with literal underscores
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertStringContainsString('_', $data[0]['nama_brg']);
    }

    // ── Search respects kategori filter ───────────────────

    public function test_search_does_not_cross_kategori_boundary(): void
    {
        $laporan = LaporanUpload::factory()->jenis_laporan('gabungan')->create();
        // Same nama_brg, different kategori
        ProdukPenjualan::factory()->upload_id($laporan->id)->kategori('tertinggi')->nama_brg('Produk Sama')->create();
        ProdukPenjualan::factory()->upload_id($laporan->id)->kategori('terendah')->nama_brg('Produk Sama')->create();

        // Search with kategori=tertinggi — should NOT return the terendah record
        $response = $this->getJson($this->searchUrl('Produk Sama', 'tertinggi'), [
            'Authorization' => 'Bearer '.$this->ownerToken,
        ]);

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('tertinggi', $data[0]['kategori']);
    }
}
