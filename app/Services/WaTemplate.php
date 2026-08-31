<?php

namespace App\Services;

use App\Models\SettingWa;

/**
 * Template pesan WhatsApp — body & nama "bot" diatur superadmin (tabel setting_wa).
 * Mendukung placeholder; semua dibungkus header (nama instansi) + salam + footer.
 */
class WaTemplate
{
    private static ?SettingWa $setting = null;

    private static function setting(): SettingWa
    {
        return self::$setting ??= SettingWa::get();
    }

    /** Bungkus isi dengan header (nama instansi) + salam + footer. */
    public static function bungkus(string $isi): string
    {
        $s = self::setting();
        $kepala = "🕌 *" . strtoupper($s->nama_bot) . "*\n━━━━━━━━━━━━━━━\n";
        $pembuka = $s->pakai_salam ? "Assalamu'alaikum Wr. Wb.\nYth. Bapak/Ibu Wali Santri,\n\n" : '';
        $kaki = $s->footer ? "\n\n_" . $s->footer . "_" : '';
        return $kepala . $pembuka . $isi . $kaki;
    }

    public static function controlling(string $nama, string $status, string $kegiatan, string $tgl, ?string $jam): string
    {
        $tpl = self::setting()->tpl_controlling ?: SettingWa::DEF_CONTROLLING;
        return self::bungkus(self::render($tpl, [
            '{nama}'     => $nama,
            '{status}'   => self::labelStatus($status),
            '{kegiatan}' => $kegiatan,
            '{tanggal}'  => $tgl,
            '{jam}'      => $jam ? ' · ' . substr($jam, 0, 5) : '',
        ]));
    }

    public static function mengajar(string $nama, string $status, string $pembelajaran, string $tgl): string
    {
        $tpl = self::setting()->tpl_mengajar ?: SettingWa::DEF_MENGAJAR;
        return self::bungkus(self::render($tpl, [
            '{nama}'         => $nama,
            '{status}'       => self::labelStatus($status),
            '{pembelajaran}' => $pembelajaran,
            '{tanggal}'      => $tgl,
        ]));
    }

    public static function eksekusi(string $jenis, string $nama, string $label, $poin, string $tgl, ?string $catatan): string
    {
        $s = self::setting();
        $tpl = match ($jenis) {
            'apresiasi' => $s->tpl_apresiasi   ?: SettingWa::DEF_APRESIASI,
            'konselor'  => $s->tpl_konselor    ?: SettingWa::DEF_KONSELOR,
            default     => $s->tpl_pelanggaran ?: SettingWa::DEF_PELANGGARAN,
        };
        return self::bungkus(self::render($tpl, [
            '{nama}'     => $nama,
            '{label}'    => $label,
            '{poin}'     => $poin !== null ? " (poin {$poin})" : '',
            '{tanggal}'  => $tgl,
            '{catatan}'  => $catatan ? "\n📝 Catatan   : {$catatan}" : '',
        ]));
    }

    /** Notifikasi kesehatan santri (Sembuh / Pengecekan Hari 1–3 / Darurat). */
    public static function kesehatan(string $nama, string $penyakit, string $tipe, ?int $hari): string
    {
        $kepala = "🏥 *Info Kesehatan Santri*\n\n"
            . "👤 Nama       : *{$nama}*\n"
            . "🩺 Kondisi    : {$penyakit}\n";
        $isi = match (true) {
            $tipe === 'sembuh' =>
                $kepala . "✅ Status     : *SEMBUH*\n\nAlhamdulillah, ananda telah pulih.",
            $tipe === 'darurat' =>
                $kepala . "🚨 Status     : *DARURAT*\n\nAnanda dalam kondisi darurat dan *diizinkan pulang* — mohon segera dijemput/dihubungi.",
            $tipe === 'pengecekan' && $hari !== null && $hari >= 3 =>
                $kepala . "🏠 Status     : *DIIZINKAN PULANG*\n\nSetelah {$hari} hari pengecekan, ananda diizinkan pulang untuk pemulihan.",
            default =>
                $kepala . "🔎 Status     : *Masih dalam pengecekan* (hari ke-" . ($hari ?? 1) . ")\n\nAnanda masih dipantau di poskestren. Kami kabari perkembangannya.",
        };
        return self::bungkus($isi);
    }

    /** Izin santri disetujui. */
    public static function izin(string $nama, string $jenisLabel, string $alasan, string $tglMulai, string $tglSelesai): string
    {
        $isi = "Kami informasikan izin ananda telah *DISETUJUI*:\n\n"
            . "👤 Nama     : *{$nama}*\n"
            . "📋 Jenis     : {$jenisLabel}\n"
            . "📝 Alasan   : {$alasan}\n"
            . "🗓️ Tanggal  : {$tglMulai} s/d {$tglSelesai}";
        return self::bungkus($isi);
    }

    /** Render untuk PREVIEW admin (data contoh) — dipakai controller. */
    public static function preview(string $jenis, string $tpl): string
    {
        $contoh = [
            '{nama}' => 'Muhammad Baydowi', '{status}' => self::labelStatus('telat'),
            '{kegiatan}' => 'Sholat Subuh Berjamaah', '{pembelajaran}' => 'Tahfidz Quran',
            '{tanggal}' => '01 Jul 2026', '{jam}' => ' · 05:18',
            '{label}' => 'Terlambat Masuk Asrama', '{poin}' => ' (poin 2)',
            '{catatan}' => "\n📝 Catatan   : Contoh catatan.",
        ];
        return self::bungkus(self::render($tpl, $contoh));
    }

    private static function render(string $tpl, array $repl): string
    {
        return strtr($tpl, $repl);
    }

    private static function labelStatus(string $s): string
    {
        return [
            'hadir' => 'HADIR ✅', 'telat' => 'TERLAMBAT ⏰',
            'izin'  => 'IZIN 📝',  'sakit' => 'SAKIT 🤒',
            'alpha' => 'TIDAK HADIR ❌',
        ][$s] ?? strtoupper($s);
    }
}
