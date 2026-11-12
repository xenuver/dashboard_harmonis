<?php

namespace Database\Factories;

use App\Models\Cabang;
use App\Models\DataTransaksi;
use App\Models\LaporanUpload;
use App\Models\MemberCount;
use App\Models\ProdukPenjualan;
use App\Models\SalesHarian;
use App\Models\SupplierPenjualan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LaporanUploadFactory extends Factory
{
    protected $model = LaporanUpload::class;

    private int $topProductsCount = 300;
    private int $worstProductsCount = 300;
    private int $supplierCount = 400;

    public function definition(): array
    {
        return [
            'jenis_laporan' => 'gabungan',
            'periode_bulan' => idate('m'),
            'periode_tahun' => idate('Y'),
            'jumlah_hari' => function (array $attributes) {
                return cal_days_in_month(CAL_GREGORIAN, $attributes['periode_bulan'], $attributes['periode_tahun']);
            },
            'format_data' => 'DATA MENTAH v2',
            'catatan' => null,
            'status' => 'berhasil',
        ];
    }

    public function jenis_laporan(string $value) {
        return $this->state([
            'jenis_laporan' => $value,
        ]);
    }

    public function periode(string $bulan, string $tahun) {
        return $this->state([
            'periode_bulan' => $bulan,
            'periode_tahun' => $tahun,
            'jumlah_hari' => cal_days_in_month(CAL_GREGORIAN, $bulan, $tahun),
        ]);
    }

    public function format_data(?string $value) {
        return $this->state([
            'format_data' => $value,
        ]);
    }

    public function status(string $value) {
        return $this->state([
            'status' => $value,
        ]);
    }

    public function catatan(?string $value) {
        return $this->state([
            'catatan' => $value,
        ]);
    }

    public function top_products_count(int $value) {
        $this->topProductsCount = $value;
        return $this;
    }

    public function worst_products_count(int $value) {
        $this->worstProductsCount = $value;
        return $this;
    }

    public function supplier_count(int $value) {
        $this->supplierCount = $value;
        return $this;
    }

    public function generateFullReport() {
        return $this->afterCreating(function (LaporanUpload $report) {

            $jumlahHari = $report->jumlah_hari;

            $cabangAll = Cabang::all(['id', 'kode']);

            DB::transaction(function() use ($jumlahHari, $cabangAll, $report) {
                for($i = 1; $i <= $jumlahHari; $i++) {
                    $salesHarianDate = Carbon::create($report->periode_tahun, $report->periode_bulan, $i);
                    if($report->jenis_laporan === 'gabungan') {
                        foreach($cabangAll as $cabang) {
                            SalesHarian::factory()->upload_id($report->id)->cabang_id($cabang->id)->tanggal($salesHarianDate)->create();
                        }
                    } else {
                        $cabang = $cabangAll->where('kode', strtoupper($report->jenis_laporan));
                        SalesHarian::factory()->upload_id($report->id)->cabang_id($cabang->id)->tanggal($salesHarianDate)->create();
                    }
                }

                MemberCount::factory()->upload_id($report->id)->create();
                DataTransaksi::factory()->upload_id($report->id)->create();

                ProdukPenjualan::factory()->upload_id($report->id)->kategori('tertinggi')->count($this->topProductsCount)->create();
                ProdukPenjualan::factory()->upload_id($report->id)->kategori('terendah')->count($this->worstProductsCount)->create();

                SupplierPenjualan::factory()->upload_id($report->id)->count($this->supplierCount)->create();
            });
        });
    }

    public function configure(): static
    {
        return $this->afterMaking(function (LaporanUpload $report) {
            if(!in_array($report->jenis_laporan, ['ampera', 'pal', 'gabungan'])) {
                throw new InvalidArgumentException(
                    'Jenis laporan hanya menerima ampera, pal, dan gabungan'
                );
            }

            if($report->periode_bulan < 1 || $report->periode_bulan > 12) {
                throw new InvalidArgumentException(
                    'Bulan hanya menerima nilai 1-12'
                );
            }

            if($report->periode_tahun < 0 || $report->periode_tahun > 65535) {
                throw new InvalidArgumentException(
                    'Tahun hanya menerima nilai 0-65535'
                );
            }

            if($this->topProductsCount < 0) {
                throw new InvalidArgumentException(
                    'Nilai jumlah produk teratas di bawah 0'
                );
            }

            if($this->worstProductsCount < 0) {
                throw new InvalidArgumentException(
                    'Nilai jumlah produk terbawah di bawah 0'
                );
            }

            if($this->supplierCount < 0) {
                throw new InvalidArgumentException(
                    'Nilai jumlah supplier di bawah 0'
                );
            }

            if(!in_array($report->status, ['berhasil', 'gagal'])) {
                throw new InvalidArgumentException(
                    'Status upload hanya menerima berhasil dan gagal'
                );
            }
        });
    }
}
