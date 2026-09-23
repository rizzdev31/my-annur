<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ekstrakurikuler extends Model
{
    protected $table = 'ekstrakurikuler';
    protected $fillable = ['nama', 'deskripsi', 'pembina_id', 'hari', 'jam_mulai', 'jam_selesai',
        'lokasi', 'wajib_lokasi', 'pertemuan_per_bulan', 'tahun_ajaran_id', 'kuota', 'nominal_vakasi',
        'batas_isi_hari', 'is_aktif', 'dibuat_oleh'];
    protected $casts = ['is_aktif' => 'boolean', 'nominal_vakasi' => 'float', 'kuota' => 'integer',
        'batas_isi_hari' => 'integer', 'wajib_lokasi' => 'boolean', 'pertemuan_per_bulan' => 'integer'];

    /** Pembina harus berada di area yang diizinkan saat membuka pertemuan. */
    public function wajibLokasi(): bool { return (bool) ($this->wajib_lokasi ?? true); }

    public function pembina()    { return $this->belongsTo(TenagaPendidik::class, 'pembina_id'); }
    public function tahunAjaran(){ return $this->belongsTo(TahunAjaran::class, 'tahun_ajaran_id'); }
    public function anggota()    { return $this->hasMany(EkstrakurikulerSantri::class); }
    public function santri()     { return $this->belongsToMany(Santri::class, 'ekstrakurikuler_santri')->withPivot('is_aktif')->withTimestamps(); }
    public function pertemuan()  { return $this->hasMany(EkstrakurikulerPertemuan::class); }
    public function penilaian()  { return $this->hasMany(EkstrakurikulerPenilaian::class); }

    public function scopeAktif($q) { return $q->where('is_aktif', true); }
}
