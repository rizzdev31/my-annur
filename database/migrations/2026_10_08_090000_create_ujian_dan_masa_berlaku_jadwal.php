<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * UJIAN SEKOLAH — pembelajaran diganti sesi ujian berpenjaga.
 *
 * KEPUTUSAN STRUKTURAL: satu sesi ujian diwujudkan sebagai BARIS
 * `jadwal_mengajar` yang masa berlakunya hanya satu hari, dengan guru =
 * PENJAGA dan mapel = mata ujian. Dengan begitu seluruh mesin yang sudah ada
 * ikut bekerja tanpa ditulis ulang: absensi mengajar, absensi santri, laporan
 * kehadiran, papan piket, monitoring, kinerja, dan — yang paling penting —
 * INVAL (`digantikan_oleh` + PenggantiMengajarService) untuk penjaga yang
 * berhalangan.
 *
 * Harganya: `jadwal_mengajar` tadinya mingguan tanpa masa berlaku (hanya ada
 * akal-akalan `created_at <= tanggal`). Tanpa masa berlaku, jadwal ujian hari
 * Senin akan muncul lagi setiap Senin dan memicu "sesi tidak terlaksana" palsu.
 * Karena itu kolom `berlaku_mulai`/`berlaku_selesai` ditambahkan dan seluruh
 * pembaca "jadwal pada tanggal X" dialihkan ke scope JadwalMengajar::berlakuPada().
 *
 * Vakasi: penjaga dibayar PER SESI (bukan per JP), dan bila sesinya di-inval,
 * yang dibayar adalah pengganti dengan nominal vakasi penjaga yang sama —
 * karena itu sesi ujian dikecualikan dari vakasi mengajar per-JP.
 */
return new class extends Migration
{
    private const DETAIL_LAMA = "'gaji_pokok','vakasi_absen','vakasi_mengajar','vakasi_tugas_jabatan',"
        . "'vakasi_tugas_tambahan','vakasi_peserta_kegiatan','vakasi_lembur','tunjangan',"
        . "'potongan_terlambat','potongan_alfa','potongan_bpjs','potongan_lain',"
        . "'penyesuaian_liburan','lainnya','vakasi_piket','vakasi_ekstrakurikuler','potongan_guru'";

    private const DETAIL_BARU = self::DETAIL_LAMA . ",'vakasi_jaga_ujian'";

    private const VAKASI_LAMA = "'absen_harian','absen_mengajar','tugas_jabatan','tugas_tambahan',"
        . "'lembur','piket','tasmi','tasnif','ekstrakurikuler'";

    private const VAKASI_BARU = self::VAKASI_LAMA . ",'jaga_ujian'";

    public function up(): void
    {
        // ── 1. Masa berlaku jadwal ────────────────────────────────────────────
        Schema::table('jadwal_mengajar', function (Blueprint $t) {
            $t->date('berlaku_mulai')->nullable()->after('is_aktif')
              ->comment('Jadwal berlaku dari tanggal ini; null = sejak dibuat');
            $t->date('berlaku_selesai')->nullable()->after('berlaku_mulai')
              ->comment('Jadwal berlaku sampai tanggal ini; null = tanpa batas');
            $t->index(['berlaku_mulai', 'berlaku_selesai'], 'jadwal_masa_berlaku');
        });

        // ── 2. Paket ujian ────────────────────────────────────────────────────
        Schema::create('ujian', function (Blueprint $t) {
            $t->id();
            $t->string('nama', 150);                       // mis. "UAS Semester 1"
            $t->date('tanggal_mulai');
            $t->date('tanggal_selesai')->nullable();
            $t->text('keterangan')->nullable();
            // Pematian pembelajaran reguler diserahkan ke fitur libur pembelajaran.
            $t->foreignId('libur_pembelajaran_id')->nullable()
              ->constrained('libur_pembelajaran')->nullOnDelete();
            $t->boolean('is_aktif')->default(true);
            $t->timestamp('dibatalkan_pada')->nullable();
            $t->string('alasan_pembatalan')->nullable();
            $t->foreignId('dibatalkan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();

            $t->index(['tanggal_mulai', 'is_aktif']);
        });

        // ── 3. Sesi ujian (satu kelas, satu penjaga) ──────────────────────────
        Schema::create('ujian_sesi', function (Blueprint $t) {
            $t->id();
            $t->foreignId('ujian_id')->constrained('ujian')->cascadeOnDelete();
            $t->date('tanggal');
            $t->time('jam_mulai');
            $t->time('jam_selesai');
            $t->foreignId('kelas_id')->constrained('kelas')->cascadeOnDelete();
            $t->foreignId('mata_pelajaran_id')->constrained('mata_pelajaran');
            $t->string('ruangan', 50)->nullable();
            $t->integer('jumlah_jp')->default(1)->comment('Diturunkan dari durasi; vakasi tetap per sesi');

            // Penjaga boleh belum ditunjuk saat jadwal ujian baru disusun.
            $t->foreignId('penjaga_id')->nullable()->constrained('tenaga_pendidik')->nullOnDelete();

            // Snapshot nominal saat ditunjuk — tarif boleh berubah tanpa mengubah
            // slip yang sudah terbit (pola yang sama dengan vakasi ekskul & piket).
            $t->decimal('nominal_vakasi', 12, 2)->default(0);
            $t->boolean('vakasi_dibayar')->default(false);
            $t->unsignedBigInteger('dibayar_periode_id')->nullable();

            $t->string('catatan')->nullable();
            $t->timestamps();

            // Satu kelas tidak boleh punya dua sesi ujian pada jam yang sama.
            $t->unique(['kelas_id', 'tanggal', 'jam_mulai'], 'ujian_kelas_jam_unik');
            $t->index(['tanggal', 'penjaga_id']);
        });

        // Penanda dua arah dihindari: cukup jadwal yang menunjuk sesinya.
        Schema::table('jadwal_mengajar', function (Blueprint $t) {
            $t->foreignId('ujian_sesi_id')->nullable()->after('berlaku_selesai')
              ->constrained('ujian_sesi')->nullOnDelete()
              ->comment('Jadwal ini adalah sesi ujian, bukan pembelajaran reguler');
        });

        // ── 4. Jalur bayar penjaga ujian ──────────────────────────────────────
        DB::statement("ALTER TABLE setting_vakasi MODIFY COLUMN tipe_aktivitas ENUM(" . self::VAKASI_BARU . ") NOT NULL");
        DB::statement("ALTER TABLE detail_penggajian MODIFY tipe ENUM(" . self::DETAIL_BARU . ") NOT NULL");

        Schema::table('penggajian', function (Blueprint $t) {
            $t->decimal('vakasi_jaga_ujian', 12, 2)->default(0)->after('vakasi_ekstrakurikuler');
        });
    }

    public function down(): void
    {
        Schema::table('penggajian', fn (Blueprint $t) => $t->dropColumn('vakasi_jaga_ujian'));

        DB::statement("UPDATE detail_penggajian SET tipe = 'lainnya' WHERE tipe = 'vakasi_jaga_ujian'");
        DB::statement("ALTER TABLE detail_penggajian MODIFY tipe ENUM(" . self::DETAIL_LAMA . ") NOT NULL");
        DB::table('setting_vakasi')->where('tipe_aktivitas', 'jaga_ujian')->delete();
        DB::statement("ALTER TABLE setting_vakasi MODIFY COLUMN tipe_aktivitas ENUM(" . self::VAKASI_LAMA . ") NOT NULL");

        // Jadwal bentukan ujian ikut terbuang bersama sesinya.
        $idJadwal = DB::table('jadwal_mengajar')->whereNotNull('ujian_sesi_id')->pluck('id');
        if ($idJadwal->isNotEmpty()) {
            DB::table('absensi_mengajar')->whereIn('jadwal_mengajar_id', $idJadwal)->delete();
            DB::table('jadwal_mengajar')->whereIn('id', $idJadwal)->delete();
        }

        Schema::table('jadwal_mengajar', function (Blueprint $t) {
            $t->dropForeign(['ujian_sesi_id']);
            $t->dropColumn('ujian_sesi_id');
        });
        Schema::dropIfExists('ujian_sesi');
        Schema::dropIfExists('ujian');
        Schema::table('jadwal_mengajar', function (Blueprint $t) {
            $t->dropIndex('jadwal_masa_berlaku');
            $t->dropColumn(['berlaku_mulai', 'berlaku_selesai']);
        });
    }
};
