<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JadwalMengajar extends Model
{
    protected $table = 'jadwal_mengajar';

    protected $fillable = [
        'tahun_ajaran_id',
        'tenaga_pendidik_id',
        'mata_pelajaran_id',
        'kelas_id',
        'hari',
        'jam_mulai',
        'jam_selesai',
        'jumlah_jp',
        'kelas',
        'ruangan',
        'is_aktif',
    ];

    protected function casts(): array
    {
        return [
            'is_aktif'   => 'boolean',
            'jumlah_jp'  => 'integer',
        ];
    }

    // ─── Relasi ──────────────────────────────────────────────────────────────

    public function tahunAjaran()
    {
        return $this->belongsTo(TahunAjaran::class);
    }

    public function tenagaPendidik()
    {
        return $this->belongsTo(TenagaPendidik::class);
    }

    public function mataPelajaran()
    {
        return $this->belongsTo(MataPelajaran::class);
    }

    public function kelasRel()
    {
        return $this->belongsTo(Kelas::class, 'kelas_id');
    }

    public function absensiMengajar()
    {
        return $this->hasMany(AbsensiMengajar::class);
    }

    // ─── Scope ───────────────────────────────────────────────────────────────

    public function scopeAktif($query)
    {
        return $query->where('is_aktif', true);
    }

    public function scopeHariIni($query)
    {
        $hariMap = [
            'Monday'    => 'senin',
            'Tuesday'   => 'selasa',
            'Wednesday' => 'rabu',
            'Thursday'  => 'kamis',
            'Friday'    => 'jumat',
            'Saturday'  => 'sabtu',
            'Sunday'    => 'ahad',
        ];

        return $query->where('hari', $hariMap[now()->format('l')]);
    }
}