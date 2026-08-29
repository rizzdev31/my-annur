<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Peminjaman/pemakaian inventaris berbasis slot waktu (tanggal + jam mulai–selesai).
 * Status: diajukan → disetujui/ditolak; disetujui → selesai; bisa dibatalkan.
 * Slot yang "mengunci" ketersediaan = status 'disetujui'.
 */
class PeminjamanInventaris extends Model
{
    protected $table = 'peminjaman_inventaris';

    protected $fillable = [
        'inventaris_id', 'tenaga_pendidik_id', 'jumlah', 'keperluan',
        'tanggal', 'jam_mulai', 'jam_selesai',
        'status', 'disetujui_oleh', 'catatan_admin', 'diputuskan_pada',
    ];

    protected function casts(): array
    {
        return [
            'tanggal'         => 'date',
            'jumlah'          => 'integer',
            'diputuskan_pada' => 'datetime',
        ];
    }

    // Status yang sedang "memakai" slot (mengunci kapasitas).
    public const STATUS_MENGUNCI = ['disetujui'];
    // Status yang masih hidup (untuk cegah pengajuan kembar).
    public const STATUS_AKTIF    = ['diajukan', 'disetujui'];

    public function inventaris()
    {
        return $this->belongsTo(Inventaris::class);
    }

    public function tenagaPendidik()
    {
        return $this->belongsTo(TenagaPendidik::class);
    }

    public function disetujuiOleh()
    {
        return $this->belongsTo(User::class, 'disetujui_oleh');
    }
}
