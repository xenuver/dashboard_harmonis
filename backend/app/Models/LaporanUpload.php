<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Builder;

class LaporanUpload extends Model
{
    use HasFactory;

    protected $fillable = [
        'uploaded_by',
        'jenis_laporan', // ampera | pal | gabungan
        'periode_bulan',
        'periode_tahun',
        'status', // berhasil | gagal
    ];

    protected function casts(): array
    {
        return [
            'periode_bulan' => 'integer',
            'periode_tahun' => 'integer',
        ];
    }

    // ── Relasi ─────────────────────────────────────────────

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function kpiSummary(): HasOne
    {
        return $this->hasOne(KpiSummary::class, 'upload_id');
    }

    public function salesHarian(): HasMany
    {
        return $this->hasMany(SalesHarian::class, 'upload_id');
    }

    public function produkPenjualan(): HasMany
    {
        return $this->hasMany(ProdukPenjualan::class, 'upload_id');
    }

    public function supplierPenjualan(): HasMany
    {
        return $this->hasMany(SupplierPenjualan::class, 'upload_id');
    }

    // ── Scope (buat query filter dashboard) ───────────────

    /**
     * Contoh pakai: LaporanUpload::jenisLaporan('ampera')->get();
     */
    public function scopeJenisLaporan(Builder $query, string $jenis): Builder
    {
        return $query->where('jenis_laporan', $jenis);
    }

    /**
     * Contoh pakai: LaporanUpload::periode(8, 2026)->first();
     */
    public function scopePeriode(Builder $query, int $bulan, int $tahun): Builder
    {
        return $query->where('periode_bulan', $bulan)->where('periode_tahun', $tahun);
    }
}
