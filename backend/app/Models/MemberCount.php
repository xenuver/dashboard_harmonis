<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberCount extends Model
{
    use HasFactory;

    protected $table = 'total_member';

    protected $fillable = [
        'upload_id',
        'member_baru',
    ];

    protected function casts(): array
    {
        return [
            'member_baru' => 'integer',
        ];
    }

    // ── Relasi ─────────────────────────────────────────────

    public function laporanUpload(): BelongsTo
    {
        return $this->belongsTo(LaporanUpload::class, 'upload_id');
    }

    // ── Accessor (buat tampilan) ──────────────────────────

    public function getNewMemberCount(): int
    {
        return $this->member_baru;
    }
}
