<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

/**
 * Libur individu tenaga pendidik (khusus guru mukim).
 *
 * Menandai TANGGAL tertentu sebagai hari libur seorang guru, terpisah dari jam
 * kerja mingguan. Mendukung pola rolling & tukar libur. Efek:
 *   • absensi: hari itu tampil "Libur" (check-in tetap opsional),
 *   • auto-alfa: dilewati (tidak dialfa),
 *   • payroll & kinerja: tidak dihitung sebagai hari kerja → tidak memotong gaji.
 */
class LiburTendik extends Model
{
    protected $table = 'libur_tendik';

    protected $fillable = [
        'tenaga_pendidik_id',
        'tanggal',
        'tipe',
        'alasan',
        'ditukar_dengan_id',
        'ditukar_tanggal',
        'dibuat_oleh',
    ];

    protected function casts(): array
    {
        return [
            'tanggal'         => 'date',
            'ditukar_tanggal' => 'date',
        ];
    }

    // ─── Relasi ────────────────────────────────────────────────────────────────

    public function tenagaPendidik()
    {
        return $this->belongsTo(TenagaPendidik::class);
    }

    public function ditukarDengan()
    {
        return $this->belongsTo(TenagaPendidik::class, 'ditukar_dengan_id');
    }

    public function dibuatOleh()
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    // ─── Helper ────────────────────────────────────────────────────────────────

    /** Apakah TANGGAL ini adalah hari libur individu guru tsb. */
    public static function isLibur(int $tenagaPendidikId, string $tanggal): bool
    {
        return static::where('tenaga_pendidik_id', $tenagaPendidikId)
            ->whereDate('tanggal', $tanggal)
            ->exists();
    }

    /**
     * Set tanggal (Y-m-d => true) libur individu guru dalam rentang.
     * Untuk lookup cepat saat menghitung hari kerja / auto-alfa.
     */
    public static function tanggalSetUntuk(int $tenagaPendidikId, string $mulai, string $selesai): array
    {
        $rows = static::where('tenaga_pendidik_id', $tenagaPendidikId)
            ->whereBetween('tanggal', [$mulai, $selesai])
            ->pluck('tanggal');

        $set = [];
        foreach ($rows as $tgl) {
            $set[Carbon::parse($tgl)->toDateString()] = true;
        }
        return $set;
    }
}
