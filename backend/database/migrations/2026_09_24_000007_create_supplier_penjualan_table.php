<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ~375 baris per upload, dari sheet TOP SUPPLIER.
     */

    private \Closure $createTable;

    public function __construct()
    {
        $this -> createTable = function (Blueprint $table) {
            $table->id();
            $table->foreignId('upload_id')
                ->constrained('laporan_uploads')
                ->onDelete('cascade');
            $table->string('kode_supp', 20);
            $table->string('nama_supp', 255);
            $table->bigInteger('gross_total');
            $table->bigInteger('net_sales_total');
            $table->timestamps();

            $table->index(['upload_id', 'net_sales_total']); // buat sort ranking
            $table->index('kode_supp'); // buat search
        };
    }

    public function up(): void
    {
        Schema::create('supplier_penjualan', $this -> createTable);
        Schema::create('supplier_penjualan_temp', $this -> createTable);
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_penjualan');
        Schema::dropIfExists('supplier_penjualan_temp');
    }
};
