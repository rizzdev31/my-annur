<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthApiController;
use App\Http\Controllers\Api\AbsensiApiController;
use App\Http\Controllers\Api\IzinApiController;
use App\Http\Controllers\Api\JadwalApiController;
use App\Http\Controllers\Api\TugasApiController;
use App\Http\Controllers\Api\LemburApiController;
use App\Http\Controllers\Api\PayrollApiController;
use App\Http\Controllers\Api\NotifikasiApiController;
use App\Http\Controllers\Api\ProfileApiController;
use App\Http\Controllers\Api\KinerjaApiController;
use App\Http\Controllers\Api\Education\LaporanApiController;
use App\Http\Controllers\Api\Education\TahfidzApiController;
use App\Http\Controllers\Api\Education\TahsinApiController;
use App\Http\Controllers\Api\SmartHabbit\EksekusiApiController;
use App\Http\Controllers\Api\SmartHabbit\ControllingScanController;
use App\Http\Controllers\Api\PiketApiController;
use App\Http\Controllers\Api\DashboardApiController;

/*
|--------------------------------------------------------------------------
| API Routes — An Nur Smart System
| Base URL: /api/v1
| Auth: Laravel Sanctum (Bearer Token)
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // ── Public (tanpa auth) ─────────────────────────────────────────────────
    Route::post('/auth/login', [AuthApiController::class, 'login']);
    Route::post('/auth/fcm-token', [AuthApiController::class, 'updateFcmToken']); // update token tanpa auth

    // Smart Controlling — scan barcode (device-agnostic: kiosk/ESP32), proteksi device token.
    Route::prefix('controlling')->middleware('device.token')->group(function () {
        Route::get('/aktif', [ControllingScanController::class, 'aktif']);
        Route::post('/scan', [ControllingScanController::class, 'scan']);
    });

    // Webhook incoming Fonnte (balasan wali → wa_inbox). Proteksi via ?secret=.
    Route::post('/webhook/fonnte/incoming', [\App\Http\Controllers\Api\FonnteWebhookController::class, 'incoming']);

    // ── Protected ───────────────────────────────────────────────────────────
    Route::middleware('auth:sanctum')->group(function () {

        // Auth
        Route::post('/auth/logout', [AuthApiController::class, 'logout']);
        Route::get('/auth/me', [AuthApiController::class, 'me']);
        Route::post('/auth/refresh', [AuthApiController::class, 'refresh']);

        // Profile
        Route::get('/profile', [ProfileApiController::class, 'show']);
        Route::put('/profile', [ProfileApiController::class, 'update']);
        Route::post('/profile/foto', [ProfileApiController::class, 'updateFoto']);
        Route::put('/profile/password', [ProfileApiController::class, 'updatePassword']);

        // Pengumuman/pamflet pop-up (semua user aplikasi)
        Route::get('/pengumuman/aktif', [\App\Http\Controllers\Api\PengumumanApiController::class, 'aktif']);

        // ── Tenaga Pendidik only ─────────────────────────────────────────────
        Route::middleware('role.api:tenaga_pendidik')->group(function () {

            // Dashboard ringkasan (agregasi real-time)
            Route::get('/dashboard/ringkasan', [DashboardApiController::class, 'ringkasan']);

            // Absensi
            Route::prefix('absensi')->group(function () {
                Route::get('/hari-ini', [AbsensiApiController::class, 'hariIni']);
                Route::get('/riwayat', [AbsensiApiController::class, 'riwayat']);
                Route::get('/rekap/{bulan}/{tahun}', [AbsensiApiController::class, 'rekap']);

                // Check-in & Check-out (dengan foto + GPS)
                Route::post('/check-in', [AbsensiApiController::class, 'checkIn']);
                Route::post('/check-out', [AbsensiApiController::class, 'checkOut']);

                // Absen mengajar
                Route::get('/mengajar/hari-ini',        [AbsensiApiController::class, 'jadwalMengajarHariIni']);
                Route::post('/mengajar/absen',           [AbsensiApiController::class, 'absenMengajar']);
                Route::post('/mengajar/konfirmasi-izin', [AbsensiApiController::class, 'konfirmasiIzin']);

                // Guru pengganti saat izin
                Route::get('/mengajar/pengganti-opsi',   [AbsensiApiController::class, 'penggantiOpsi']);
                Route::post('/mengajar/tunjuk-pengganti',[AbsensiApiController::class, 'tunjukPengganti']);
                Route::get('/mengajar/pengganti-saya',   [AbsensiApiController::class, 'penggantiSaya']);
                Route::post('/mengajar/absen-pengganti', [AbsensiApiController::class, 'absenPengganti']);
                Route::post('/mengajar/batal-pengganti', [AbsensiApiController::class, 'batalPengganti']);
                Route::post('/mengajar/mulai',           [AbsensiApiController::class, 'mulaiMengajar']);
                Route::post('/mengajar/selesai',         [AbsensiApiController::class, 'selesaiMengajar']);
                Route::get('/mengajar/riwayat',          [AbsensiApiController::class, 'riwayatMengajar']);

                // Smart Education — absensi santri (jurnal mengajar sekolah)
                Route::post('/mengajar/absen-santri',    [AbsensiApiController::class, 'absenSantri']);
                Route::get('/mengajar/{jadwalId}/santri', [AbsensiApiController::class, 'santriKelasJadwal']);
            });

            // Jadwal
            Route::prefix('jadwal')->group(function () {
                Route::get('/', [JadwalApiController::class, 'index']);
                Route::get('/hari-ini', [JadwalApiController::class, 'hariIni']);
                Route::get('/minggu-ini', [JadwalApiController::class, 'mingguIni']);
            });

            // Tugas Tambahan (mandiri)
            Route::prefix('tugas')->group(function () {
                Route::get('/',                                   [TugasApiController::class, 'index']);
                Route::get('/aktif',                              [TugasApiController::class, 'aktif']);
                Route::get('/{penugasan}',                        [TugasApiController::class, 'show']);
                Route::post('/{penugasan}/mulai',                 [TugasApiController::class, 'mulai']);
                Route::post('/{penugasan}/laporan',               [TugasApiController::class, 'kirimLaporan']);

                // Tugas Jabatan
                Route::get('/jabatan/list',                       [TugasApiController::class, 'tugasJabatan']);
                Route::post('/jabatan/{tugasJabatan}/realisasi',  [TugasApiController::class, 'realisasiJabatan']);
            });

            // Lembur (guru: ajukan + upload bukti GPS)
            Route::prefix('lembur')->group(function () {
                Route::get('/',                       [LemburApiController::class, 'index']);
                Route::post('/ajukan',                [LemburApiController::class, 'ajukan']);
                Route::post('/{peserta}/bukti',       [LemburApiController::class, 'uploadBukti']);
            });

            // Absensi Kegiatan (tipe_pengerjaan = absen_kegiatan)
            Route::prefix('kegiatan')->group(function () {
                Route::get('/',                                   [TugasApiController::class, 'kegiatanSaya']);
                Route::post('/',                                  [TugasApiController::class, 'buatKegiatan']);
                Route::get('/{kegiatanId}',                       [TugasApiController::class, 'kegiatanDetail']);
                Route::post('/{kegiatanId}/peserta',              [TugasApiController::class, 'tambahPeserta']);
                Route::patch('/{kegiatanId}/absensi-bulk',        [TugasApiController::class, 'updateAbensiBulk']);
                Route::post('/{kegiatanId}/selesaikan',           [TugasApiController::class, 'selesaikanKegiatan']);
            });

            // Ekstrakurikuler (pembina) — absensi per pertemuan (→vakasi) + penilaian A/B/C
            Route::prefix('ekstrakurikuler')->group(function () {
                $c = \App\Http\Controllers\Api\EkstrakurikulerApiController::class;
                Route::get('/saya',                    [$c, 'saya']);
                Route::get('/{id}',                    [$c, 'detail']);
                Route::post('/{id}/pertemuan',         [$c, 'mulaiPertemuan']);
                Route::get('/pertemuan/{id}',          [$c, 'pertemuanDetail']);
                Route::post('/pertemuan/{id}/absensi', [$c, 'simpanAbsensi']);
                Route::get('/{id}/penilaian',          [$c, 'penilaianList']);
                Route::post('/{id}/penilaian',         [$c, 'simpanPenilaian']);
            });

            // Payroll — guru hanya bisa lihat slipnya sendiri
            Route::prefix('payroll')->group(function () {
                Route::get('/riwayat', [PayrollApiController::class, 'riwayat']);
                Route::get('/terkini', [PayrollApiController::class, 'terkini']);
                Route::get('/{periode}', [PayrollApiController::class, 'detail']);
                Route::get('/{penggajian}/slip', [PayrollApiController::class, 'slip']);
            });

            // Kinerja — guru lihat skor & rekap kinerjanya sendiri
            Route::prefix('kinerja')->group(function () {
                Route::get('/bulan-ini',    [KinerjaApiController::class, 'bulanIni']);    // preview real-time bulan ini
                Route::get('/riwayat',      [KinerjaApiController::class, 'riwayat']);     // rekap 12 bulan tersimpan
                Route::get('/nilai-status', [KinerjaApiController::class, 'nilaiStatus']); // tabel nilai per status
                Route::get('/punishment',   [KinerjaApiController::class, 'punishment']);  // teguran/punishment guru
            });

            // Smart Education — Laporan (jurnal pembelajaran per kelas)
            Route::prefix('education')->group(function () {
                Route::get('/laporan/pembelajaran', [LaporanApiController::class, 'pembelajaran']);
                Route::get('/laporan/tahfidz',      [LaporanApiController::class, 'tahfidz']);
                Route::get('/laporan/tahsin',       [LaporanApiController::class, 'tahsin']);

                // Smart Tahfidz — setoran & tracking hafalan
                Route::get('/surah',                              [TahfidzApiController::class, 'surah']);
                Route::get('/tahfidz/jadwal-hari-ini',            [TahfidzApiController::class, 'jadwalHariIni']);
                Route::post('/tahfidz/absen',                     [TahfidzApiController::class, 'absen']);
                Route::get('/tahfidz/sesi/{absensiMengajarId}/santri', [TahfidzApiController::class, 'rosterSesi']);
                Route::get('/tahfidz/jadwal/{jadwalId}/roster',   [TahfidzApiController::class, 'rosterJadwal']); // setoran luar jam
                Route::post('/tahfidz/setoran',                   [TahfidzApiController::class, 'setoran']);
                Route::get('/tahfidz/santri/{santriId}/status',   [TahfidzApiController::class, 'statusSantri']);
                // Tasmi' sebagai tugas tambahan ber-vakasi
                Route::get('/tahfidz/penguji-opsi',               [TahfidzApiController::class, 'pengujiOpsi']);
                Route::post('/tahfidz/tunjuk-tasmi',              [TahfidzApiController::class, 'tunjukTasmi']);
                Route::get('/tahfidz/tasmi-saya',                 [TahfidzApiController::class, 'tasmiSaya']);
                Route::post('/tahfidz/tasmi/{tugasTasmi}/nilai',  [TahfidzApiController::class, 'nilaiTasmi']);
                Route::get('/tahfidz/tasmi/{tugasTasmi}/sertifikat', [TahfidzApiController::class, 'sertifikatTasmi']);

                // Smart Tahsin — absen, materi & penilaian per level
                Route::get('/tahsin/jadwal-hari-ini',             [TahsinApiController::class, 'jadwalHariIni']);
                Route::post('/tahsin/absen',                      [TahsinApiController::class, 'absen']);
                Route::get('/tahsin/sesi/{absensiMengajarId}/santri', [TahsinApiController::class, 'rosterSesi']);
                Route::get('/tahsin/jadwal/{jadwalId}/roster',    [TahsinApiController::class, 'rosterJadwal']); // penilaian luar jam
                Route::get('/tahsin/santri/{santriId}/materi',    [TahsinApiController::class, 'materiSantri']);
                Route::post('/tahsin/nilai',                      [TahsinApiController::class, 'nilai']);
                Route::post('/tahsin/santri/{santriId}/naik-level', [TahsinApiController::class, 'naikLevel']);
                // TASNIF — ujian kenaikan level (analog tasmi')
                Route::get('/tahsin/penguji-opsi',                [TahsinApiController::class, 'pengujiOpsi']);
                Route::post('/tahsin/tunjuk-tasnif',              [TahsinApiController::class, 'tunjukTasnif']);
                Route::get('/tahsin/tasnif-saya',                 [TahsinApiController::class, 'tasnifSaya']);
                Route::post('/tahsin/tasnif/{tugasTasnif}/nilai', [TahsinApiController::class, 'nilaiTasnif']);
                Route::get('/tahsin/tasnif/{tugasTasnif}/sertifikat', [TahsinApiController::class, 'sertifikatTasnif']);
            });

            // Smart Habbit — Smart Eksekusi (lapor santri → RamahAnak via outbox)
            Route::prefix('smart-habbit')->group(function () {
                Route::get('/kode/{jenis}',            [EksekusiApiController::class, 'kode']);
                Route::get('/santri',                  [EksekusiApiController::class, 'santri']);
                Route::post('/eksekusi',               [EksekusiApiController::class, 'store']);
                Route::get('/outbox',                  [EksekusiApiController::class, 'outbox']);
                Route::post('/outbox/{outbox}/retry',  [EksekusiApiController::class, 'retry']);

                // Smart Controlling — fallback scan via HP guru (auth Bearer, petugas = guru login).
                Route::get('/controlling/aktif', [ControllingScanController::class, 'aktif']);
                Route::post('/controlling/scan', [ControllingScanController::class, 'scanGuru']);
            });

            // Smart Health — petugas Bagian Kesehatan (lapor/validasi/pengecekan).
            Route::prefix('health')->group(function () {
                Route::get('/status',            [\App\Http\Controllers\Api\SmartHealthApiController::class, 'status']);
                Route::get('/santri',            [\App\Http\Controllers\Api\SmartHealthApiController::class, 'santri']);
                Route::get('/',                  [\App\Http\Controllers\Api\SmartHealthApiController::class, 'daftar']);
                Route::post('/lapor',            [\App\Http\Controllers\Api\SmartHealthApiController::class, 'lapor']);
                Route::post('/{laporan}/setujui',   [\App\Http\Controllers\Api\SmartHealthApiController::class, 'setujui']);
                Route::post('/{laporan}/tolak',     [\App\Http\Controllers\Api\SmartHealthApiController::class, 'tolak']);
                Route::post('/{laporan}/pengecekan',[\App\Http\Controllers\Api\SmartHealthApiController::class, 'pengecekan']);
            });

            // Perizinan Santri — guru petugas (ajukan/setujui/tolak).
            Route::prefix('perizinan')->group(function () {
                Route::get('/status',           [\App\Http\Controllers\Api\PerizinanApiController::class, 'status']);
                Route::get('/santri',           [\App\Http\Controllers\Api\PerizinanApiController::class, 'santri']);
                Route::get('/',                 [\App\Http\Controllers\Api\PerizinanApiController::class, 'daftar']);
                Route::post('/',                [\App\Http\Controllers\Api\PerizinanApiController::class, 'ajukan']);
                Route::post('/{izin}/setujui',  [\App\Http\Controllers\Api\PerizinanApiController::class, 'setujui']);
                Route::post('/{izin}/tolak',    [\App\Http\Controllers\Api\PerizinanApiController::class, 'tolak']);
            });

            // Inventaris — pengajuan pemakaian sarana oleh guru.
            Route::prefix('inventaris')->group(function () {
                Route::get('/',  [\App\Http\Controllers\Api\InventarisApiController::class, 'index']);
                Route::get('/peminjaman', [\App\Http\Controllers\Api\InventarisApiController::class, 'peminjamanSaya']);
                Route::post('/peminjaman', [\App\Http\Controllers\Api\InventarisApiController::class, 'ajukan']);
                Route::post('/peminjaman/{peminjaman}/batal', [\App\Http\Controllers\Api\InventarisApiController::class, 'batal']);
                Route::get('/{inventaris}/ketersediaan', [\App\Http\Controllers\Api\InventarisApiController::class, 'ketersediaan']);
            });

            // Guru Piket — penilaian kinerja keliling (tunduk window jam kerja piket).
            Route::prefix('piket')->group(function () {
                Route::get('/status',         [PiketApiController::class, 'status']);
                Route::get('/kategori',       [PiketApiController::class, 'kategori']);
                Route::get('/guru',           [PiketApiController::class, 'guru']);
                Route::post('/penilaian',     [PiketApiController::class, 'store']);
                Route::get('/penilaian',      [PiketApiController::class, 'riwayat']);
                Route::post('/laporan-harian',[PiketApiController::class, 'laporanHarian']);
                // Kegiatan Penting Guru (absensi kegiatan oleh guru piket)
                Route::get('/kegiatan',                    [\App\Http\Controllers\Api\KegiatanPentingApiController::class, 'hariIni']);
                Route::get('/kegiatan/{kegiatan}/peserta', [\App\Http\Controllers\Api\KegiatanPentingApiController::class, 'peserta']);
                Route::post('/kegiatan/{kegiatan}/simpan', [\App\Http\Controllers\Api\KegiatanPentingApiController::class, 'simpan']);
                // Handoff absensi santri (guru tak konfirmasi → piket)
                Route::get('/sesi',           [PiketApiController::class, 'sesi']);
                Route::get('/roster/{jadwal}',[PiketApiController::class, 'roster']);
                Route::post('/absen-kelas',   [PiketApiController::class, 'absenKelas']);
                // Hak sanggah (guru yang dinilai)
                Route::get('/penilaian-saya',        [PiketApiController::class, 'penilaianSaya']);
                Route::post('/penilaian/{id}/sanggah', [PiketApiController::class, 'sanggah']);
            });

            // Pengajuan Izin / Cuti / Sakit / Dinas
            Route::prefix('izin')->group(function () {
                Route::get('/jenis', [IzinApiController::class, 'jenisIzin']);    // daftar jenis + sisa kuota
                Route::get('/',     [IzinApiController::class, 'riwayat']);       // riwayat pengajuan guru
                Route::post('/',    [IzinApiController::class, 'buat']);          // buat pengajuan baru
                Route::delete('/{id}', [IzinApiController::class, 'batalkan']);   // batalkan pengajuan
            });

            // Notifikasi
            Route::prefix('notifikasi')->group(function () {
                Route::get('/', [NotifikasiApiController::class, 'index']);
                Route::get('/unread-count', [NotifikasiApiController::class, 'unreadCount']);
                Route::patch('/{notifikasi}/baca', [NotifikasiApiController::class, 'markAsRead']);
                Route::post('/baca-semua', [NotifikasiApiController::class, 'markAllAsRead']);
            });

            // Berita pesantren (proxy CMS → banner Beranda)
            Route::get('/berita', [\App\Http\Controllers\Api\BeritaApiController::class, 'index']);
        });
    });
});
/*
|--------------------------------------------------------------------------
| Portal SANTRI / WALI — monitoring read-only (token santri, ability santri:read)
|--------------------------------------------------------------------------
*/
Route::prefix('santri')->group(function () {
    // Auth (publik) — login harian pakai password; aktivasi/reset untuk atur password
    Route::post('auth/login',          [\App\Http\Controllers\Api\Santri\AuthController::class, 'login']);
    Route::post('auth/aktivasi',       [\App\Http\Controllers\Api\Santri\AuthController::class, 'aktivasi']);
    Route::post('auth/minta-otp',      [\App\Http\Controllers\Api\Santri\AuthController::class, 'mintaOtp']);
    Route::post('auth/reset-password', [\App\Http\Controllers\Api\Santri\AuthController::class, 'resetPassword']);

    // Data (token santri saja)
    Route::middleware(['auth:sanctum', \App\Http\Middleware\EnsureSantri::class])->group(function () {
        Route::get('auth/me',   [\App\Http\Controllers\Api\Santri\AuthController::class, 'me']);
        Route::post('auth/logout', [\App\Http\Controllers\Api\Santri\AuthController::class, 'logout']);

        Route::get('beranda',     [\App\Http\Controllers\Api\Santri\MonitorController::class, 'beranda']);
        Route::get('absensi',     [\App\Http\Controllers\Api\Santri\MonitorController::class, 'absensi']);
        Route::get('tahfidz',     [\App\Http\Controllers\Api\Santri\MonitorController::class, 'tahfidz']);
        Route::get('tahsin',      [\App\Http\Controllers\Api\Santri\MonitorController::class, 'tahsin']);
        Route::get('controlling', [\App\Http\Controllers\Api\Santri\MonitorController::class, 'controlling']);
        Route::get('izin',        [\App\Http\Controllers\Api\Santri\MonitorController::class, 'izin']);
        Route::get('kesehatan',   [\App\Http\Controllers\Api\Santri\MonitorController::class, 'kesehatan']);
        Route::get('pengumuman',  [\App\Http\Controllers\Api\Santri\MonitorController::class, 'pengumuman']);
        Route::get('tahfidz/tasmi/{id}/sertifikat', [\App\Http\Controllers\Api\Santri\MonitorController::class, 'tasmiSertifikat']);
        Route::get('tahsin/tasnif/{id}/sertifikat', [\App\Http\Controllers\Api\Santri\MonitorController::class, 'tasnifSertifikat']);
    });
});
