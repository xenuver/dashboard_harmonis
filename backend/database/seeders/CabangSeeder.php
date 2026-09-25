<?php

namespace Database\Seeders;

use App\Models\Cabang;
use Illuminate\Database\Seeder;

class CabangSeeder extends Seeder
{
    /**
     * Jalankan dengan: php artisan db:seed --class=CabangSeeder
     * (atau panggil dari DatabaseSeeder supaya ikut ter-run pas migrate:fresh --seed)
     */
    public function run(): void
    {
        Cabang::firstOrCreate(['kode' => 'AMPERA'], ['nama' => 'Ampera']);
        Cabang::firstOrCreate(['kode' => 'PAL'], ['nama' => 'Pal']);
    }
}
