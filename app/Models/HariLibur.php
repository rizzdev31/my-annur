<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class HariLibur extends Model
{
    protected $table = 'hari_libur';

    protected $fillable = [
        'nama',
        'tanggal',
        'tanggal_selesai',
        'tipe',
        'sumber',         // nasional | pesantren | darurat
        'is_aktif',       // bisa di-toggle (berguna untuk nasional)
        'is_darurat',
        'keterangan',
        'pengaruh_gaji',
        'absensi_terdampak',
        'dibatalkan_pada',
        'alasan_pembatalan',
        'dibatalkan_oleh',
        'dibuat_oleh',
    ];

    protected function casts(): array
    {
        return [
            'tanggal'          => 'date',
            'tanggal_selesai'  => 'date',
            'dibatalkan_pada'  => 'datetime',
            'pengaruh_gaji'    => 'boolean',
            'is_aktif'         => 'boolean',
            'is_darurat'       => 'boolean',
            'absensi_terdampak'=> 'integer',
        ];
    }

    // ─── Label ────────────────────────────────────────────────────────────────

    public function getSumberLabelAttribute(): string
    {
        return [
            'nasional'  => '🇮🇩 Nasional',
            'pesantren' => '🕌 Pesantren',
            'darurat'   => '🚨 Darurat',
        ][$this->sumber] ?? $this->sumber;
    }

    public function getDurasiHariAttribute(): int
    {
        if (!$this->tanggal_selesai || $this->tanggal_selesai->eq($this->tanggal)) {
            return 1;
        }
        return $this->tanggal->diffInDays($this->tanggal_selesai) + 1;
    }

    public function getIsDibatalkanAttribute(): bool
    {
        return $this->dibatalkan_pada !== null;
    }

    // ─── Relasi ───────────────────────────────────────────────────────────────

    public function dibuatOleh()
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    public function dibatalkanOleh()
    {
        return $this->belongsTo(User::class, 'dibatalkan_oleh');
    }

    // ─── Static Helpers ───────────────────────────────────────────────────────

    /**
     * Cek apakah tanggal tertentu adalah hari libur aktif.
     */
    public static function isLibur(string $tanggal): bool
    {
        return static::where('is_aktif', true)
            ->whereNull('dibatalkan_pada')
            ->where('tanggal', '<=', $tanggal)
            ->where(fn($q) =>
                $q->whereNull('tanggal_selesai')
                  ->orWhere('tanggal_selesai', '>=', $tanggal)
            )
            ->exists();
    }

    /**
     * Hitung jumlah hari libur aktif dalam rentang.
     * Dipakai PayrollCalculationService untuk hitung hari kerja.
     */
    public static function hitungHariLiburDalamRentang(string $mulai, string $selesai): int
    {
        $hariLibur = static::where('is_aktif', true)
            ->whereNull('dibatalkan_pada')
            ->where('tanggal', '<=', $selesai)
            ->where(fn($q) =>
                $q->whereNull('tanggal_selesai')
                  ->orWhere('tanggal_selesai', '>=', $mulai)
            )
            ->get();

        // Hitung total hari unik yang jatuh dalam rentang
        $tanggalLibur = collect();

        foreach ($hariLibur as $hl) {
            $start  = Carbon::parse(max($mulai,  $hl->tanggal->toDateString()));
            $end    = Carbon::parse(min($selesai, $hl->tanggal_selesai?->toDateString() ?? $hl->tanggal->toDateString()));

            $current = $start->copy();
            while ($current->lte($end)) {
                $tanggalLibur->push($current->toDateString());
                $current->addDay();
            }
        }

        return $tanggalLibur->unique()->count();
    }

    /**
     * Set tanggal (Y-m-d => true) libur aktif yang jatuh dalam rentang.
     * Dipakai untuk hitung hari kerja (lookup cepat per tanggal).
     */
    public static function tanggalSetDalamRentang(string $mulai, string $selesai): array
    {
        $rows = static::where('is_aktif', true)
            ->whereNull('dibatalkan_pada')
            ->where('tanggal', '<=', $selesai)
            ->where(fn($q) =>
                $q->whereNull('tanggal_selesai')
                  ->orWhere('tanggal_selesai', '>=', $mulai)
            )
            ->get();

        $set = [];
        foreach ($rows as $hl) {
            $start = Carbon::parse(max($mulai,  $hl->tanggal->toDateString()));
            $end   = Carbon::parse(min($selesai, $hl->tanggal_selesai?->toDateString() ?? $hl->tanggal->toDateString()));
            $c = $start->copy();
            while ($c->lte($end)) { $set[$c->toDateString()] = true; $c->addDay(); }
        }
        return $set;
    }

    /**
     * Daftar hari libur nasional Indonesia (pre-loaded untuk seed/import).
     * Update tiap tahun atau via fitur import.
     */
    public static function daftarNasional(int $tahun): array
    {
        return [
            ['nama' => 'Tahun Baru Masehi',             'tanggal' => "{$tahun}-01-01"],
            ['nama' => 'Hari Buruh Internasional',       'tanggal' => "{$tahun}-05-01"],
            ['nama' => 'Hari Lahir Pancasila',           'tanggal' => "{$tahun}-06-01"],
            ['nama' => 'Hari Kemerdekaan RI',            'tanggal' => "{$tahun}-08-17"],
            ['nama' => 'Hari Natal',                     'tanggal' => "{$tahun}-12-25"],
            ['nama' => 'Hari Kedua Natal',               'tanggal' => "{$tahun}-12-26"],
            // Hari keagamaan Islam (tanggal berubah tiap tahun — perlu update manual)
            ['nama' => 'Isra Miraj',                     'tanggal' => ''],
            ['nama' => 'Idul Fitri',                     'tanggal' => ''],
            ['nama' => 'Idul Fitri (Cuti Bersama)',      'tanggal' => ''],
            ['nama' => 'Idul Adha',                      'tanggal' => ''],
            ['nama' => 'Tahun Baru Islam',               'tanggal' => ''],
            ['nama' => 'Maulid Nabi Muhammad SAW',       'tanggal' => ''],
        ];
    }

    // ─── Scope ───────────────────────────────────────────────────────────────

    public function scopeAktif($query)
    {
        return $query->where('is_aktif', true)->whereNull('dibatalkan_pada');
    }

    public function scopeNasional($query)
    {
        return $query->where('sumber', 'nasional');
    }

    public function scopePesantren($query)
    {
        return $query->where('sumber', 'pesantren');
    }

    public function scopeDarurat($query)
    {
        return $query->where('sumber', 'darurat');
    }

    public function scopeBerpengaruhGaji($query)
    {
        return $query->where('pengaruh_gaji', true);
    }
}