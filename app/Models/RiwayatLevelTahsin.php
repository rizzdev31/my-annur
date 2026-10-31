<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris = satu perubahan level tahsin, beserta sebabnya.
 * Ditulis HANYA lewat Santri::catatLevelTahsin() agar sebabnya tidak pernah
 * kosong dan nilai lamanya tidak pernah hilang.
 */
class RiwayatLevelTahsin extends Model
{
    protected $table = 'riwayat_level_tahsin';

    protected $fillable = [
        'santri_id', 'level_dari', 'level_ke', 'sebab',
        'kelas_id', 'kelas_nama', 'catatan', 'oleh',
    ];

    protected function casts(): array
    {
        return ['level_dari' => 'integer', 'level_ke' => 'integer'];
    }

    public const SEBAB = ['penempatan', 'naik_kelas', 'tasnif', 'manual'];

    public function santri(): BelongsTo
    {
        return $this->belongsTo(Santri::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'oleh');
    }

    public function getLabelSebabAttribute(): string
    {
        return match ($this->sebab) {
            'penempatan' => 'Penempatan awal',
            'naik_kelas' => 'Naik kelas tahsin',
            'tasnif'     => 'Lulus tasnif',
            'manual'     => 'Diubah admin',
            default      => ucfirst((string) $this->sebab),
        };
    }
}
