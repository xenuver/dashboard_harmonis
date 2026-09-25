<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KpiSummary extends Model
{
    use HasFactory;

    protected $table = 'kpi_summary'; // default Laravel akan cari "kpi_summaries"

    protected $fillable = [
        'upload_id',
        'total_sales',
        'total_growth',
        'jumlah_transaksi',
        'growth_member',
    ];

    protected function casts(): array
    {
        return [
            'total_sales' => 'integer',
            'total_growth' => 'decimal:6',
            'jumlah_transaksi' => 'integer',
            'growth_member' => 'integer',
        ];
    }

    // ── Relasi ─────────────────────────────────────────────

    public function laporanUpload(): BelongsTo
    {
        return $this->belongsTo(LaporanUpload::class, 'upload_id');
    }

    // ── Accessor (buat tampilan) ──────────────────────────

    /**
     * total_growth disimpan sebagai desimal (0.047615) -> tampilkan sebagai persen.
     * Pakai: $kpi->total_growth_percent  => "4.76%"
     */
    public function getTotalGrowthPercentAttribute(): string
    {
        return number_format($this->total_growth * 100, 2) . '%';
    }
}
