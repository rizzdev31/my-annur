<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes, HasApiTokens;

    protected $fillable = [
        'name',
        'email',
        'username',
        'password',
        'role',
        'status',
        'foto',
        'fcm_token',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    // ─── Relasi ──────────────────────────────────────────────────────────────

    public function tenagaPendidik()
    {
        return $this->hasOne(TenagaPendidik::class);
    }

    public function notifikasi()
    {
        return $this->hasMany(Notifikasi::class);
    }

    public function logAktivitas()
    {
        return $this->hasMany(LogAktivitas::class);
    }

    // ─── Role Helpers ────────────────────────────────────────────────────────

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isTenagaPendidik(): bool
    {
        return $this->role === 'tenaga_pendidik';
    }

    public function isSantri(): bool
    {
        return $this->role === 'santri';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    // ─── RBAC Peran (web admin) ───────────────────────────────────────────────

    public function peran()
    {
        return $this->belongsToMany(Peran::class, 'user_peran', 'user_id', 'peran_id')
            ->withPivot('ditetapkan_oleh')->withTimestamps();
    }

    /** Semua kode modul yang boleh diakses user. Superadmin = SEMUA modul. */
    public function modulSaya(): array
    {
        if ($this->isSuperAdmin()) {
            return array_keys(config('modul.daftar', []));
        }

        return \Illuminate\Support\Facades\DB::table('user_peran')
            ->join('peran', 'peran.id', '=', 'user_peran.peran_id')
            ->join('peran_modul', 'peran_modul.peran_id', '=', 'peran.id')
            ->where('user_peran.user_id', $this->id)
            ->where('peran.is_aktif', true)
            ->pluck('peran_modul.modul')
            ->unique()->values()->all();
    }

    public function bolehModul(string $kode): bool
    {
        return $this->isSuperAdmin() || in_array($kode, $this->modulSaya(), true);
    }

    /** Apakah user boleh mengakses route bernama tsb (via peta modul). Superadmin selalu boleh. */
    public function bolehRoute(?string $namaRoute): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }
        if (!$namaRoute) {
            return false;
        }
        $modul = static::modulUntukRoute($namaRoute);
        return $modul !== null && $this->bolehModul($modul);
    }

    /** Petakan nama route → kode modul (batas segmen agar tak salah cocok). */
    public static function modulUntukRoute(string $namaRoute): ?string
    {
        foreach (config('modul.daftar', []) as $kode => $def) {
            foreach ((array) ($def['prefix'] ?? []) as $pre) {
                $p = rtrim($pre, '.');
                if ($namaRoute === $p || str_starts_with($namaRoute, $p . '.')) {
                    return $kode;
                }
            }
        }
        return null;
    }

    /** Route landing setelah login: superadmin → dashboard; admin → menu modul pertamanya. */
    public function berandaRoute(): ?string
    {
        if ($this->isSuperAdmin()) {
            return 'admin.dashboard';
        }
        $mine = $this->modulSaya();
        foreach (config('modul.daftar', []) as $kode => $def) {
            if (in_array($kode, $mine, true) && !empty($def['beranda'])) {
                return $def['beranda'];
            }
        }
        return null;
    }

    // ─── Notifikasi Helpers ──────────────────────────────────────────────────

    public function notifikasiBelumDibaca()
    {
        return $this->notifikasi()->where('sudah_dibaca', false);
    }

    public function jumlahNotifikasiBelumDibaca(): int
    {
        return $this->notifikasiBelumDibaca()->count();
    }

    // ─── Scope ───────────────────────────────────────────────────────────────

    public function scopeAktif($query)
    {
        return $query->where('status', 'aktif');
    }

    public function scopeByRole($query, string $role)
    {
        return $query->where('role', $role);
    }
}