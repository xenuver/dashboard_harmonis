<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataTransaksi extends Model
{
    use HasFactory;

    protected $table = 'data_transaksi';

    protected $fillable = [
        'upload_id',
        'transaksi_member',
        'transaksi_non_member',
    ];

    protected function casts(): array
    {
        return [
            'transaksi_member' => 'integer',
            'transaksi_non_member' => 'integer',
        ];
    }

    // ── Relasi ─────────────────────────────────────────────

    public function laporanUpload(): BelongsTo
    {
        return $this->belongsTo(LaporanUpload::class, 'upload_id');
    }

    // ── Accessor (buat tampilan) ──────────────────────────

    public function getTotalMemberTransaction(): int
    {
        return $this->transaksi_member;
    }

    public function getTotalNonMemberTransaction(): int
    {
        return $this->transaksi_non_member;
    }

    public function getTotalTransaction(): int
    {
        return $this->transaksi_non_member + $this->transaksi_member;
    }
}
