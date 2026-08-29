<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Delegasi peran petugas (ditunjuk superadmin): perizinan | kesehatan.
 * Dipakai Perizinan Santri & Smart Health. "Salah satu petugas setuju → sah".
 */
class PetugasPeran extends Model
{
    protected $table = 'petugas_peran';

    protected $fillable = ['tenaga_pendidik_id', 'peran', 'is_aktif', 'ditunjuk_oleh'];

    protected function casts(): array
    {
        return ['is_aktif' => 'boolean'];
    }

    public function tenagaPendidik()
    {
        return $this->belongsTo(TenagaPendidik::class);
    }

    /** Apakah seorang tendik adalah petugas aktif untuk peran tertentu. */
    public static function isPetugas(int $tenagaPendidikId, string $peran): bool
    {
        return static::where('tenaga_pendidik_id', $tenagaPendidikId)
            ->where('peran', $peran)->where('is_aktif', true)->exists();
    }
}
