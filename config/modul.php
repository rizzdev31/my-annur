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
            'prefix'   => ['admin.smart-payroll.tugas-tambahan'],
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
        'gaji_laporan' => [
            'nama'     => 'Laporan Payroll',
            'kategori' => 'Penggajian & Laporan',
            'beranda'  => 'admin.smart-payroll.laporan.ringkasan',
            'prefix'   => ['admin.smart-payroll.laporan'],
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
        'se_laporan' => [
            'nama'     => 'Laporan Pembelajaran',
            'kategori' => 'Smart Education',
            'beranda'  => 'admin.smart-education.laporan.index',
            'prefix'   => ['admin.smart-education.laporan'],
        ],

        // ── Kesiswaan ────────────────────────────────────────────────────────
        'perizinan_santri' => [
            'nama'     => 'Perizinan Santri',
            'kategori' => 'Kesiswaan',
            'beranda'  => 'admin.perizinan.index',
            'prefix'   => ['admin.perizinan.'],
        ],
        'smart_health' => [
            'nama'     => 'Smart Health',
            'kategori' => 'Kesiswaan',
            'beranda'  => 'admin.smart-health.index',
            'prefix'   => ['admin.smart-health.'],
        ],
        'smart_habbit' => [
            'nama'     => 'Smart Controlling & Eksekusi',
            'kategori' => 'Kesiswaan',
            'beranda'  => 'admin.smart-habbit.controlling.index',
            'prefix'   => ['admin.smart-habbit.'],
        ],
        'piket' => [
            'nama'     => 'Guru Piket',
            'kategori' => 'Kesiswaan',
            'beranda'  => 'admin.piket.jadwal.index',
            'prefix'   => ['admin.piket.'],
        ],

        // ── Sarana ───────────────────────────────────────────────────────────
        'inventaris' => [
            'nama'     => 'Inventaris',
            'kategori' => 'Sarana',
            'beranda'  => 'admin.inventaris.index',
            'prefix'   => ['admin.inventaris.'],
        ],

        // ── Komunikasi ───────────────────────────────────────────────────────
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
