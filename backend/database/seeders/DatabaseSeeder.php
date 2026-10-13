<?php

namespace Database\Seeders;

use App\Models\Cabang;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Cabang::firstOrCreate(['kode' => 'AMPERA'], ['nama' => 'Ampera']);
        Cabang::firstOrCreate(['kode' => 'PAL'], ['nama' => 'Pal']);

        User::factory()->create([
            'username' => 'admin',
            'password' => bcrypt("admin"),
            'enabled' => true,
            'role' => 'it_staff'
        ]);
    }
}
