<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Pengecualian/penambahan peserta kegiatan wajib di luar aturan sasaran.
 * Dipakai untuk kasus nyata: sebagian guru mukim ikut kegiatan guru non-mukim,
 * dan sebaliknya ada guru yang tidak seharusnya diwajibkan.
 */
class KegiatanPentingPeserta extends Model
{
    protected $table = 'kegiatan_penting_peserta';

    protected $fillable = [
        'kegiatan_penting_id', 'tenaga_pendidik_id', 'mode', 'catatan', 'dibuat_oleh',
    ];

    public const MODE_TAMBAHAN     = 'tambahan';
    public const MODE_DIKECUALIKAN = 'dikecualikan';

    public function kegiatan()
    {
        return $this->belongsTo(KegiatanPenting::class, 'kegiatan_penting_id');
    }

    public function tenagaPendidik()
    {
        return $this->belongsTo(TenagaPendidik::class);
    }
}
