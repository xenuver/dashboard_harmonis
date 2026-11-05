<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierPenjualan extends Model
{
    use HasFactory;

    protected $table = 'supplier_penjualan'; // default Laravel akan cari "supplier_penjualans"

    protected $fillable = [
        'upload_id',
        'kode_supp',
        'nama_supp',
        'gross_total',
        'net_sales_total',
    ];

    protected function casts(): array
    {
        return [
            'gross_total' => 'integer',
            'net_sales_total' => 'integer',
        ];
    }

    // ── Relasi ─────────────────────────────────────────────

    public function laporanUpload(): BelongsTo
    {
        return $this->belongsTo(LaporanUpload::class, 'upload_id');
    }

    // ── Scope ──────────────────────────────────────────────

    public function scopeCari(Builder $query, string $keyword): Builder
    {
        $keywords = preg_split('/\s+/', trim($keyword), -1, PREG_SPLIT_NO_EMPTY);

        return $query->where(function (Builder $q) use ($keywords) {
            foreach ($keywords as $keyword) {
                $keyword = strtr($keyword, [
                    '!' => '!!',
                    '%' => '!%',
                    '_' => '!_',
                ]);

                $q->where(function (Builder $q) use ($keyword) {
                    $pattern = "%{$keyword}%";

                    $q->whereRaw("nama_supp LIKE ? ESCAPE '!'", [$pattern])
                        ->orWhereRaw("kode_supp LIKE ? ESCAPE '!'", [$pattern]);
                });
            }
        });
    }
}
