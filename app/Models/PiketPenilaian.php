<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PiketPenilaian extends Model
{
    protected $table = 'piket_penilaian';

    protected $fillable = [
        'piket_jadwal_id', 'guru_dinilai_id', 'kategori_id', 'jenis', 'dimensi', 'poin',
        'catatan', 'bukti_foto', 'jadwal_mengajar_id', 'absensi_mengajar_id', 'status_sanggah',
        'jumlah_sanggah', 'alasan_sanggah', 'ditinjau_oleh', 'ditinjau_pada', 'catatan_tinjauan',
    ];

    protected $casts = [
        'poin'           => 'float',
        'jumlah_sanggah' => 'integer',
        'ditinjau_pada'  => 'datetime',
    ];

    /** Poin bertanda: apresiasi (+), catatan (−). */
    public function getPoinBertandaAttribute(): float
    {
        return $this->jenis === 'catatan' ? -abs($this->poin) : abs($this->poin);
    }

    public function jadwal()      { return $this->belongsTo(PiketJadwal::class, 'piket_jadwal_id'); }
    public function kategori()    { return $this->belongsTo(PiketKategori::class, 'kategori_id'); }
    public function guruDinilai() { return $this->belongsTo(TenagaPendidik::class, 'guru_dinilai_id'); }
}
