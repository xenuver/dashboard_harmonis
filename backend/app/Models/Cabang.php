<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cabang extends Model
{
    use HasFactory;

    protected $table = 'cabang'; // default Laravel akan cari "cabangs", kita override

    protected $fillable = [
        'kode',
        'nama',
    ];

    // ── Relasi ─────────────────────────────────────────────

    public function salesHarian(): HasMany
    {
        return $this->hasMany(SalesHarian::class, 'cabang_id');
    }
}
