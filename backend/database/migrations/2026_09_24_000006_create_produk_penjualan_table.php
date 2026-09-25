<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ~300 baris per kategori per upload, dari sheet TOP 300 HIGHEST / TOP 300 LOWEST.
     * kategori membedakan tertinggi vs terendah.
     * qty bisa negatif -> menandakan barang retur (lihat sheet TOP 300 LOWEST asli),
     * bukan sekadar "kurang laku". Tandai beda secara visual di frontend.
     */

    private \Closure $createTable;

    public function __construct() {
        $this -> createTable = function(Blueprint $table) {
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
            $table->bigInteger('jumlah');   // nilai penjualan (Rp), bisa negatif kalau retur
            $table->integer('qty');         // qty terjual, bisa negatif kalau retur
            $table->integer('stok')->nullable();
            $table->timestamps();

            $table->index(['upload_id', 'kategori']);
            $table->index('kode_brg'); // buat search/filter produk
        };
    }

    public function up(): void
    {
        Schema::create('produk_penjualan', $this -> createTable);
        Schema::create('produk_penjualan_temp', $this -> createTable);
    }

    public function down(): void
    {
        Schema::dropIfExists('produk_penjualan');
        Schema::dropIfExists('produk_penjualan_temp');
    }
};
