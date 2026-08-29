<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SettingVakasi extends Model
{
    protected $table = 'setting_vakasi';

    protected $fillable = [
        'nama',
        'tipe_aktivitas',   // absen_harian | absen_mengajar | tugas_jabatan | tugas_tambahan | lembur
        'satuan',
        'nominal',
        // Khusus lembur:
        'min_durasi_menit',  // minimal menit kerja agar berhak flat
        'batas_grace_menit', // tenggang upload bukti setelah jam selesai
        // Lingkup: semua | per_jabatan | per_individu | custom
        'lingkup',
        'berlaku_untuk_semua',
        'jabatan_ids',
        'tenaga_pendidik_ids',
        'berlaku_mulai',
        'berlaku_selesai',
        'is_aktif',
        'dibuat_oleh',
    ];

    protected function casts(): array
    {
        return [
            'nominal'               => 'float',
            'min_durasi_menit'      => 'integer',
            'batas_grace_menit'     => 'integer',
            'berlaku_untuk_semua'   => 'boolean',
            'jabatan_ids'           => 'array',
            'tenaga_pendidik_ids'   => 'array',
            'berlaku_mulai'         => 'date',
            'berlaku_selesai'       => 'date',
            'is_aktif'              => 'boolean',
        ];
    }

    // ─── Relasi ──────────────────────────────────────────────────────────────

    public function dibuatOleh()
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    // ─── Helper ──────────────────────────────────────────────────────────────

    /**
     * Cek apakah vakasi berlaku untuk guru tertentu.
     * Mendukung lingkup: semua | per_jabatan | per_individu | custom
     *
     * @param TenagaPendidik $guru
     * @return bool
     */
    public function berlakuUntukGuru(TenagaPendidik $guru): bool
    {
        // Cek berdasarkan lingkup
        return match ($this->lingkup ?? 'semua') {
            'semua'          => true,
            'per_jabatan'    => $this->cekPerJabatan($guru),
            'per_individu'   => $this->cekPerIndividu($guru),
            'custom'         => $this->cekPerJabatan($guru) || $this->cekPerIndividu($guru),
            default          => $this->berlaku_untuk_semua,
        };
    }

    /**
     * Cek apakah vakasi berlaku untuk jabatan guru tersebut.
     * Support multi jabatan (rangkap jabatan).
     */
    private function cekPerJabatan(TenagaPendidik $guru): bool
    {
        $jabatanIds = $this->jabatan_ids ?? [];
        if (empty($jabatanIds)) return false;

        // Cek via pivot jabatan_guru (multi jabatan)
        $jabatanAktifIds = $guru->jabatanGuru()
            ->whereNull('berlaku_selesai')
            ->pluck('jabatan_id')
            ->toArray();

        // Fallback ke jabatan_id lama
        if (empty($jabatanAktifIds) && $guru->jabatan_id) {
            $jabatanAktifIds = [$guru->jabatan_id];
        }

        return !empty(array_intersect($jabatanIds, $jabatanAktifIds));
    }

    /**
     * Cek apakah vakasi berlaku untuk individu ini (by tenaga_pendidik_id).
     */
    private function cekPerIndividu(TenagaPendidik $guru): bool
    {
        $ids = $this->tenaga_pendidik_ids ?? [];
        return in_array($guru->id, $ids);
    }

    /**
     * Backward compat — cek by jabatan_id saja (tanpa model).
     */
    public function berlakuUntukJabatan(int $jabatanId): bool
    {
        if ($this->berlaku_untuk_semua || $this->lingkup === 'semua') return true;
        return in_array($jabatanId, $this->jabatan_ids ?? []);
    }

    /**
     * Deskripsi lingkup untuk tampilan.
     */
    public function getLingkupLabelAttribute(): string
    {
        return match ($this->lingkup ?? 'semua') {
            'semua'        => 'Semua tenaga pendidik',
            'per_jabatan'  => 'Per jabatan tertentu',
            'per_individu' => 'Per individu tertentu',
            'custom'       => 'Jabatan & individu tertentu',
            default        => 'Semua',
        };
    }

    // ─── Scope ───────────────────────────────────────────────────────────────

    public function scopeAktif($query)
    {
        return $query->where('is_aktif', true);
    }

    public function scopeByTipe($query, string $tipe)
    {
        return $query->where('tipe_aktivitas', $tipe);
    }

    public function scopeBerlakuUntukJabatan($query, int $jabatanId)
    {
        return $query->where(fn($q) =>
            $q->where('berlaku_untuk_semua', true)
              ->orWhere('lingkup', 'semua')
              ->orWhere(fn($q2) =>
                  $q2->whereIn('lingkup', ['per_jabatan', 'custom'])
                     ->whereJsonContains('jabatan_ids', $jabatanId)
              )
        );
    }

    public function scopeBerlakuUntukIndividu($query, int $guruId)
    {
        return $query->where(fn($q) =>
            $q->where('berlaku_untuk_semua', true)
              ->orWhere('lingkup', 'semua')
              ->orWhere(fn($q2) =>
                  $q2->whereIn('lingkup', ['per_individu', 'custom'])
                     ->whereJsonContains('tenaga_pendidik_ids', $guruId)
              )
        );
    }
}