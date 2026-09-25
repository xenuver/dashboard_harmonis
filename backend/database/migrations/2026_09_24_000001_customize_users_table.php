<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menyesuaikan tabel users bawaan Laravel untuk kebutuhan app ini:
     * - Tambah kolom role -> staff IT (full access, bisa upload) atau
     *   owner (read-only, cuma bisa lihat dashboard)
     * - Tambah kolom username -> dipakai untuk login, GANTI email
     * - Hapus email & email_verified_at -> app internal ini tidak perlu
     *   verifikasi email atau reset password lewat email
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 50)->unique()->after('name');
            $table->enum('role', ['it_staff', 'owner'])->default('it_staff')->after('username');
            $table->dropColumn(['name', 'email', 'email_verified_at']);
        });
    }
};
