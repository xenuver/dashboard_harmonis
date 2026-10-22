<?php

namespace App\Http\Controllers;

use App\Models\Cabang;
use App\Models\DataTransaksi;
use App\Models\LaporanUpload;
use App\Models\MemberCount;
use App\Models\ProdukPenjualan;
use App\Models\SalesHarian;
use App\Models\SupplierPenjualan;
use App\Services\InfoLaporanExcelParser;
use App\Services\LaporanExcelParser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UploadController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'jenis_laporan' => 'required|in:ampera,pal,gabungan',
            'periode_bulan' => 'required|integer|between:1,12',
            'periode_tahun' => 'required|integer|between:0,65535',
            'file' => 'required|file|mimes:xlsx',
        ], [
            'jenis_laporan.required' => 'Jenis laporan harus diisi',
            'jenis_laporan.in' => 'Hanya menerima nilai ampera, pal, dan gabungan pada jenis laporan',
            'periode_bulan.required' => 'Periode bulan harus diisi',
            'periode_bulan.integer' => 'Periode bulan harus merupakan angka dalam format integer',
            'periode_bulan.between' => 'Periode bulan harus berupa angka antara 1 sampai 12',
            'periode_tahun.required' => 'Periode tahun harus diisi',
            'periode_tahun.integer' => 'Periode tahun harus merupakan angka dalam format integer',
            'periode_tahun.between' => 'Periode tahun harus berupa angka antara 0 sampai 65535',
            'file.required' => 'File harus dilampirkan',
            'file.mimes' => 'File lampiran harus dalam format .xlsx',
        ]);

        $infoParser = new InfoLaporanExcelParser($validated['file']);

        $infoParserData = $infoParser->parseInfo();
        $infoParserErrors = $infoParser->getImportErrors();

        if(strtolower($infoParserData['jenis_laporan']) != strtolower($validated['jenis_laporan'])) {
            $infoParserErrors['INFO'][] = [
                "location" => null,
                "message" => "Jenis laporan yang diinput dengan file berbeda! File: ".$infoParserData['jenis_laporan'].". Input: ".$validated['jenis_laporan'],
            ];
        }

        if($infoParserData['bulan'] != $validated['periode_bulan']) {
            $infoParserErrors['INFO'][] = [
                "location" => null,
                "message" => "Bulan yang diinput dengan file berbeda! File: ".$infoParserData['bulan'].". Input: ".$validated['periode_bulan'],
            ];
        }

        if($infoParserData['tahun'] != $validated['periode_tahun']) {
            $infoParserErrors['INFO'][] = [
                "location" => null,
                "message" => "Tahun yang diinput dengan file berbeda! File: ".$infoParserData['tahun'].". Input: ".$validated['periode_tahun'],
            ];
        }

        if (! empty($infoParserErrors)) {
            return response()->json([
                'message' => 'Validasi spreadsheet gagal.',
                'xlsxValidationErrors' => $infoParserErrors,
            ], 422);
        }

        $dataExists = LaporanUpload::where('jenis_laporan', $infoParserData['jenis_laporan'])
            ->where('periode_bulan', $infoParserData['bulan'])
            ->where('periode_tahun', $infoParserData['tahun'])
            ->exists();

        if ($dataExists) {
            return response()->json([
                'message' => 'Laporan untuk periode dan jenis ini sudah pernah diunggah.',
            ], 409);
        }

        $isGabungan = $infoParser->getIsGabungan();

        $parser = new LaporanExcelParser($validated['file'], $isGabungan, $infoParserData['jumlah_hari']);
        $errors = $parser->getImportErrors();

        if (! empty($errors)) {
            return response()->json([
                'message' => 'Validasi spreadsheet gagal.',
                'xlsxValidationErrors' => $errors,
            ], 422);
        }

        return DB::transaction(function () use ($request, $validated, $parser, $isGabungan, $infoParserData) {
            $upload = LaporanUpload::create([
                'uploaded_by' => $request->user()->id,
                'jenis_laporan' => $infoParserData['jenis_laporan'],
                'periode_bulan' => $infoParserData['bulan'],
                'periode_tahun' => $infoParserData['tahun'],
                'jumlah_hari' => $infoParserData['jumlah_hari'],
                'format_data' => $infoParserData['format'],
                'catatan' => $infoParserData['catatan'] ?? null,
                'status' => 'berhasil',
            ]);

            $cabangAmpera = Cabang::where('kode', 'AMPERA')->firstOrFail();
            $cabangPal = Cabang::where('kode', 'PAL')->firstOrFail();

            foreach ($parser->parseSalesHarian() as $hari => $nilai) {
                $tanggal = sprintf('%04d-%02d-%02d', $validated['periode_tahun'], $validated['periode_bulan'], $hari);

                if ($isGabungan) {
                    SalesHarian::create(['upload_id' => $upload->id, 'cabang_id' => $cabangAmpera->id, 'tanggal' => $tanggal, 'total_penjualan' => $nilai['ampera']]);
                    SalesHarian::create(['upload_id' => $upload->id, 'cabang_id' => $cabangPal->id, 'tanggal' => $tanggal, 'total_penjualan' => $nilai['pal']]);
                } else {
                    $cabangId = $validated['jenis_laporan'] === 'ampera' ? $cabangAmpera->id : $cabangPal->id;
                    SalesHarian::create(['upload_id' => $upload->id, 'cabang_id' => $cabangId, 'tanggal' => $tanggal, 'total_penjualan' => $nilai['total']]);
                }
            }

            foreach ($parser->parseProdukPenjualan('tertinggi') as $produk) {
                ProdukPenjualan::create(['upload_id' => $upload->id, ...$produk]);
            }
            foreach ($parser->parseProdukPenjualan('terendah') as $produk) {
                ProdukPenjualan::create(['upload_id' => $upload->id, ...$produk]);
            }
            foreach ($parser->parseSupplierPenjualan() as $supplier) {
                SupplierPenjualan::create(['upload_id' => $upload->id, ...$supplier]);
            }

            DataTransaksi::create([
                'upload_id' => $upload->id,
                'transaksi_member' => $parser->parseTransactionData()['transaksi_member'],
                'transaksi_non_member' => $parser->parseTransactionData()['transaksi_non_member'],
            ]);

            MemberCount::create([
                'upload_id' => $upload->id, 
                'member_baru' => $parser->parseTransactionData()['member_baru'],
            ]);

            return response()->json([
                'message' => 'Upload berhasil',
                'upload_id' => $upload->id,
            ]);
        });
    }
}
