<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Jejak perubahan vakasi tugas tambahan.
 *
 * Sebelum ini tidak ada jejak apa pun: kolom audit di `tugas_tambahan` hanya
 * `dibuat_oleh`, dan `log_aktivitas` nol baris untuk TugasTambahan — sehingga
 * pertanyaan "siapa yang mengubah vakasi ini, kapan, dari berapa ke berapa"
 * tidak bisa dijawab. Satu baris di sini ditulis setiap kali nominal atau
 * sumbernya berubah, termasuk saat pembekuan otomatis.
 */
class RiwayatVakasiTugas extends Model
{
    protected $table = 'riwayat_vakasi_tugas';

    protected $fillable = [
        'tugas_tambahan_id', 'penugasan_id',
        'nominal_lama', 'nominal_baru',
        'sumber_lama', 'sumber_baru',
        'dikunci', 'alasan', 'diubah_oleh',
    ];

    protected function casts(): array
    {
        return [
            'nominal_lama' => 'float',
            'nominal_baru' => 'float',
            'dikunci'      => 'boolean',
        ];
    }

    public function tugasTambahan() { return $this->belongsTo(TugasTambahan::class); }
    public function penugasan()     { return $this->belongsTo(PenugasanTambahan::class, 'penugasan_id'); }
    public function diubahOleh()    { return $this->belongsTo(User::class, 'diubah_oleh'); }
}
