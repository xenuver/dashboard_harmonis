<?php
 
namespace App\Http\Controllers;
 
use App\Models\Cabang;
use App\Models\KpiSummary;
use App\Models\LaporanUpload;
use App\Models\ProdukPenjualan;
use App\Models\SalesHarian;
use App\Models\SupplierPenjualan;
use App\Services\LaporanExcelParser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
 
class UploadController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'jenis_laporan' => 'required|in:ampera,pal,gabungan',
            'periode_bulan' => 'required|integer|min:1|max:12',
            'periode_tahun' => 'required|integer',
            'file'          => 'required|file|mimes:xlsx',
        ]);
 
        $parser = new LaporanExcelParser($validated['file']);
        $isGabungan = $validated['jenis_laporan'] === 'gabungan';
 
        // DB::transaction -> kalau ada error di tengah proses, SEMUA
        // yang sudah keburu tersimpan ikut dibatalkan (rollback).
        // Jangan sampai upload gagal tapi menyisakan data setengah jadi.
        return DB::transaction(function () use ($request, $validated, $parser, $isGabungan) {
            $upload = LaporanUpload::create([
                'uploaded_by'   => $request->user()->id,
                'jenis_laporan' => $validated['jenis_laporan'],
                'periode_bulan' => $validated['periode_bulan'],
                'periode_tahun' => $validated['periode_tahun'],
                'nama_file'     => $validated['file']->getClientOriginalName(),
                'status'        => 'berhasil',
            ]);
 
            KpiSummary::create([
                'upload_id' => $upload->id,
                ...$parser->parseKpiSummary(),
            ]);
 
            $cabangAmpera = Cabang::where('kode', 'AMPERA')->firstOrFail();
            $cabangPal = Cabang::where('kode', 'PAL')->firstOrFail();
 
            foreach ($parser->parseSalesHarian($isGabungan) as $hari => $nilai) {
                $tanggal = sprintf('%04d-%02d-%02d', $validated['periode_tahun'], $validated['periode_bulan'], $hari);
 
                if ($isGabungan) {
                    // 2 baris per tanggal -- 1 per cabang (lihat dokumen PRD)
                    SalesHarian::create(['upload_id' => $upload->id, 'cabang_id' => $cabangAmpera->id, 'tanggal' => $tanggal, 'total_penjualan' => $nilai['ampera']]);
                    SalesHarian::create(['upload_id' => $upload->id, 'cabang_id' => $cabangPal->id, 'tanggal' => $tanggal, 'total_penjualan' => $nilai['pal']]);
                } else {
                    $cabangId = $validated['jenis_laporan'] === 'ampera' ? $cabangAmpera->id : $cabangPal->id;
                    SalesHarian::create(['upload_id' => $upload->id, 'cabang_id' => $cabangId, 'tanggal' => $tanggal, 'total_penjualan' => $nilai['total']]);
                }
            }
 
            foreach ($parser->parseProdukPenjualan(2, 'tertinggi') as $produk) {
                ProdukPenjualan::create(['upload_id' => $upload->id, ...$produk]);
            }
            foreach ($parser->parseProdukPenjualan(3, 'terendah') as $produk) {
                ProdukPenjualan::create(['upload_id' => $upload->id, ...$produk]);
            }
            foreach ($parser->parseSupplierPenjualan() as $supplier) {
                SupplierPenjualan::create(['upload_id' => $upload->id, ...$supplier]);
            }
 
            return response()->json([
                'message'   => 'Upload berhasil',
                'upload_id' => $upload->id,
            ]);
        });
    }
}