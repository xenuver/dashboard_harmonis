<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('laporan_uploads', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('laporan_uploads', function (Blueprint $table) {
            $status = $table->enum('status', ['berhasil', 'gagal'])->default('berhasil');
            if (DB::getDriverName() === 'sqlite') {
                $status->collation('NOCASE');
            }
        });
    }
};
