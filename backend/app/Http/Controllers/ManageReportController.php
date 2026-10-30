<?php

namespace App\Http\Controllers;

use App\Models\LaporanUpload;
use Illuminate\Http\Request;

class ManageReportController extends Controller
{
    public function listReports(Request $request)
    {
        $request->mergeIfMissing([
            'per_page' => 10,
            'page' => 1,
        ]);

        $validated = $request->validate([
            'jenis_laporan' => 'sometimes|in:ampera,pal,gabungan',
            'periode_bulan' => 'sometimes|integer|between:1,12',
            'periode_tahun' => 'sometimes|integer|between:0,65535',
            'per_page' => 'required|integer|in:10,20,50',
            'page' => 'required|integer|min:1',
        ], [
            'jenis_laporan.in' => 'Hanya menerima nilai ampera, pal, dan gabungan pada jenis laporan',
            'periode_bulan.integer' => 'Periode bulan harus merupakan angka dalam format integer',
            'periode_bulan.between' => 'Periode bulan harus berupa angka antara 1 sampai 12',
            'periode_tahun.integer' => 'Periode tahun harus merupakan angka dalam format integer',
            'periode_tahun.between' => 'Periode tahun harus berupa angka antara 0 sampai 65535',
            'per_page.required' => 'data per halaman harus diisi',
            'per_page.integer' => 'format data per halaman harus merupakan angka dalam integer',
            'per_page.in' => 'jumlah data per halaman hanya menerima nilai 10, 20, dan 50',
            'page.required' => 'halaman harus diisi',
            'page.integer' => 'halaman harus merupakan angka dalam integer',
            'page.min' => 'angka pada halaman harus minimal 1',
        ]);

        $query = LaporanUpload::query();

        if (! empty($validated['jenis_laporan'])) {
            $query->where('jenis_laporan', $validated['jenis_laporan']);
        }

        if (! empty($validated['periode_bulan'])) {
            $query->where('periode_bulan', $validated['periode_bulan']);
        }

        if (! empty($validated['periode_tahun'])) {
            $query->where('periode_tahun', $validated['periode_tahun']);
        }

        return $query->orderByDesc('periode_bulan')->orderByDesc('periode_tahun')->paginate($validated['per_page'], ['id', 'jenis_laporan', 'periode_bulan', 'periode_tahun']);
    }

    public function deleteReport(Request $request)
    {
        $validated = $request->validate([
            'reportid' => 'required|exists:laporan_uploads,id',
        ], [
            'reportid.required' => 'ID laporan harus diisi',
            'reportid.exists' => 'ID laporan harus merupakan laporan yang valid',
        ]);

        $laporan = LaporanUpload::findOrFail($validated['reportid']);

        $laporan->delete();

        return response()->json([
            'message' => 'Laporan berhasil dihapus',
        ]);
    }
}
