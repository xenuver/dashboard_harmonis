<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private Closure $createProductTempTable;

    private Closure $createSupplierTempTable;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('produk_penjualan_temp');
        Schema::dropIfExists('supplier_penjualan_temp');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->createProductTempTable = function (Blueprint $table) {
            $table->id();
            $table->foreignId('upload_id')
                ->constrained('laporan_uploads')
                ->onDelete('cascade');
            $table->enum('kategori', ['tertinggi', 'terendah']);
            $table->string('barcode', 50)->nullable();
            $table->string('kode_brg', 20);
            $table->string('nama_brg', 255);
            $table->string('satuan', 20)->nullable();
            $table->bigInteger('harga_jual');
            $table->bigInteger('jumlah');
            $table->integer('qty');
            $table->integer('stok')->nullable();
            $table->timestamps();

            $table->index(['upload_id', 'kategori']);
            $table->index('kode_brg');
        };
        $this->createSupplierTempTable = function (Blueprint $table) {
            $table->id();
            $table->foreignId('upload_id')
                ->constrained('laporan_uploads')
                ->onDelete('cascade');
            $table->string('kode_supp', 20);
            $table->string('nama_supp', 255);
            $table->bigInteger('gross_total');
            $table->bigInteger('net_sales_total');
            $table->timestamps();

            $table->index(['upload_id', 'net_sales_total']);
            $table->index('kode_supp');
        };
    }
};
