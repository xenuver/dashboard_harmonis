<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ~300 baris per kategori per upload, dari sheet TOP 300 HIGHEST / TOP 300 LOWEST.
     * kategori membedakan tertinggi vs terendah.
     * qty bisa negatif -> menandakan barang retur (lihat sheet TOP 300 LOWEST asli),
     * bukan sekadar "kurang laku". Tandai beda secara visual di frontend.
     */
    private Closure $createTable;

    public function __construct()
    {
        $this->createTable = function (Blueprint $table) {
            $table->id();
            $table->foreignId('upload_id')
                ->constrained('laporan_uploads')
                ->onDelete('cascade');
            $kategori = $table->enum('kategori', ['tertinggi', 'terendah']);
            $barcode = $table->string('barcode', 50)->nullable();
            $kode_brg = $table->string('kode_brg', 20);
            $nama_brg = $table->string('nama_brg', 255);
            $satuan = $table->string('satuan', 20)->nullable();
            $table->bigInteger('harga_jual');
            $table->bigInteger('jumlah');   // nilai penjualan (Rp), bisa negatif kalau retur
            $table->integer('qty');         // qty terjual, bisa negatif kalau retur
            $table->integer('stok')->nullable();
            if (DB::getDriverName() === 'sqlite') {
                $kategori->collation('NOCASE');
                $barcode->collation('NOCASE');
                $kode_brg->collation('NOCASE');
                $nama_brg->collation('NOCASE');
                $satuan->collation('NOCASE');
            }
            $table->timestamps();

            $table->index(['upload_id', 'kategori']);
            $table->index('kode_brg'); // buat search/filter produk
        };
    }

    public function up(): void
    {
        Schema::create('produk_penjualan', $this->createTable);
        Schema::create('produk_penjualan_temp', $this->createTable);
    }

    public function down(): void
    {
        Schema::dropIfExists('produk_penjualan');
        Schema::dropIfExists('produk_penjualan_temp');
    }
};
