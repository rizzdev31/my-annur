<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu baris = satu komponen penyusun gaji pokok seorang guru pada satu masa.
 *
 * Gaji pokok seseorang = jumlah komponen yang BERLAKU pada tanggal yang ditanya.
 * Lihat migration 2026_10_28_090000 untuk alasan ia per-guru, bukan per-jabatan.
 */
class KomponenGajiPokok extends Model
{
    protected $table = 'komponen_gaji_pokok';

    /** Urutan tampil mengikuti kolom berkas asal. */
    public const URUTAN = [
        'jam_mengajar'         => 1,
        'jabatan_1'            => 2,
        'tunjangan_pendidikan' => 3,
        'jabatan_2'            => 4,
        'jabatan_3'            => 5,
    ];

    public const LABEL_JENIS = [
        'jam_mengajar'         => 'Jam Mengajar',
        'jabatan'              => 'Jabatan',
        'tunjangan_pendidikan' => 'Tunjangan Pendidikan',
    ];

    protected $fillable = [
        'tenaga_pendidik_id', 'jenis', 'label', 'nominal', 'urutan',
        'berlaku_mulai', 'berlaku_selesai', 'perlu_ditinjau', 'catatan',
        'ditetapkan_oleh',
    ];

    protected function casts(): array
    {
        return [
            'nominal'         => 'float',
            'urutan'          => 'integer',
            'berlaku_mulai'   => 'date',
            'berlaku_selesai' => 'date',
            'perlu_ditinjau'  => 'boolean',
        ];
    }

    /**
     * Keterangan yang muncul di slip. Jabatan memakai nama jabatannya; dua jenis
     * lain memakai label jenisnya, karena "Jam Mengajar" lebih jelas bagi guru
     * daripada label kosong.
     */
    public function getKeteranganAttribute(): string
    {
        if ($this->jenis === 'jabatan') {
            return $this->label ?: 'Jabatan';
        }

        return $this->label ?: (self::LABEL_JENIS[$this->jenis] ?? $this->jenis);
    }

    /** Komponen yang berlaku pada satu tanggal (bawaan: hari ini). */
    public function scopeBerlakuPada($q, ?string $tanggal = null)
    {
        $tgl = $tanggal ?? \App\Services\TimezoneHelper::today()->toDateString();

        return $q->whereDate('berlaku_mulai', '<=', $tgl)
            ->where(fn ($w) => $w->whereNull('berlaku_selesai')
                ->orWhereDate('berlaku_selesai', '>=', $tgl));
    }

    public function tenagaPendidik()
    {
        return $this->belongsTo(TenagaPendidik::class);
    }

    public function ditetapkanOleh()
    {
        return $this->belongsTo(User::class, 'ditetapkan_oleh');
    }
}
