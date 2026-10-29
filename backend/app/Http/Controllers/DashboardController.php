<?php

namespace App\Http\Controllers;

use App\Models\DataTransaksi;
use App\Models\LaporanUpload;
use App\Models\MemberCount;
use App\Models\SalesHarian;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->mergeIfMissing([
            'jenis_laporan' => 'gabungan',
            'periode_bulan' => date('n'),
            'periode_tahun' => date('Y'),
        ]);

        $validated = $request->validate([
            'jenis_laporan' => 'required|in:ampera,pal,gabungan',
            'periode_bulan' => 'required|integer|between:1,12',
            'periode_tahun' => 'required|integer|between:0,65535',
        ], [
            'jenis_laporan.required' => 'Jenis laporan harus diisi',
            'jenis_laporan.in' => 'Hanya menerima nilai ampera, pal, dan gabungan pada jenis laporan',
            'periode_bulan.required' => 'Periode bulan harus diisi',
            'periode_bulan.integer' => 'Periode bulan harus merupakan angka dalam format integer',
            'periode_bulan.between' => 'Periode bulan harus berupa angka antara 1 sampai 12',
            'periode_tahun.required' => 'Periode tahun harus diisi',
            'periode_tahun.integer' => 'Periode tahun harus merupakan angka dalam format integer',
            'periode_tahun.between' => 'Periode tahun harus berupa angka antara 0 sampai 65535',
        ]);

        $upload = LaporanUpload::jenisLaporan($validated['jenis_laporan'])
            ->periode($validated['periode_bulan'], $validated['periode_tahun'])
            ->first();

        if (! $upload) {
            return response()->json(['message' => 'Belum ada data'], 404); // -> state Kosong
        }

        $prevDate = Carbon::createFromDate($validated['periode_tahun'], $validated['periode_bulan'], 1)->subMonth();

        $uploadPrev = LaporanUpload::jenisLaporan($validated['jenis_laporan'])
            ->periode($prevDate->month, $prevDate->year)
            ->first();

        // Data buat grafik tren: dikelompokkan per tanggal, tiap tanggal
        // berisi 1 baris (Ampera/Pal saja) atau 2 baris (mode Gabungan)
        $trend = SalesHarian::where('upload_id', $upload->id)
            ->with('cabang')
            ->orderBy('tanggal')
            ->get()
            ->groupBy(fn ($row) => $row->tanggal->format('Y-m-d'))
            ->map(fn ($rows) => $rows->map(fn ($r) => [
                'cabang' => $r->cabang->nama,
                'total' => $r->total_penjualan,
            ]));

        $dataTransaksi = DataTransaksi::where('upload_id', $upload->id)->first();

        $totalPenjualan = SalesHarian::where('upload_id', $upload->id)->sum('total_penjualan');

        $kpi = [
            'total_sales' => filter_var($totalPenjualan ?? null, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE),
            'jumlah_transaksi' => $dataTransaksi->getTotalTransaction(),
            'growth_member' => MemberCount::where('upload_id', $upload->id)->select('member_baru')->first()?->member_baru,
        ];

        if ($uploadPrev) {
            $totalPenjualanPrev = SalesHarian::where('upload_id', $uploadPrev->id)->sum('total_penjualan');

            if ($totalPenjualanPrev) {
                $kpi['total_growth'] = strval((($totalPenjualan - $totalPenjualanPrev) / $totalPenjualanPrev) * 100);
            }
        }

        return response()->json([
            'kpi' => $kpi,
            'trend' => $trend,
            'transaksi' => [
                'transaksi_member' => $dataTransaksi->getTotalMemberTransaction(),
                'transaksi_non_member' => $dataTransaksi->getTotalNonMemberTransaction(),
            ],
            'top_produk_terlaris' => $upload->produkPenjualan()
                ->tertinggi()->limit(10)->get(),
            'top_produk_terendah' => $upload->produkPenjualan()
                ->terendah()->limit(10)->get(),
            'top_supplier' => $upload->supplierPenjualan()->OrderByDesc('gross_total')->limit(10)->get(),
        ]);
    }
}
