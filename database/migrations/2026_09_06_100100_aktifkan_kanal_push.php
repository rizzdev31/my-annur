<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Nyalakan kanal 'push' HANYA untuk event yang terikat waktu.
 *
 * Sengaja tidak semua event: bila 15 event berbunyi di HP, guru akan
 * terganggu lalu mematikan izin notifikasi sama sekali — dan kanalnya hilang
 * untuk selamanya. Sisanya cukup lonceng in-app. Admin tetap bisa mengubah
 * pilihan ini lewat Setting Notifikasi.
 */
return new class extends Migration
{
    private array $push = [
        'absensi.reminder_masuk',   // sebelum jam masuk
        'mengajar.reminder',        // jurnal belum diisi
        'kegiatan.reminder',        // kegiatan wajib akan dimulai
        'pengganti.ditunjuk',       // mendadak diminta menggantikan
        'izin.diputuskan',          // hasil pengajuan izin
    ];

    public function up(): void
    {
        if (!Schema::hasTable('setting_notifikasi')) return;

        foreach (DB::table('setting_notifikasi')->whereIn('event_kode', $this->push)->get() as $row) {
            $kanal = json_decode((string) $row->kanal, true) ?: [];
            $kanal['push'] = true;

            DB::table('setting_notifikasi')->where('id', $row->id)
                ->update(['kanal' => json_encode($kanal), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('setting_notifikasi')) return;

        foreach (DB::table('setting_notifikasi')->whereIn('event_kode', $this->push)->get() as $row) {
            $kanal = json_decode((string) $row->kanal, true) ?: [];
            unset($kanal['push']);

            DB::table('setting_notifikasi')->where('id', $row->id)
                ->update(['kanal' => json_encode($kanal), 'updated_at' => now()]);
        }
    }
};
