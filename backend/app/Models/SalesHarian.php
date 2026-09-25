<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesHarian extends Model
{
    use HasFactory;

    protected $table = 'sales_harian'; // default Laravel akan cari "sales_harians"

    protected $fillable = [
        'upload_id',
        'cabang_id',
        'tanggal',
        'total_penjualan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'total_penjualan' => 'integer',
        ];
    }

    // ── Relasi ─────────────────────────────────────────────

    public function laporanUpload(): BelongsTo
    {
        return $this->belongsTo(LaporanUpload::class, 'upload_id');
    }

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class, 'cabang_id');
    }
}
