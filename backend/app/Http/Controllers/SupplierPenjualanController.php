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
            'page'          => 1,
        ]);

        $validated = $request->validate([
            'jenis_laporan' => 'required|in:ampera,pal,gabungan',
            'periode_bulan' => 'required|integer|min:1|max:12',
            'periode_tahun' => 'required|integer',
            'search'        => 'nullable|string',
            'per_page'      => 'required|in:10,20,50|integer',
            'page'          => 'integer|min:1',
        ], [
            'jenis_laporan.required' => 'Jenis laporan harus diisi',
            'jenis_laporan.in' => 'Hanya menerima nilai ampera, pal, dan gabungan pada jenis laporan',
            'periode_bulan.required' => 'Periode bulan harus diisi',
            'periode_bulan.integer' => 'Periode bulan harus merupakan angka dalam format integer',
            'periode_bulan.min' => 'Periode bulan harus berupa angka minimal 1',
            'periode_bulan.max' => 'Periode bulan harus berupa angka maksimal 12',
            'periode_tahun.required' => 'Periode bulan harus diisi',
            'periode_tahun.integer' => 'Periode bulan harus merupakan angka dalam format integer',
            'per_page.integer' => 'format data per halaman harus merupakan angka dalam integer',
            'per_page.in' => 'jumlah data per halaman hanya menerima nilai 10, 20, dan 50',
            'page.required' => 'halaman harus diisi',
            'page.integer' => 'halaman harus merupakan angka dalam integer',
            'page.min' => 'angka pada halaman harus minimal 1',
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