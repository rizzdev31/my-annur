<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GajiPokokIndividu extends Model
{
    protected $table = 'gaji_pokok_individu';

    protected $fillable = [
        'tenaga_pendidik_id',
        'jabatan_id',   // NULL = override total, DIISI = override spesifik per jabatan
        'nominal',
        'berlaku_mulai',
        'berlaku_selesai',
        'alasan',       // kolom lama, tetap dipertahankan
        'keterangan',   // alias alasan
        'dibuat_oleh',
    ];

    protected function casts(): array
    {
        return [
            'nominal'         => 'float',
            'berlaku_mulai'   => 'date',
            'berlaku_selesai' => 'date',
        ];
    }

    public function tenagaPendidik()
    {
        return $this->belongsTo(TenagaPendidik::class);
    }

    public function jabatan()
    {
        return $this->belongsTo(Jabatan::class);
    }

    public function dibuatOleh()
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    // ── Scope ─────────────────────────────────────────────────────────────────

    public function scopeAktif($query)
    {
        return $query->where('berlaku_mulai', '<=', now())
            ->where(fn($q) =>
                $q->whereNull('berlaku_selesai')
                  ->orWhere('berlaku_selesai', '>=', now())
            );
    }

    public function scopePerJabatan($query, int $jabatanId)
    {
        return $query->where('jabatan_id', $jabatanId);
    }

    public function scopeTotal($query)
    {
        return $query->whereNull('jabatan_id');
    }
}