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
            if (DB::getDriverName() === 'sqlite') { // sqlite tidak bisa menggunakan after
                $table->unsignedTinyInteger('jumlah_hari');
                $format_data = $table->string('format_data', 50);
                $catatan = $table->text('catatan')->nullable();
                if (DB::getDriverName() === 'sqlite') {
                    $format_data->collation('NOCASE');
                    $catatan->collation('NOCASE');
                }
            } else {
                $table->unsignedTinyInteger('jumlah_hari')->after('periode_tahun');
                $table->string('format_data', 50)->after('jumlah_hari');
                $table->text('catatan')->nullable()->after('format_data');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('laporan_uploads', function (Blueprint $table) {
            $table->dropColumn('jumlah_hari');
            $table->dropColumn('format_data');
            $table->dropColumn('catatan');
        });
    }
};
