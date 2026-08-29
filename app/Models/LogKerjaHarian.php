<?php
// ═══════════════════════════════════════════════════════
// Model: LogKerjaHarian
// ═══════════════════════════════════════════════════════
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogKerjaHarian extends Model
{
    protected $table = 'log_kerja_harian';

    protected $fillable = [
        'tenaga_pendidik_id', 'tanggal', 'judul', 'deskripsi',
        'tugas_jabatan_id', 'kategori_custom',
        'durasi_menit', 'file_bukti',
        'status', 'diverifikasi_oleh', 'catatan_verifikasi', 'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'tanggal'     => 'date',
            'verified_at' => 'datetime',
        ];
    }

    public function tenagaPendidik()
    {
        return $this->belongsTo(TenagaPendidik::class);
    }

    public function tugasJabatan()
    {
        return $this->belongsTo(TugasJabatan::class);
    }

    public function diverifikasiOleh()
    {
        return $this->belongsTo(User::class, 'diverifikasi_oleh');
    }

    public function getStatusBadgeAttribute(): array
    {
        return match ($this->status) {
            'draft'       => ['label' => 'Draft',        'class' => 'bg-gray-100 text-gray-500'],
            'submitted'   => ['label' => 'Menunggu',     'class' => 'bg-amber-50 text-amber-700'],
            'diverifikasi'=> ['label' => 'Terverifikasi','class' => 'bg-emerald-50 text-emerald-700'],
            'ditolak'     => ['label' => 'Ditolak',      'class' => 'bg-red-50 text-red-700'],
            default       => ['label' => ucfirst($this->status), 'class' => 'bg-gray-100 text-gray-500'],
        };
    }

    public function getDurasiFormatAttribute(): string
    {
        if (!$this->durasi_menit) return '—';
        $jam   = intdiv($this->durasi_menit, 60);
        $menit = $this->durasi_menit % 60;
        return $jam > 0 ? "{$jam}j {$menit}m" : "{$menit}m";
    }

    public function scopeByBulan($q, int $bulan, int $tahun)
    {
        return $q->whereMonth('tanggal', $bulan)->whereYear('tanggal', $tahun);
    }

    public function scopeSubmitted($q)  { return $q->where('status', 'submitted'); }
    public function scopeVerified($q)   { return $q->where('status', 'diverifikasi'); }
}