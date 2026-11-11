<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 1 baris = 1 file Excel yang diupload staff IT.
     * jenis_laporan menentukan cakupan file: ampera / pal / gabungan.
     * PENTING: gabungan BUKAN hasil jumlah otomatis ampera+pal, file terpisah
     * dari Phoenix — lihat catatan di dokumen Spesifikasi Halaman.
     */
    public function up(): void
    {
        Schema::create('laporan_uploads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('uploaded_by')
                ->constrained('users')
                ->onDelete('restrict');
            $table->enum('jenis_laporan', ['ampera', 'pal', 'gabungan']);
            $table->unsignedTinyInteger('periode_bulan'); // 1-12
            $table->unsignedSmallInteger('periode_tahun'); // contoh: 2026
            $table->enum('status', ['berhasil', 'gagal'])->default('berhasil');
            $table->timestamps();

            // Cegah upload dobel untuk kombinasi jenis laporan + periode yang sama
            $table->unique(['jenis_laporan', 'periode_bulan', 'periode_tahun'], 'uq_laporan_periode');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laporan_uploads');
    }
};
