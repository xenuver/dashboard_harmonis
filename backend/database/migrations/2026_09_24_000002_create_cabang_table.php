<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Master data cabang. Isinya cuma 2 baris: Ampera & Pal.
     * Diisi lewat CabangSeeder, bukan lewat form (tidak perlu CRUD untuk MVP).
     */
    public function up(): void
    {
        Schema::create('cabang', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 20)->unique();  // contoh: AMPERA, PAL
            $table->string('nama', 100);           // contoh: Ampera, Pal
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cabang');
    }
};
