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
        Schema::create('total_member', function (Blueprint $table) {
            $table->id();
            $table->foreignId('upload_id')
                ->unique()
                ->constrained('laporan_uploads')
                ->onDelete('cascade');
            $table->decimal('member_baru', 65, 0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('total_member');
    }
};
