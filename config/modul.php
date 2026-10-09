<?php

/*
|--------------------------------------------------------------------------
| Daftar MODUL (granular) — RBAC Web Admin
|--------------------------------------------------------------------------
| Modul = unit fitur nyata yang terikat kode/route. TIDAK dibuat lewat UI.
| Peran (tabel DB) memilih modul-modul ini (satu-satu) dan diberikan ke akun.
|
| Tiap modul:
|   - nama     : label tampilan
|   - kategori : grup tampilan di UI Kelola Peran
|   - beranda  : nama route utama (untuk landing / tautan cepat)
|   - prefix   : daftar prefix NAMA route yang termasuk modul ini
|
| Batas prefix aman: dicocokkan sbg `nama === prefix` ATAU `nama` diawali
| `prefix.'.'` — jadi 'tahsin' TIDAK cocok 'tahsin-monitoring'.
|
| Route yang TIDAK cocok modul mana pun → hanya super_admin (fail-safe).
| Catatan kompatibilitas: kode kasar lama (smart_education, penggajian, tugas,
| absensi) di-expand ke kode granular via migration
| 2026_08_24_*_expand_peran_modul_granular.
*/

return [
    'daftar' => [

        // ── Absensi & Kehadiran ──────────────────────────────────────────────
        'absensi' => [
            'nama'     => 'Absensi & Koreksi',
            'kategori' => 'Absensi & Kehadiran',
            'beranda'  => 'admin.smart-payroll.absensi.harian',
            'prefix'   => ['admin.smart-payroll.absensi'],
        ],
        'monitoring' => [
            'nama'     => 'Monitoring Harian',
            'kategori' => 'Absensi & Kehadiran',
            'beranda'  => 'admin.smart-payroll.monitoring.index',
            'prefix'   => ['admin.smart-payroll.monitoring'],
        ],
        // Penunjukan pengawas: memberi guru/pimpinan hak memantau guru lain di PWA.
        // Sensitif (membuka data pribadi rekan) → berikan hanya ke peran tepercaya.
        'pengawas' => [
            'nama'     => 'Pengawas Monitoring (PWA)',
            'kategori' => 'Absensi & Kehadiran',
            'beranda'  => 'admin.pengawas.index',
            'prefix'   => ['admin.pengawas'],
        ],

        // ── Kinerja & Tugas ──────────────────────────────────────────────────
        'kinerja' => [
            'nama'     => 'Kinerja',
            'kategori' => 'Kinerja & Tugas',
            'beranda'  => 'admin.smart-payroll.kinerja.index',
            'prefix'   => ['admin.smart-payroll.kinerja'],
        ],
        'tugas_jabatan' => [
            'nama'     => 'Tugas Jabatan',
            'kategori' => 'Kinerja & Tugas',
            'beranda'  => 'admin.smart-payroll.tugas-jabatan.index',
            'prefix'   => ['admin.smart-payroll.tugas-jabatan'],
        ],
        'tugas_tambahan' => [
            'nama'     => 'Tugas Tambahan',
            'kategori' => 'Kinerja & Tugas',
            'beranda'  => 'admin.smart-payroll.tugas-tambahan.index',
            // 'penugasan' = aksi per penerima (verifikasi, vakasi, keputusan tenggat)
            'prefix'   => ['admin.smart-payroll.tugas-tambahan', 'admin.smart-payroll.penugasan'],
        ],
        'kegiatan_penting' => [
            'nama'     => 'Kegiatan Wajib Guru',
            'kategori' => 'Kinerja & Tugas',
            'beranda'  => 'admin.smart-payroll.kegiatan-penting.index',
            'prefix'   => ['admin.smart-payroll.kegiatan-penting'],
        ],
        'absensi_kegiatan' => [
            'nama'     => 'Absensi Kegiatan',
            'kategori' => 'Kinerja & Tugas',
            'beranda'  => 'admin.smart-payroll.absensi-kegiatan.index',
            'prefix'   => ['admin.smart-payroll.absensi-kegiatan'],
        ],
        'lembur' => [
            'nama'     => 'Lembur',
            'kategori' => 'Kinerja & Tugas',
            'beranda'  => 'admin.smart-payroll.lembur.index',
            'prefix'   => ['admin.smart-payroll.lembur'],
        ],

        // ── Pengajuan ────────────────────────────────────────────────────────
        'pengajuan_izin' => [
            'nama'     => 'Pengajuan Izin Guru',
            'kategori' => 'Pengajuan',
            'beranda'  => 'admin.smart-payroll.pengajuan-izin.index',
            'prefix'   => ['admin.smart-payroll.pengajuan-izin'],
        ],

        // ── Penggajian & Laporan ─────────────────────────────────────────────
        'gaji_periode' => [
            'nama'     => 'Periode Gaji',
            'kategori' => 'Penggajian & Laporan',
            'beranda'  => 'admin.smart-payroll.periode.index',
            'prefix'   => ['admin.smart-payroll.periode'],
        ],
        'gaji_data' => [
            'nama'     => 'Data Gaji',
            'kategori' => 'Penggajian & Laporan',
            'beranda'  => 'admin.smart-payroll.penggajian.index',
            'prefix'   => ['admin.smart-payroll.penggajian'],
        ],
        // Laporan dipecah PER LAPORAN supaya bisa dipilih satu-satu di Kelola Peran.
        // Kode lama 'gaji_laporan' (membungkus semuanya) di-expand ke kode-kode ini
        // lewat migration 2026_09_30_*_expand_modul_laporan_granular.
        'laporan_ringkasan' => [
            'nama'     => 'Laporan: Ringkasan',
            'kategori' => 'Penggajian & Laporan',
            'beranda'  => 'admin.smart-payroll.laporan.ringkasan',
            'prefix'   => ['admin.smart-payroll.laporan.ringkasan'],
        ],
        'laporan_kehadiran' => [
            'nama'     => 'Laporan: Kehadiran',
            'kategori' => 'Penggajian & Laporan',
            'beranda'  => 'admin.smart-payroll.laporan.kehadiran',
            'prefix'   => ['admin.smart-payroll.laporan.kehadiran'],
        ],
        'laporan_absensi' => [
            'nama'     => 'Laporan: Absensi Harian',
            'kategori' => 'Penggajian & Laporan',
            'beranda'  => 'admin.smart-payroll.laporan.absensi',
            'prefix'   => ['admin.smart-payroll.laporan.absensi'],
        ],
        'laporan_mengajar' => [
            'nama'     => 'Laporan: Absensi Mengajar',
            'kategori' => 'Penggajian & Laporan',
            'beranda'  => 'admin.smart-payroll.laporan.mengajar',
            'prefix'   => ['admin.smart-payroll.laporan.mengajar'],
        ],
        'laporan_pengganti' => [
            'nama'     => 'Laporan: Guru Pengganti',
            'kategori' => 'Penggajian & Laporan',
            'beranda'  => 'admin.smart-payroll.laporan.pengganti',
            'prefix'   => ['admin.smart-payroll.laporan.pengganti'],
        ],
        'laporan_penggajian' => [
            'nama'     => 'Laporan: Penggajian & Slip',
            'kategori' => 'Penggajian & Laporan',
            'beranda'  => 'admin.smart-payroll.laporan.penggajian',
            'prefix'   => [
                'admin.smart-payroll.laporan.penggajian',
                'admin.smart-payroll.laporan.slip-gaji',
            ],
        ],
        'laporan_vakasi' => [
            'nama'     => 'Laporan: Vakasi',
            'kategori' => 'Penggajian & Laporan',
            'beranda'  => 'admin.smart-payroll.laporan.vakasi',
            'prefix'   => [
                'admin.smart-payroll.laporan.vakasi',
                'admin.smart-payroll.laporan.vakasi-detail',
            ],
        ],
        'laporan_guru' => [
            'nama'     => 'Laporan: Rekap per Guru & Ekspor',
            'kategori' => 'Penggajian & Laporan',
            'beranda'  => 'admin.smart-payroll.laporan.detail-guru',
            'prefix'   => [
                'admin.smart-payroll.laporan.detail-guru',
                'admin.smart-payroll.laporan.export-guru',
                'admin.smart-payroll.laporan.export',
            ],
        ],
        'buku_tamu' => [
            'nama'     => 'Buku Tamu',
            'kategori' => 'Penggajian & Laporan',
            'beranda'  => 'admin.smart-payroll.buku-tamu.index',
            'prefix'   => ['admin.smart-payroll.buku-tamu'],
        ],
        'kalender_libur' => [
            'nama'     => 'Kalender Libur',
            'kategori' => 'Penggajian & Laporan',
            'beranda'  => 'admin.smart-payroll.hari-libur.index',
            'prefix'   => ['admin.smart-payroll.hari-libur', 'admin.smart-payroll.libur-tendik'],
        ],

        // ── Smart Education ──────────────────────────────────────────────────
        'se_santri' => [
            'nama'     => 'Santri',
            'kategori' => 'Smart Education',
            'beranda'  => 'admin.smart-education.santri.index',
            'prefix'   => ['admin.smart-education.santri'],
        ],
        'se_ujian' => [
            'nama'     => 'Ujian Sekolah',
            'kategori' => 'Smart Education',
            'beranda'  => 'admin.smart-education.ujian.index',
            'prefix'   => ['admin.smart-education.ujian'],
        ],
        'se_kelas' => [
            'nama'     => 'Kelas',
            'kategori' => 'Smart Education',
            'beranda'  => 'admin.smart-education.kelas.index',
            'prefix'   => ['admin.smart-education.kelas'],
        ],
        'se_ekskul' => [
            'nama'     => 'Ekstrakurikuler',
            'kategori' => 'Smart Education',
            'beranda'  => 'admin.smart-education.ekstrakurikuler.index',
            'prefix'   => ['admin.smart-education.ekstrakurikuler'],
        ],
        'se_jurnal' => [
            'nama'     => 'Jurnal Mengajar',
            'kategori' => 'Smart Education',
            'beranda'  => 'admin.smart-education.jurnal.index',
            'prefix'   => ['admin.smart-education.jurnal'],
        ],
        'se_tahfidz' => [
            'nama'     => 'Tahfidz',
            'kategori' => 'Smart Education',
            'beranda'  => 'admin.smart-education.tahfidz.index',
            'prefix'   => ['admin.smart-education.tahfidz', 'admin.smart-education.tahfidz-monitoring'],
        ],
        'se_tahsin' => [
            'nama'     => 'Tahsin',
            'kategori' => 'Smart Education',
            'beranda'  => 'admin.smart-education.tahsin.index',
            'prefix'   => ['admin.smart-education.tahsin', 'admin.smart-education.materi-tahsin', 'admin.smart-education.tahsin-monitoring'],
        ],
        // Dipecah per laporan (kode lama 'se_laporan' di-expand lewat migration).
        'se_laporan_index' => [
            'nama'     => 'Laporan Pembelajaran: Ringkasan',
            'kategori' => 'Smart Education',
            'beranda'  => 'admin.smart-education.laporan.index',
            'prefix'   => ['admin.smart-education.laporan.index'],
        ],
        'se_laporan_tahfidz' => [
            'nama'     => 'Laporan Pembelajaran: Tahfidz',
            'kategori' => 'Smart Education',
            'beranda'  => 'admin.smart-education.laporan.tahfidz',
            'prefix'   => ['admin.smart-education.laporan.tahfidz'],
        ],
        'se_laporan_tahsin' => [
            'nama'     => 'Laporan Pembelajaran: Tahsin',
            'kategori' => 'Smart Education',
            'beranda'  => 'admin.smart-education.laporan.tahsin',
            'prefix'   => ['admin.smart-education.laporan.tahsin'],
        ],
        'se_laporan_kehadiran_santri' => [
            'nama'     => 'Laporan Pembelajaran: Kehadiran Santri',
            'kategori' => 'Smart Education',
            'beranda'  => 'admin.smart-education.laporan.kehadiran-santri',
            'prefix'   => ['admin.smart-education.laporan.kehadiran-santri'],
        ],
        'se_laporan_ujian' => [
            'nama'     => 'Laporan Pembelajaran: Ujian Sekolah',
            'kategori' => 'Smart Education',
            'beranda'  => 'admin.smart-education.laporan.ujian',
            'prefix'   => ['admin.smart-education.laporan.ujian'],
        ],
        'se_laporan_mengajar_quran' => [
            'nama'     => 'Laporan Pembelajaran: Mengajar Tahfidz & Tahsin',
            'kategori' => 'Smart Education',
            'beranda'  => 'admin.smart-education.laporan.mengajar-quran',
            'prefix'   => ['admin.smart-education.laporan.mengajar-quran'],
        ],

        // ── Kesiswaan ────────────────────────────────────────────────────────
        'perizinan_santri' => [
            'nama'     => 'Perizinan Santri',
            'kategori' => 'Kesiswaan',
            'beranda'  => 'admin.perizinan.index',
            'prefix'   => ['admin.perizinan'],
        ],
        'smart_health' => [
            'nama'     => 'Smart Health',
            'kategori' => 'Kesiswaan',
            'beranda'  => 'admin.smart-health.index',
            'prefix'   => ['admin.smart-health'],
        ],
        'smart_habbit' => [
            'nama'     => 'Smart Controlling & Eksekusi',
            'kategori' => 'Kesiswaan',
            'beranda'  => 'admin.smart-habbit.controlling.index',
            'prefix'   => ['admin.smart-habbit'],
        ],
        'piket' => [
            'nama'     => 'Guru Piket',
            'kategori' => 'Kesiswaan',
            'beranda'  => 'admin.piket.jadwal.index',
            'prefix'   => ['admin.piket'],
        ],

        // ── Sarana ───────────────────────────────────────────────────────────
        'inventaris' => [
            'nama'     => 'Inventaris',
            'kategori' => 'Sarana',
            'beranda'  => 'admin.inventaris.index',
            'prefix'   => ['admin.inventaris'],
        ],

        // ── Komunikasi ───────────────────────────────────────────────────────
        // Saran & masukan pengguna sistem (keluhan/bug dari PWA) — berisi
        // keluhan apa adanya, jadi berikan hanya ke pengelola sistem.
        'masukan' => [
            'nama'     => 'Saran & Masukan',
            'kategori' => 'Komunikasi',
            'beranda'  => 'admin.masukan.index',
            'prefix'   => ['admin.masukan'],
        ],
        // ── Master Data ──────────────────────────────────────────────────────
        // Sebelumnya TIDAK terpetakan modul apa pun → hanya super_admin yang bisa
        // membukanya, sehingga fiturnya tidak pernah muncul di Kelola Peran.
        'master_tenaga_pendidik' => [
            'nama'     => 'Data Tenaga Pendidik',
            'kategori' => 'Master Data',
            'beranda'  => 'admin.master.tenaga-pendidik.index',
            'prefix'   => ['admin.master.tenaga-pendidik'],
        ],
        'master_jabatan' => [
            'nama'     => 'Jabatan & Penugasan Jabatan',
            'kategori' => 'Master Data',
            'beranda'  => 'admin.master.jabatan.index',
            'prefix'   => ['admin.master.jabatan', 'admin.master.jabatan-guru'],
        ],
        'master_mapel' => [
            'nama'     => 'Mata Pelajaran',
            'kategori' => 'Master Data',
            'beranda'  => 'admin.master.mata-pelajaran.index',
            'prefix'   => ['admin.master.mata-pelajaran'],
        ],
        'master_jadwal_mengajar' => [
            'nama'     => 'Jadwal Mengajar',
            'kategori' => 'Master Data',
            'beranda'  => 'admin.master.jadwal-mengajar.index',
            'prefix'   => ['admin.master.jadwal-mengajar'],
        ],
        'master_tahun_ajaran' => [
            'nama'     => 'Tahun Ajaran',
            'kategori' => 'Master Data',
            'beranda'  => 'admin.master.tahun-ajaran.index',
            'prefix'   => ['admin.master.tahun-ajaran'],
        ],

        // ── Pengaturan Sistem ────────────────────────────────────────────────
        'setting_gaji' => [
            'nama'     => 'Setting Gaji (Pokok, Vakasi, Jam Kerja)',
            'kategori' => 'Pengaturan',
            'beranda'  => 'admin.smart-payroll.setting-gaji.index',
            'prefix'   => ['admin.smart-payroll.setting-gaji'],
        ],
        'setting_potongan' => [
            'nama'     => 'Setting Potongan',
            'kategori' => 'Pengaturan',
            'beranda'  => 'admin.smart-payroll.setting-potongan.index',
            'prefix'   => ['admin.smart-payroll.setting-potongan'],
        ],
        'potongan_guru' => [
            'nama'     => 'Potongan per Guru',
            'kategori' => 'Pengaturan',
            'beranda'  => 'admin.smart-payroll.potongan.index',
            'prefix'   => ['admin.smart-payroll.potongan'],
        ],
        'setting_kinerja' => [
            'nama'     => 'Setting Kinerja',
            'kategori' => 'Pengaturan',
            'beranda'  => 'admin.smart-payroll.setting-kinerja.index',
            'prefix'   => ['admin.smart-payroll.setting-kinerja'],
        ],
        'setting_lokasi' => [
            'nama'     => 'Setting Lokasi Absensi',
            'kategori' => 'Pengaturan',
            'beranda'  => 'admin.smart-payroll.setting-lokasi.index',
            'prefix'   => ['admin.smart-payroll.setting-lokasi'],
        ],
        'setting_pengajuan' => [
            'nama'     => 'Setting Jenis Pengajuan Izin',
            'kategori' => 'Pengaturan',
            'beranda'  => 'admin.smart-payroll.setting-pengajuan.index',
            'prefix'   => ['admin.smart-payroll.setting-pengajuan'],
        ],
        'setting_notifikasi' => [
            'nama'     => 'Setting Notifikasi & Broadcast',
            'kategori' => 'Pengaturan',
            'beranda'  => 'admin.smart-payroll.setting-notifikasi.index',
            'prefix'   => ['admin.smart-payroll.setting-notifikasi'],
        ],
        'jadwal_shift' => [
            'nama'     => 'Jadwal Shift (Satpam/Asrama)',
            'kategori' => 'Pengaturan',
            'beranda'  => 'admin.smart-payroll.jadwal-shift.index',
            'prefix'   => ['admin.smart-payroll.jadwal-shift'],
        ],

        // ── Akun & Akses ─────────────────────────────────────────────────────
        'akun_pengguna' => [
            'nama'     => 'Akun Pengguna',
            'kategori' => 'Akun & Akses',
            'beranda'  => 'admin.akun.index',
            'prefix'   => ['admin.akun'],
        ],
        // SANGAT SENSITIF: pemegang modul ini bisa memberi dirinya modul lain.
        // Berikan hanya kepada pengelola sistem.
        'kelola_peran' => [
            'nama'     => 'Kelola Peran (sensitif)',
            'kategori' => 'Akun & Akses',
            'beranda'  => 'admin.peran.index',
            'prefix'   => ['admin.peran'],
        ],
        'pengumuman' => [
            'nama'     => 'Pengumuman',
            'kategori' => 'Komunikasi',
            'beranda'  => 'admin.pengumuman.index',
            'prefix'   => ['admin.pengumuman'],
        ],

        'whatsapp' => [
            'nama'     => 'WhatsApp',
            'kategori' => 'Komunikasi',
            'beranda'  => 'admin.smart-payroll.wa-outbox.index',
            'prefix'   => [
                'admin.smart-payroll.setting-wa',
                'admin.smart-payroll.wa-outbox',
                'admin.smart-payroll.wa-inbox',
            ],
        ],

    ],
];
