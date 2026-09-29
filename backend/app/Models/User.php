<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Login pakai username, bukan email. Laravel tidak hardcode ke kolom
 * "email" -- tinggal panggil:
 *   Auth::attempt(['username' => $request->username, 'password' => $request->password])
 * dan itu otomatis match ke kolom username di tabel ini.
 */
class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    protected $fillable = [
        'username', // dipakai untuk login, bukan email
        'password',
        'enabled', // apakah akunnya bisa digunakan/tersembunyi dari API?
        'role', // it_staff | owner
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    // ── Relasi ─────────────────────────────────────────────

    /**
     * Semua laporan yang pernah diupload user ini.
     */
    public function laporanUploads(): HasMany
    {
        return $this->hasMany(LaporanUpload::class, 'uploaded_by');
    }

    // ── Helper ─────────────────────────────────────────────

    public function isItStaff(): bool
    {
        return $this->role === 'it_staff';
    }

    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }
}
