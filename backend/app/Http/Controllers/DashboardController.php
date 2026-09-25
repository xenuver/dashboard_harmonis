<?php
 
namespace App\Http\Controllers;
 
use App\Models\LaporanUpload;
use App\Models\SalesHarian;
use Illuminate\Http\Request;
 
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
            'periode_bulan' => 'required|integer',
            'periode_tahun' => 'required|integer',
        ]);
 
        $upload = LaporanUpload::jenisLaporan($validated['jenis_laporan'])
            ->periode($validated['periode_bulan'], $validated['periode_tahun'])
            ->with('kpiSummary')
            ->first();
 
        if (! $upload) {
            return response()->json(['message' => 'Belum ada data'], 404); // -> state Kosong
        }
 
        // Data buat grafik tren: dikelompokkan per tanggal, tiap tanggal
        // berisi 1 baris (Ampera/Pal saja) atau 2 baris (mode Gabungan)
        $trend = SalesHarian::where('upload_id', $upload->id)
            ->with('cabang')
            ->orderBy('tanggal')
            ->get()
            ->groupBy(fn ($row) => $row->tanggal->format('Y-m-d'))
            ->map(fn ($rows) => $rows->map(fn ($r) => [
                'cabang' => $r->cabang->nama,
                'total'  => $r->total_penjualan,
            ]));
 
        return response()->json([
            'kpi' => $upload->kpiSummary,
            'trend' => $trend,
            'top_produk_terlaris' => $upload->produkPenjualan()
                ->tertinggi()->orderByDesc('jumlah')->limit(10)->get(),
            'top_produk_terendah' => $upload->produkPenjualan()
                ->terendah()->orderBy('jumlah')->limit(10)->get(),
            'top_supplier' => $upload->supplierPenjualan()
                ->orderByDesc('net_sales_total')->limit(10)->get(),
        ]);
    }
}