<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;

class JabatanGuru extends Model
{
    protected $table = 'jabatan_guru';

    protected $fillable = [
        'tenaga_pendidik_id',
        'jabatan_id',
        'adalah_utama',
        'berlaku_mulai',
        'berlaku_selesai',
        'keterangan',
        'ditetapkan_oleh',
    ];

    protected function casts(): array
    {
        return [
            'adalah_utama'    => 'boolean',
            'berlaku_mulai'   => 'date',
            'berlaku_selesai' => 'date',
        ];
    }

    // ─── Relasi ──────────────────────────────────────────────────────────────

    public function tenagaPendidik()
    {
        return $this->belongsTo(TenagaPendidik::class);
    }

    public function jabatan()
    {
        return $this->belongsTo(Jabatan::class);
    }

    public function ditetapkanOleh()
    {
        return $this->belongsTo(User::class, 'ditetapkan_oleh');
    }

    // ─── Scope ───────────────────────────────────────────────────────────────

    public function scopeAktif($query)
    {
        return $query->where('berlaku_mulai', '<=', now())
            ->where(fn($q) =>
                $q->whereNull('berlaku_selesai')
                  ->orWhere('berlaku_selesai', '>=', now())
            );
    }

    public function scopeUtama($query)
    {
        return $query->where('adalah_utama', true);
    }
}