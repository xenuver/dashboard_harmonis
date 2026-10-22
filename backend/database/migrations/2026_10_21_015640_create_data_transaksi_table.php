<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('data_transaksi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('upload_id')
                ->unique()
                ->constrained('laporan_uploads')
                ->onDelete('cascade');
            $table->decimal('transaksi_member', 65, 0)->unsigned();
            $table->decimal('transaksi_non_member', 65, 0)->unsigned();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('data_transaksi');
    }
};
