<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu baris = satu versi skor kinerja yang PERNAH berlaku sebelum ditimpa.
 * Ditulis otomatis oleh RekapKinerjaBulananObserver — jangan tulis manual dari
 * controller agar tidak ada jalur perubahan yang lolos tanpa jejak.
 */
class RiwayatRekapKinerja extends Model
{
    protected $table = 'riwayat_rekap_kinerja';

    protected $fillable = [
        'rekap_kinerja_bulanan_id', 'tenaga_pendidik_id', 'bulan', 'tahun',
        'skor_total', 'skor_absensi', 'skor_tugas', 'skor_administrasi', 'skor_piket',
        'skor_total_baru', 'sudah_dikunci_lama', 'catatan_superadmin_lama', 'data_lama',
        'sebab', 'alasan', 'diubah_oleh', 'setting_kinerja_id',
    ];

    protected function casts(): array
    {
        return [
            'skor_total'         => 'float',
            'skor_absensi'       => 'float',
            'skor_tugas'         => 'float',
            'skor_administrasi'  => 'float',
            'skor_piket'         => 'float',
            'skor_total_baru'    => 'float',
            'sudah_dikunci_lama' => 'boolean',
            'data_lama'          => 'array',
        ];
    }

    public const LABEL_SEBAB = [
        'awal'         => 'Nilai awal tersimpan',
        'hitung_ulang' => 'Dihitung ulang dari data',
        'reset'        => 'Direset admin',
        'reset_semua'  => 'Reset massal periode',
        'override'     => 'Skor ditetapkan manual',
        'finalisasi'   => 'Dibekukan saat finalisasi penggajian',
        'catatan'      => 'Catatan admin diubah',
    ];

    public function getLabelSebabAttribute(): string
    {
        return self::LABEL_SEBAB[$this->sebab] ?? $this->sebab;
    }

    /** Selisih skor: positif = naik, negatif = turun. */
    public function getSelisihAttribute(): ?float
    {
        if ($this->skor_total === null || $this->skor_total_baru === null) return null;
        return round($this->skor_total_baru - $this->skor_total, 2);
    }

    public function tenagaPendidik()
    {
        return $this->belongsTo(TenagaPendidik::class);
    }

    public function pengubah()
    {
        return $this->belongsTo(User::class, 'diubah_oleh');
    }
}
