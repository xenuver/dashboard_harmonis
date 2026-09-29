<?php
 
namespace App\Http\Controllers;
 
use App\Models\LaporanUpload;
use Illuminate\Http\Request;
 
class SupplierPenjualanController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->mergeIfMissing([
            'jenis_laporan' => 'gabungan',
            'periode_bulan' => date('n'),
            'periode_tahun' => date('Y'),
            'kategori'      => 'tertinggi',
            'per_page'      => 10,
        ]);

        $validated = $request->validate([
            'jenis_laporan' => 'required|in:ampera,pal,gabungan',
            'periode_bulan' => 'required|integer',
            'periode_tahun' => 'required|integer',
            'search'        => 'nullable|string',
            'per_page'      => 'nullable|integer',
        ]);
 
        $upload = LaporanUpload::jenisLaporan($validated['jenis_laporan'])
            ->periode($validated['periode_bulan'], $validated['periode_tahun'])
            ->first(); // -> 404 kalau belum ada data (state Kosong)

        if (! $upload) {
            return response()->json(['message' => 'Belum ada data'], 404); // -> state Kosong
        }

        $query = $upload->supplierPenjualan();
 
        if (! empty($validated['search'])) {
            $query->cari($validated['search']); // scope dari model ProdukPenjualan
        }
 
        return $query->orderByDesc('net_sales_total')
            ->paginate($validated['per_page']);
    }
}