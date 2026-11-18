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

    public function jenis_laporan(string $value)
    {
        if (! in_array($value, ['ampera', 'pal', 'gabungan'])) {
            throw new InvalidArgumentException('Jenis laporan hanya menerima ampera, pal, dan gabungan');
        }

        return $this->state([
            'jenis_laporan' => $value,
        ]);
    }

    public function periode(string $bulan, string $tahun)
    {
        if ($bulan < 1 || $bulan > 12) {
            throw new InvalidArgumentException(
                'Bulan hanya menerima nilai 1-12'
            );
        }

        if ($tahun < 0 || $tahun > 65535) {
            throw new InvalidArgumentException('Tahun hanya menerima nilai 0-65535');
        }

        return $this->state([
            'periode_bulan' => $bulan,
            'periode_tahun' => $tahun,
            'jumlah_hari' => cal_days_in_month(CAL_GREGORIAN, $bulan, $tahun),
        ]);
    }

    public function format_data(?string $value)
    {
        return $this->state([
            'format_data' => $value,
        ]);
    }

    public function status(string $value)
    {
        if (! in_array($value, ['berhasil', 'gagal'])) {
            throw new InvalidArgumentException('Status upload hanya menerima berhasil dan gagal');
        }

        return $this->state([
            'status' => $value,
        ]);
    }

    public function catatan(?string $value)
    {
        return $this->state([
            'catatan' => $value,
        ]);
    }

    public function withSalesHarian() {
        return $this->afterCreating(function (LaporanUpload $report) {
            $jumlahHari = $report->jumlah_hari;
            $cabangAll = Cabang::all(['id', 'kode']);

            DB::transaction(function () use ($jumlahHari, $cabangAll, $report) {
                if($cabangAll->isEmpty()) {
                    throw new \RuntimeException('Tabel cabang kosong. Pastikan database terdapat setidaknya 1 cabang.');
                }
                for ($i = 1; $i <= $jumlahHari; $i++) {
                    $salesHarianDate = Carbon::create($report->periode_tahun, $report->periode_bulan, $i);
                    if ($report->jenis_laporan === 'gabungan') {
                        foreach ($cabangAll as $cabang) {
                            SalesHarian::factory()->upload_id($report->id)->cabang_id($cabang->id)->tanggal($salesHarianDate)->create();
                        }
                    } else {
                        $cabang = $cabangAll->where('kode', strtoupper($report->jenis_laporan))->firstOrFail();
                        SalesHarian::factory()->upload_id($report->id)->cabang_id($cabang->id)->tanggal($salesHarianDate)->create();
                    }
                }
            });
        });
    }

    public function withDataTransaksi() {
        return $this->afterCreating(function (LaporanUpload $report) {
            DataTransaksi::factory()->upload_id($report->id)->create();
        });
    }

    public function withMemberCount() {
        return $this->afterCreating(function (LaporanUpload $report) {
            MemberCount::factory()->upload_id($report->id)->create();
        });
    }

    public function withProdukPenjualan(int $topProductsCount = 300, int $worstProductsCount = 300) {
        if ($topProductsCount < 0) {
            throw new InvalidArgumentException('Nilai jumlah produk teratas di bawah 0');
        }

        if ($worstProductsCount < 0) {
            throw new InvalidArgumentException('Nilai jumlah produk terbawah di bawah 0');
        }

        return $this->afterCreating(function (LaporanUpload $report) use ($topProductsCount, $worstProductsCount) {
            ProdukPenjualan::factory()->upload_id($report->id)->kategori('tertinggi')->count($topProductsCount)->create();
            ProdukPenjualan::factory()->upload_id($report->id)->kategori('terendah')->count($worstProductsCount)->create();
        });
    }

    public function withSupplierPenjualan(int $supplierCount = 400) {
        if ($supplierCount < 0) {
            throw new InvalidArgumentException('Nilai jumlah supplier di bawah 0');
        }

        return $this->afterCreating(function (LaporanUpload $report) use ($supplierCount) {
            SupplierPenjualan::factory()->upload_id($report->id)->count($supplierCount)->create();
        });
    }
}
