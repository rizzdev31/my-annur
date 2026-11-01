<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * LIBUR PEKANAN GURU ASRAMA — diajukan sendiri lewat Pengajuan Izin.
 *
 * Guru asrama bekerja Senin–Ahad dan liburnya dipilih sendiri antara Jumat
 * atau Ahad, satu hari per pekan. Mekanisme liburnya sudah ada
 * (`libur_tendik`, dihormati auto-alfa, kinerja, payroll dan kegiatan piket),
 * yang belum ada adalah PENGAJUAN dari sisi guru: selama ini hanya admin yang
 * bisa menetapkannya, dan hasilnya terukur 1 Nov 2026 hanya 4 dari 15 guru
 * mukim punya libur tercatat — 11 lainnya terhitung bekerja 7 hari sepekan.
 *
 * Tiga hal yang ditambahkan:
 *
 * 1. `tenaga_pendidik.boleh_libur_pekanan` — penanda EKSPLISIT siapa yang
 *    berhak. Sengaja kolom sendiri, bukan diturunkan dari `is_mukim` atau dari
 *    setting jam kerja, karena terukur keduanya tidak tepat: "Jam Kerja Asrama"
 *    dipakai 11 guru yang 2 di antaranya non-mukim, sementara 8 dari 15 guru
 *    mukim justru memakai jam kerja perorangan. Menebak dari salah satunya
 *    membuat ada guru kehilangan hak liburnya — dan libur mengurangi hari
 *    kerja, jadi itu menyentuh gaji.
 *
 * 2. Tiga sakelar di `setting_jenis_pengajuan` supaya aturannya berada di DATA,
 *    bukan ditanam di kode: jenis mana yang menjadi libur, hari apa saja yang
 *    boleh dipilih, dan berapa kali per pekan.
 *
 * 3. Nilai `pekanan` pada `libur_tendik.tipe`, agar libur hasil pengajuan guru
 *    bisa dibedakan dari `rutin` (generator admin), `manual`, dan `tukar`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('tenaga_pendidik', 'boleh_libur_pekanan')) {
            Schema::table('tenaga_pendidik', function (Blueprint $t) {
                $t->boolean('boleh_libur_pekanan')->default(false)->after('is_mukim');
            });
        }

        Schema::table('setting_jenis_pengajuan', function (Blueprint $t) {
            if (!Schema::hasColumn('setting_jenis_pengajuan', 'jadi_libur_tendik')) {
                // true → saat disetujui, menulis baris libur_tendik dan TIDAK
                // menandai absensi 'izin': harinya benar-benar bukan hari kerja,
                // bukan hari kerja yang ditinggalkan.
                $t->boolean('jadi_libur_tendik')->default(false)->after('buat_tugas_tambahan');
            }
            if (!Schema::hasColumn('setting_jenis_pengajuan', 'hari_diizinkan')) {
                // Array nomor hari ISO (1=Senin … 7=Ahad). null = hari apa pun.
                $t->json('hari_diizinkan')->nullable()->after('jadi_libur_tendik');
            }
            if (!Schema::hasColumn('setting_jenis_pengajuan', 'maks_per_pekan')) {
                $t->unsignedTinyInteger('maks_per_pekan')->nullable()->after('hari_diizinkan');
            }
        });

        // libur_tendik.tipe: tambah 'pekanan'
        $kolom = collect(DB::select("SHOW COLUMNS FROM libur_tendik WHERE Field = 'tipe'"))->first();
        if ($kolom && !str_contains((string) $kolom->Type, 'pekanan')) {
            DB::statement("ALTER TABLE libur_tendik MODIFY COLUMN tipe
                ENUM('rutin','manual','tukar','pekanan') NOT NULL DEFAULT 'manual'");
        }

        // Jejak pengajuan asalnya, supaya pembatalan izin bisa mencabut liburnya.
        if (!Schema::hasColumn('libur_tendik', 'pengajuan_izin_id')) {
            Schema::table('libur_tendik', function (Blueprint $t) {
                $t->foreignId('pengajuan_izin_id')->nullable()->after('tipe')
                    ->constrained('pengajuan_izin')->nullOnDelete();
            });
        }

        // ── Jenis pengajuannya ───────────────────────────────────────────────
        // Langsung disetujui (keputusan pengguna: paling simpel), dibatasi satu
        // hari per pekan dan hanya Jumat/Ahad. Tidak memotong gaji dan tanpa
        // kuota tahunan — ini hak libur pekanan, bukan izin meninggalkan kerja.
        $ada = DB::table('setting_jenis_pengajuan')->where('kode', 'LIBUR_ASRAMA')->first();
        $isi = [
            'nama'                          => 'Libur Pekanan Asrama',
            'kode'                          => 'LIBUR_ASRAMA',
            'kategori'                      => 'izin',
            'deskripsi'                     => 'Libur pekanan guru asrama — pilih Jumat atau Ahad, '
                . 'satu hari per pekan. Hari itu menjadi libur: tidak perlu absen, '
                . 'pembelajarannya netral, dan kinerja tidak terpengaruh.',
            'max_hari_per_pengajuan'        => 1,
            'kuota_per_tahun'               => null,
            'min_hari_pengajuan_sebelumnya' => 0,
            'butuh_dokumen'                 => false,
            'auto_approve'                  => true,
            'pengaruh_gaji'                 => 'tidak_potong',
            'persen_potongan'               => 0,
            'update_status_kepegawaian'     => false,
            'buat_tugas_tambahan'           => false,
            'jadi_libur_tendik'             => true,
            'hari_diizinkan'                => json_encode([5, 7]),   // Jumat, Ahad
            'maks_per_pekan'                => 1,
            'is_aktif'                      => true,
            'updated_at'                    => now(),
        ];

        if ($ada) {
            DB::table('setting_jenis_pengajuan')->where('id', $ada->id)->update($isi);
        } else {
            DB::table('setting_jenis_pengajuan')->insert($isi + ['created_at' => now()]);
        }

        // ── Penerima hak awal ────────────────────────────────────────────────
        // Guru mukim ATAU pemakai setting jam kerja yang namanya memuat
        // "asrama" — gabungan keduanya, karena masing-masing melewatkan orang.
        // Selanjutnya sepenuhnya dikendalikan admin lewat centang per guru.
        $jamAsrama = DB::table('setting_jam_kerja')
            ->where('nama', 'like', '%asrama%')->pluck('id');

        DB::table('tenaga_pendidik')
            ->where('is_aktif', true)
            ->where(function ($q) use ($jamAsrama) {
                $q->where('is_mukim', true);
                if ($jamAsrama->isNotEmpty()) {
                    $q->orWhereIn('setting_jam_kerja_id', $jamAsrama);
                }
            })
            ->update(['boleh_libur_pekanan' => true]);
    }

    public function down(): void
    {
        DB::table('setting_jenis_pengajuan')->where('kode', 'LIBUR_ASRAMA')
            ->update(['is_aktif' => false]);

        if (Schema::hasColumn('libur_tendik', 'pengajuan_izin_id')) {
            Schema::table('libur_tendik', function (Blueprint $t) {
                $t->dropConstrainedForeignId('pengajuan_izin_id');
            });
        }

        Schema::table('setting_jenis_pengajuan', function (Blueprint $t) {
            foreach (['maks_per_pekan', 'hari_diizinkan', 'jadi_libur_tendik'] as $k) {
                if (Schema::hasColumn('setting_jenis_pengajuan', $k)) $t->dropColumn($k);
            }
        });

        if (Schema::hasColumn('tenaga_pendidik', 'boleh_libur_pekanan')) {
            Schema::table('tenaga_pendidik', function (Blueprint $t) {
                $t->dropColumn('boleh_libur_pekanan');
            });
        }
    }
};
