<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetailPenggajian extends Model
{
    protected $table = 'detail_penggajian';

    protected $fillable = [
        'penggajian_id',
        'tipe',
        'keterangan',
        'jumlah_satuan',
        'satuan',
        'nilai_per_satuan',
        'subtotal',
        'referensi_ids',
    ];

    protected function casts(): array
    {
        return [
            'jumlah_satuan'    => 'integer',
            'nilai_per_satuan' => 'float',
            'subtotal'         => 'float',
            'referensi_ids'    => 'array',
        ];
    }

    // ─── Relasi ──────────────────────────────────────────────────────────────

    public function penggajian()
    {
        return $this->belongsTo(Penggajian::class);
    }

    // ─── Helper ──────────────────────────────────────────────────────────────

    /**
     * Label tipe untuk tampilan UI.
     */
    public function getLabelTipeAttribute(): string
    {
        return match ($this->tipe) {
            'gaji_pokok'             => 'Gaji Pokok',
            'vakasi_absen'           => 'Vakasi Kehadiran',
            'vakasi_mengajar'        => 'Vakasi Mengajar',
            'vakasi_tugas_jabatan'   => 'Vakasi Tugas Jabatan',
            'vakasi_tugas_tambahan'  => 'Vakasi Tugas Tambahan',
            'vakasi_peserta_kegiatan'=> 'Vakasi Peserta Kegiatan',
            'tunjangan'              => 'Tunjangan',
            'potongan_terlambat'     => 'Potongan Keterlambatan',
            'potongan_alfa'          => 'Potongan Alfa',
            'potongan_bpjs'          => 'Potongan BPJS',
            'potongan_lain'          => 'Potongan Lainnya',
            'penyesuaian_liburan'    => 'Penyesuaian Liburan',
            default                  => ucfirst(str_replace('_', ' ', $this->tipe)),
        };
    }

    /**
     * Daftar tipe yang dihitung sebagai POTONGAN (mengurangi gaji).
     * Selain prefix 'potongan', penyesuaian_liburan juga potongan.
     */
    public const TIPE_POTONGAN_LAIN = ['penyesuaian_liburan'];

    /**
     * Apakah tipe ini termasuk potongan (negatif).
     */
    public function isPotongan(): bool
    {
        return str_starts_with($this->tipe, 'potongan')
            || in_array($this->tipe, self::TIPE_POTONGAN_LAIN, true);
    }

    // ─── Scope ───────────────────────────────────────────────────────────────

    public function scopePendapatan($query)
    {
        return $query->where('tipe', 'not like', 'potongan%')
            ->whereNotIn('tipe', self::TIPE_POTONGAN_LAIN);
    }

    public function scopePotongan($query)
    {
        return $query->where(fn($q) =>
            $q->where('tipe', 'like', 'potongan%')
              ->orWhereIn('tipe', self::TIPE_POTONGAN_LAIN)
        );
    }
}