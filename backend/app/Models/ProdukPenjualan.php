<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class ProdukPenjualan extends Model
{
    use HasFactory;

    protected $table = 'produk_penjualan'; // default Laravel akan cari "produk_penjualans"

    protected $fillable = [
        'upload_id',
        'kategori', // tertinggi | terendah
        'barcode',
        'kode_brg',
        'nama_brg',
        'satuan',
        'harga_jual',
        'jumlah',
        'qty',
        'stok',
    ];

    protected function casts(): array
    {
        return [
            'harga_jual' => 'integer',
            'jumlah' => 'integer',
            'qty' => 'integer',
            'stok' => 'integer',
        ];
    }

    // ── Relasi ─────────────────────────────────────────────

    public function laporanUpload(): BelongsTo
    {
        return $this->belongsTo(LaporanUpload::class, 'upload_id');
    }

    // ── Scope ──────────────────────────────────────────────

    public function scopeTertinggi(Builder $query): Builder
    {
        return $query->where('kategori', 'tertinggi');
    }

    public function scopeTerendah(Builder $query): Builder
    {
        return $query->where('kategori', 'terendah');
    }

    public function scopeCari(Builder $query, string $keyword): Builder
    {
        return $query->where(function (Builder $q) use ($keyword) {
            $q->where('nama_brg', 'like', "%{$keyword}%")
                ->orWhere('kode_brg', 'like', "%{$keyword}%");
        });
    }

    // ── Helper ─────────────────────────────────────────────

    /**
     * qty negatif = barang retur, BUKAN sekadar "kurang laku".
     * Dipakai frontend untuk kasih badge/tanda khusus di tabel.
     */
    public function isRetur(): bool
    {
        return $this->qty < 0;
    }
}
