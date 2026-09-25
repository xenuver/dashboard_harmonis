<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rincian penjualan harian, dari sheet DATA.
     * cabang_id SELALU diisi (NOT NULL) -- termasuk untuk upload jenis
     * ampera/pal biasa -- supaya query dashboard seragam (tinggal WHERE/GROUP BY
     * cabang_id) tanpa perlu logic beda tergantung jenis file sumbernya.
     *
     * Untuk upload jenis "gabungan": sheet DATA-nya punya kolom SUM AMP & SUM PAL
     * terpisah per tanggal -> insert 2 baris per tanggal (1 per cabang), bukan
     * 1 baris gabungan.
     */
    public function up(): void
    {
        Schema::create('sales_harian', function (Blueprint $table) {
            $table->id();
            $table->foreignId('upload_id')
                ->constrained('laporan_uploads')
                ->onDelete('cascade');
            $table->foreignId('cabang_id')
                ->constrained('cabang')
                ->onDelete('restrict');
            $table->date('tanggal');
            $table->bigInteger('total_penjualan');
            $table->timestamps();

            $table->index(['cabang_id', 'tanggal']); // buat query grafik tren
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_harian');
    }
};
