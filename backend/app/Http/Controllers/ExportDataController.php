<?php

namespace App\Http\Controllers;

use App\Exports\SalesDashboardExport;
use App\Models\LaporanUpload;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ExportDataController extends Controller
{
    public function index(Request $request)
    {
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
            return response()->json(['message' => 'Data tidak ditemukan'], 404); // -> state Kosong
        }

        return Excel::download(new SalesDashboardExport($upload), 'sales-dashboard '.$validated['periode_bulan'].'-'.$validated['periode_tahun'].'.xlsx');
    }
}
