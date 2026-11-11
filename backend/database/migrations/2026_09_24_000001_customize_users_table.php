<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
            if (DB::getDriverName() === 'sqlite') { // sqlite tidak bisa menggunakan after
                $table->string('username', 50);
                $role = $table->enum('role', ['it_staff', 'owner'])->default('it_staff');
                if (DB::getDriverName() === 'sqlite') {
                    $role->collation('NOCASE');
                }
            } else {
                $table->string('username', 50)->unique()->after('name');
                $table->enum('role', ['it_staff', 'owner'])->default('it_staff')->after('username');
            }
            $table->boolean('enabled')->default(true);
            $table->dropUnique('users_email_unique'); // trait unique harus dihapus dalam sqlite sebelum drop kolom
            $table->dropColumn(['name', 'email', 'email_verified_at']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (DB::getDriverName() === 'sqlite') { // sqlite tidak bisa menggunakan after
                $table->string('name');
                $email = $table->string('email', 50);
                if (DB::getDriverName() === 'sqlite') {
                    $email->collation('NOCASE');
                }
                $email->unique();
                $table->timestamp('email_verified_at')->nullable();
            } else {
                $table->string('name')->after('id');
                $table->string('email')->unique()->after('name');
                $table->timestamp('email_verified_at')->nullable()->after('email');
            }
            $table->dropUnique('users_username_unique'); // trait unique harus dihapus dalam sqlite sebelum drop kolom
            $table->dropColumn(['username', 'role', 'enabled']);
        });
    }
};
