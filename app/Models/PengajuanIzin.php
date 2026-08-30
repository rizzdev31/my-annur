<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PengajuanIzin extends Model
{
    use SoftDeletes;

    protected $table = 'pengajuan_izin';

    protected $fillable = [
        'tenaga_pendidik_id',
        'setting_jenis_pengajuan_id',
        'tanggal_mulai',
        'tanggal_selesai',
        'jam_mulai',
        'jam_selesai',
        'is_sementara',
        'jumlah_hari',
        'alasan',
        'file_dokumen',
        'nama_dokumen',
        'status',
        'catatan_admin',
        'diproses_oleh',
        'tanggal_keputusan',
        'absensi_sudah_diupdate',
        'status_kepegawaian_diupdate',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_mulai'                => 'date',
            'tanggal_selesai'              => 'date',
            'tanggal_keputusan'            => 'datetime',
            'is_sementara'                 => 'boolean',
            'absensi_sudah_diupdate'       => 'boolean',
            'status_kepegawaian_diupdate'  => 'boolean',
        ];
    }

    // ─── Relasi ──────────────────────────────────────────────────────────────

    public function tenagaPendidik()
    {
        return $this->belongsTo(TenagaPendidik::class);
    }

    public function jenisPengajuan()
    {
        return $this->belongsTo(SettingJenisPengajuan::class, 'setting_jenis_pengajuan_id');
    }

    public function diprosesOleh()
    {
        return $this->belongsTo(User::class, 'diproses_oleh');
    }

    // ─── Helper ──────────────────────────────────────────────────────────────

    public function getStatusBadgeAttribute(): array
    {
        return match ($this->status) {
            'pending'    => ['label' => 'Menunggu',   'class' => 'bg-amber-50 text-amber-700'],
            'disetujui'  => ['label' => 'Disetujui',  'class' => 'bg-emerald-50 text-emerald-700'],
            'ditolak'    => ['label' => 'Ditolak',    'class' => 'bg-red-50 text-red-700'],
            'dibatalkan' => ['label' => 'Dibatalkan', 'class' => 'bg-gray-100 text-gray-600'],
            default      => ['label' => ucfirst($this->status), 'class' => 'bg-gray-100 text-gray-600'],
        };
    }

    public function isPending(): bool    { return $this->status === 'pending'; }
    public function isDisetujui(): bool  { return $this->status === 'disetujui'; }
    public function isDitolak(): bool    { return $this->status === 'ditolak'; }
    public function isSementara(): bool  { return (bool) $this->is_sementara; }

    /** Izin sementara (partial-day) yang disetujui pada tanggal tertentu. */
    public function scopeSementaraAktif($query, int $tpId, string $tanggal)
    {
        return $query->where('tenaga_pendidik_id', $tpId)
            ->where('is_sementara', true)
            ->where('status', 'disetujui')
            ->whereDate('tanggal_mulai', $tanggal);
    }

    /**
     * Konversi kategori jenis ke status absensi.
     */
    public function getStatusAbsensi(): string
    {
        return match ($this->jenisPengajuan->kategori) {
            'sakit' => 'sakit',
            'cuti'  => 'izin',
            'dinas' => 'dinas_luar',
            default => 'izin',
        };
    }

    // ─── Scope ───────────────────────────────────────────────────────────────

    public function scopePending($query)   { return $query->where('status', 'pending'); }
    public function scopeDisetujui($query) { return $query->where('status', 'disetujui'); }

    public function scopeByBulan($query, int $bulan, int $tahun)
    {
        return $query->whereMonth('tanggal_mulai', $bulan)
                     ->whereYear('tanggal_mulai', $tahun);
    }
}