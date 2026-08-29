<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Pengaturan template Bot WhatsApp (singleton). Diedit superadmin.
 * Body template mendukung placeholder; lihat DEFAULT_* di bawah.
 */
class SettingWa extends Model
{
    protected $table = 'setting_wa';

    protected $fillable = [
        'nama_bot', 'pakai_salam', 'footer',
        'tpl_controlling', 'tpl_mengajar', 'tpl_pelanggaran', 'tpl_apresiasi', 'tpl_konselor',
    ];

    protected function casts(): array
    {
        return ['pakai_salam' => 'boolean'];
    }

    // ── Template default (dipakai bila kolom kosong) ──
    public const DEF_FOOTER = 'Pesan otomatis Sistem Informasi Santri — mohon tidak membalas.';

    public const DEF_CONTROLLING =
        "Kami informasikan kehadiran ananda pada kegiatan:\n\n"
        . "👤 Nama       : *{nama}*\n"
        . "📌 Kegiatan : *{kegiatan}*\n"
        . "🗓️ Waktu      : {tanggal}{jam}\n"
        . "📊 Status     : *{status}*";

    public const DEF_MENGAJAR =
        "Kami informasikan kehadiran ananda pada pembelajaran:\n\n"
        . "👤 Nama             : *{nama}*\n"
        . "📚 Pembelajaran : *{pembelajaran}*\n"
        . "🗓️ Tanggal          : {tanggal}\n"
        . "📊 Status            : *{status}*";

    public const DEF_PELANGGARAN =
        "*Catatan Pelanggaran*\n\n"
        . "👤 Nama       : *{nama}*\n"
        . "⚠️ Pelanggaran : *{label}*{poin}\n"
        . "🗓️ Tanggal   : {tanggal}{catatan}\n\n"
        . "Mohon perhatian & bimbingan Bapak/Ibu di rumah.";

    public const DEF_APRESIASI =
        "*Apresiasi Santri* 🎉\n\n"
        . "👤 Nama       : *{nama}*\n"
        . "🏅 Apresiasi : *{label}*{poin}\n"
        . "🗓️ Tanggal   : {tanggal}{catatan}\n\n"
        . "Selamat & terima kasih atas dukungan Bapak/Ibu. Semoga ananda terus berprestasi.";

    public const DEF_KONSELOR =
        "*Informasi Konseling*\n\n"
        . "👤 Nama       : *{nama}*\n"
        . "🧭 Layanan  : *{label}*\n"
        . "🗓️ Tanggal   : {tanggal}{catatan}\n\n"
        . "Ananda mendapat pendampingan konseling. Mohon kerja samanya.";

    /** Singleton — ambil baris pertama atau buat default. */
    public static function get(): self
    {
        return static::firstOrCreate([], [
            'nama_bot'        => (string) config('fonnte.nama', 'PPM An-Nur'),
            'pakai_salam'     => true,
            'footer'          => self::DEF_FOOTER,
            'tpl_controlling' => self::DEF_CONTROLLING,
            'tpl_mengajar'    => self::DEF_MENGAJAR,
            'tpl_pelanggaran' => self::DEF_PELANGGARAN,
            'tpl_apresiasi'   => self::DEF_APRESIASI,
            'tpl_konselor'    => self::DEF_KONSELOR,
        ]);
    }
}
