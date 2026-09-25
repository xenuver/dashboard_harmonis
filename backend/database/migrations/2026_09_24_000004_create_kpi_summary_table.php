<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 1 baris per upload, diambil dari sheet VIEW.
     * upload_id unique -> relasi 1:1 dengan laporan_uploads.
     */
    public function up(): void
    {
        Schema::create('kpi_summary', function (Blueprint $table) {
            $table->id();
            $table->foreignId('upload_id')
                ->unique()
                ->constrained('laporan_uploads')
                ->onDelete('cascade');
            $table->bigInteger('total_sales');              // contoh: 14499385298
            $table->decimal('total_growth', 8, 6);           // contoh: 0.047615 (4,76%)
            $table->unsignedInteger('jumlah_transaksi');     // contoh: 70560
            $table->integer('growth_member');                // contoh: 303 (bisa minus)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_summary');
    }
};
