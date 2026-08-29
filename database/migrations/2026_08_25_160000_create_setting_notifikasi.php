<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sistem notifikasi terkonfigurasi (lihat docs/PRD-Notifikasi.md).
 * - setting_notifikasi: katalog event + override per event (aktif/penerima/kanal/reminder/eskalasi).
 * - notifikasi: +event_kode, +prioritas.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('setting_notifikasi')) {
            Schema::create('setting_notifikasi', function (Blueprint $table) {
                $table->id();
                $table->string('event_kode', 60)->unique();
                $table->string('nama');
                $table->string('kategori', 40);
                $table->string('deskripsi')->nullable();
                $table->boolean('wajib')->default(false);
                $table->boolean('aktif')->default(true);
                $table->json('penerima')->nullable();   // ['guru','admin','pimpinan','penguji']
                $table->json('kanal')->nullable();       // {in_app:true, wa:false, push:false}
                $table->json('reminder')->nullable();    // {sebelum_menit, ulang_menit, batas_menit}
                $table->json('eskalasi')->nullable();    // {setelah_menit, ke:['admin']}
                $table->unsignedSmallInteger('maks_per_hari')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('notifikasi')) {
            Schema::table('notifikasi', function (Blueprint $table) {
                if (!Schema::hasColumn('notifikasi', 'event_kode')) {
                    $table->string('event_kode', 60)->nullable()->after('tipe');
                }
                if (!Schema::hasColumn('notifikasi', 'prioritas')) {
                    $table->string('prioritas', 12)->default('normal')->after('event_kode'); // rendah|normal|tinggi
                }
            });
        }

        $this->seedKatalog();
    }

    /** Seed katalog event (idempotent — hanya isi bila kode belum ada). */
    private function seedKatalog(): void
    {
        $now = now();
        $events = [
            ['absensi.reminder_masuk','Pengingat Absen Masuk','Absensi','Ingatkan guru absen masuk menjelang jadwal',true,['guru'],['in_app'=>true],['sebelum_menit'=>15,'ulang_menit'=>0,'batas_menit'=>30],null,3],
            ['absensi.reminder_pulang','Pengingat Absen Pulang','Absensi','Ingatkan guru absen pulang setelah jam pulang',true,['guru'],['in_app'=>true],['sebelum_menit'=>0,'ulang_menit'=>15,'batas_menit'=>45],['setelah_menit'=>60,'ke'=>['admin']],3],
            ['absensi.alfa','Alfa Tercatat','Absensi','Guru tidak absen s/d shift berakhir (auto-alfa)',true,['guru','admin'],['in_app'=>true],null,null,1],
            ['mengajar.reminder','Pengingat Absen Mengajar','Mengajar','Absen mengajar belum diisi & sudah melewati jadwal',true,['guru'],['in_app'=>true],['sebelum_menit'=>0,'ulang_menit'=>20,'batas_menit'=>60],['setelah_menit'=>90,'ke'=>['admin']],4],
            ['izin.diajukan','Pengajuan Izin Baru','Pengajuan','Guru mengajukan izin — perlu ditinjau',true,['admin'],['in_app'=>true],null,null,null],
            ['izin.diputuskan','Keputusan Izin','Pengajuan','Izin disetujui/ditolak',true,['guru'],['in_app'=>true],null,null,null],
            ['pengganti.ditunjuk','Ditunjuk Guru Pengganti','Mengajar','Guru ditunjuk menggantikan mengajar',true,['guru'],['in_app'=>true],null,null,null],
            ['tugas.baru','Tugas Baru','Tugas','Guru mendapat tugas baru',true,['guru'],['in_app'=>true],null,null,null],
            ['penggajian.terbit','Slip Gaji Terbit','Penggajian','Slip gaji periode diterbitkan',false,['guru'],['in_app'=>true],null,null,null],
            ['kinerja.rendah','Kinerja Perlu Perhatian','Kinerja','Skor kinerja di bawah ambang',false,['guru','pimpinan'],['in_app'=>true],null,null,null],
            ['pengumuman.umum','Pengumuman','Umum','Broadcast pengumuman manual',false,['guru'],['in_app'=>true],null,null,null],
        ];

        foreach ($events as $e) {
            if (DB::table('setting_notifikasi')->where('event_kode', $e[0])->exists()) continue;
            DB::table('setting_notifikasi')->insert([
                'event_kode' => $e[0], 'nama' => $e[1], 'kategori' => $e[2], 'deskripsi' => $e[3],
                'wajib' => $e[4], 'aktif' => true,
                'penerima' => json_encode($e[5]), 'kanal' => json_encode($e[6]),
                'reminder' => $e[7] ? json_encode($e[7]) : null,
                'eskalasi' => $e[8] ? json_encode($e[8]) : null,
                'maks_per_hari' => $e[9],
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('notifikasi')) {
            Schema::table('notifikasi', function (Blueprint $table) {
                if (Schema::hasColumn('notifikasi', 'event_kode')) $table->dropColumn('event_kode');
                if (Schema::hasColumn('notifikasi', 'prioritas'))  $table->dropColumn('prioritas');
            });
        }
        Schema::dropIfExists('setting_notifikasi');
    }
};
