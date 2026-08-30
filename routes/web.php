<?php

use Illuminate\Support\Facades\Route;

// ── Auth ──────────────────────────────────────────────────────────────────────
use App\Http\Controllers\Auth\AuthController;

// ── Master Data ───────────────────────────────────────────────────────────────
use App\Http\Controllers\Superadmin\DashboardController;
use App\Http\Controllers\Superadmin\NotifikasiController;
use App\Http\Controllers\Superadmin\PeranController;
use App\Http\Controllers\Superadmin\AkunController;
use App\Http\Controllers\Superadmin\PengumumanController;
use App\Http\Controllers\Superadmin\TenagaPendidikController;
use App\Http\Controllers\Superadmin\JabatanGuruController;
use App\Http\Controllers\Superadmin\JabatanController;
use App\Http\Controllers\Superadmin\JadwalMengajarController;
use App\Http\Controllers\Superadmin\MataPelajaranController;
use App\Http\Controllers\Superadmin\TahunAjaranController;

// ── Smart Payroll — Absensi & Monitoring ──────────────────────────────────────
use App\Http\Controllers\Superadmin\AbsensiController;
use App\Http\Controllers\Superadmin\MonitoringController;

// ── Smart Payroll — Kinerja ───────────────────────────────────────────────────
use App\Http\Controllers\Superadmin\KinerjaController;

// ── Smart Payroll — Tugas ─────────────────────────────────────────────────────
use App\Http\Controllers\Superadmin\TugasJabatanController;
use App\Http\Controllers\Superadmin\LemburController;
use App\Http\Controllers\Superadmin\TugasTambahanController;
use App\Http\Controllers\Superadmin\AbsensiKegiatanController;

// ── Smart Payroll — Izin & Libur ──────────────────────────────────────────────
use App\Http\Controllers\Superadmin\PengajuanIzinController;
use App\Http\Controllers\Superadmin\HariLiburController;
use App\Http\Controllers\Superadmin\LiburTendikController;
use App\Http\Controllers\Superadmin\InventarisController;
use App\Http\Controllers\Superadmin\PerizinanController;
use App\Http\Controllers\Superadmin\SmartHealthController;
use App\Http\Controllers\Superadmin\SettingWaController;
use App\Http\Controllers\Superadmin\WaOutboxController;
use App\Http\Controllers\Superadmin\WaInboxController;

// ── Smart Payroll — Penggajian ────────────────────────────────────────────────
use App\Http\Controllers\Superadmin\PeriodePenggajianController;
use App\Http\Controllers\Superadmin\PenggajianController;

// ── Smart Payroll — Laporan ───────────────────────────────────────────────────
use App\Http\Controllers\Superadmin\LaporanController;

// ── Setting ───────────────────────────────────────────────────────────────────
use App\Http\Controllers\Superadmin\SettingGajiController;
use App\Http\Controllers\Superadmin\SettingKinerjaController;
use App\Http\Controllers\Superadmin\SettingLokasiAbsensiController;
use App\Http\Controllers\Superadmin\SettingPotonganController;
use App\Http\Controllers\Superadmin\SettingJenisPengajuanController;

// ── Smart Education ───────────────────────────────────────────────────────────
use App\Http\Controllers\Superadmin\Education\SantriController;
use App\Http\Controllers\Superadmin\Education\KelasController;
use App\Http\Controllers\Superadmin\Education\JurnalMengajarController;
use App\Http\Controllers\Superadmin\Education\LaporanController as EducationLaporanController;
use App\Http\Controllers\Superadmin\Education\TahfidzController as EducationTahfidzController;
use App\Http\Controllers\Superadmin\Education\MonitoringTahfidzController;
use App\Http\Controllers\Superadmin\Education\MateriTahsinController;
use App\Http\Controllers\Superadmin\Education\MonitoringTahsinController;
use App\Http\Controllers\Superadmin\Education\TahsinController as EducationTahsinController;
use App\Http\Controllers\Superadmin\SmartHabbit\ControllingSettingController;
use App\Http\Controllers\Superadmin\SmartHabbit\EksekusiController as SmartEksekusiController;
use App\Http\Controllers\Superadmin\SmartHabbit\ControllingRekapController;
use App\Http\Controllers\Superadmin\Piket\KategoriController as PiketKategoriController;
use App\Http\Controllers\Superadmin\Piket\JadwalController as PiketJadwalController;
use App\Http\Controllers\Superadmin\Piket\SanggahController as PiketSanggahController;

// ═════════════════════════════════════════════════════════════════════════════
// ROOT REDIRECT
// ═════════════════════════════════════════════════════════════════════════════

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('admin.dashboard')
        : redirect()->route('login');
});

// ─────────────────────────────────────────────────────────────────────────────
// GURU MOBILE-WEB (PWA SPA) — semua rute /guru/* dilayani shell SPA;
// routing internal ditangani Vue Router. Auth lewat API token (bukan session).
// ─────────────────────────────────────────────────────────────────────────────
Route::get('/guru/{any?}', fn () => view('guru'))
    ->where('any', '.*')
    ->name('guru');

// Portal Santri/Wali (PWA SPA) — routing internal oleh Vue Router, auth via API token.
Route::get('/santri/{any?}', fn () => view('santri'))
    ->where('any', '.*')
    ->name('santri');

// ═════════════════════════════════════════════════════════════════════════════
// AUTH
// ═════════════════════════════════════════════════════════════════════════════

Route::middleware('guest')->group(function () {
    Route::get('/login',  [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

// ═════════════════════════════════════════════════════════════════════════════
// SUPERADMIN AREA
// ═════════════════════════════════════════════════════════════════════════════

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'role:super_admin,admin', 'akses'])
    ->group(function () {

        // Dashboard
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        // Notifikasi (dropdown lonceng topbar — dikonsumsi via fetch)
        Route::get('notifikasi', [NotifikasiController::class, 'index'])->name('notifikasi.index');
        Route::get('notifikasi/count', [NotifikasiController::class, 'count'])->name('notifikasi.count');
        Route::post('notifikasi/baca-semua', [NotifikasiController::class, 'bacaSemua'])->name('notifikasi.baca-semua');
        Route::post('notifikasi/{id}/baca', [NotifikasiController::class, 'baca'])->name('notifikasi.baca');

        // ── RBAC: Kelola Peran & Akun (superadmin only via fail-safe 'akses') ──
        Route::get('peran', [PeranController::class, 'index'])->name('peran.index');
        Route::post('peran', [PeranController::class, 'store'])->name('peran.store');
        Route::put('peran/{peran}', [PeranController::class, 'update'])->name('peran.update');
        Route::patch('peran/{peran}/toggle', [PeranController::class, 'toggle'])->name('peran.toggle');
        Route::delete('peran/{peran}', [PeranController::class, 'destroy'])->name('peran.destroy');

        Route::get('akun', [AkunController::class, 'index'])->name('akun.index');
        Route::post('akun', [AkunController::class, 'store'])->name('akun.store');
        Route::put('akun/{akun}', [AkunController::class, 'update'])->name('akun.update');
        Route::patch('akun/{akun}/toggle', [AkunController::class, 'toggle'])->name('akun.toggle');
        Route::patch('akun/{akun}/reset-password', [AkunController::class, 'resetPassword'])->name('akun.reset-password');
        Route::delete('akun/{akun}', [AkunController::class, 'destroy'])->name('akun.destroy');

        // ── Pengumuman/Pamflet pop-up aplikasi Flutter (superadmin only) ──────
        Route::get('pengumuman', [PengumumanController::class, 'index'])->name('pengumuman.index');
        Route::post('pengumuman', [PengumumanController::class, 'store'])->name('pengumuman.store');
        Route::post('pengumuman/{pengumuman}', [PengumumanController::class, 'update'])->name('pengumuman.update');
        Route::patch('pengumuman/{pengumuman}/toggle', [PengumumanController::class, 'toggle'])->name('pengumuman.toggle');
        Route::delete('pengumuman/{pengumuman}', [PengumumanController::class, 'destroy'])->name('pengumuman.destroy');

        // ╔══════════════════════════════════════════════════════════════════╗
        // ║  MASTER DATA                                                     ║
        // ╚══════════════════════════════════════════════════════════════════╝

        Route::prefix('master')->name('master.')->group(function () {

            // ── Jabatan ───────────────────────────────────────────────────
            Route::patch('jabatan/{jabatan}/toggle-status',
                [JabatanController::class, 'toggleStatus'])->name('jabatan.toggle-status');
            Route::get('jabatan/multi',
                [JabatanController::class, 'multiJabatan'])->name('jabatan.multi');
            Route::resource('jabatan', JabatanController::class);

            // ── Tenaga Pendidik ───────────────────────────────────────────
            Route::get('tenaga-pendidik/{tenagaPendidik}/rekap-absensi',
                [TenagaPendidikController::class, 'rekapAbsensi'])->name('tenaga-pendidik.rekap-absensi');
            Route::patch('tenaga-pendidik/{tenagaPendidik}/toggle-status',
                [TenagaPendidikController::class, 'toggleStatus'])->name('tenaga-pendidik.toggle-status');
            Route::post('tenaga-pendidik/{tenagaPendidik}/libur/setujui',
                [TenagaPendidikController::class, 'setujuiLibur'])->name('tenaga-pendidik.libur.setujui');
            Route::post('tenaga-pendidik/{tenagaPendidik}/libur/tolak',
                [TenagaPendidikController::class, 'tolakLibur'])->name('tenaga-pendidik.libur.tolak');
            Route::get('tenaga-pendidik/pengajuan-libur',
                [TenagaPendidikController::class, 'pengajuanLiburIndex'])->name('tenaga-pendidik.pengajuan-libur');

            // Jabatan Guru (pivot multi-jabatan)
            Route::get('tenaga-pendidik/{tenagaPendidik}/jabatan',
                [JabatanGuruController::class, 'index'])->name('jabatan-guru.index');
            Route::post('tenaga-pendidik/{tenagaPendidik}/jabatan',
                [JabatanGuruController::class, 'store'])->name('jabatan-guru.store');
            Route::patch('tenaga-pendidik/{tenagaPendidik}/jabatan/{jabatanGuru}',
                [JabatanGuruController::class, 'update'])->name('jabatan-guru.update');
            Route::patch('tenaga-pendidik/{tenagaPendidik}/jabatan/{jabatanGuru}/set-utama',
                [JabatanGuruController::class, 'setUtama'])->name('jabatan-guru.set-utama');
            Route::delete('tenaga-pendidik/{tenagaPendidik}/jabatan/{jabatanGuru}',
                [JabatanGuruController::class, 'destroy'])->name('jabatan-guru.destroy');

            // Import Excel (HARUS sebelum resource agar tidak tertangkap {tenagaPendidik})
            Route::get('tenaga-pendidik/template-import', [TenagaPendidikController::class, 'templateImport'])->name('tenaga-pendidik.template-import');
            Route::post('tenaga-pendidik/import', [TenagaPendidikController::class, 'import'])->name('tenaga-pendidik.import');
            Route::resource('tenaga-pendidik', TenagaPendidikController::class);

            // ── Tahun Ajaran ──────────────────────────────────────────────
            Route::patch('tahun-ajaran/{tahunAjaran}/set-aktif',
                [TahunAjaranController::class, 'setAktif'])->name('tahun-ajaran.set-aktif');
            Route::get('tahun-ajaran',                 [TahunAjaranController::class, 'index'])->name('tahun-ajaran.index');
            Route::post('tahun-ajaran',                [TahunAjaranController::class, 'store'])->name('tahun-ajaran.store');
            Route::put('tahun-ajaran/{tahunAjaran}',   [TahunAjaranController::class, 'update'])->name('tahun-ajaran.update');
            Route::delete('tahun-ajaran/{tahunAjaran}',[TahunAjaranController::class, 'destroy'])->name('tahun-ajaran.destroy');

            // ── Mata Pelajaran ────────────────────────────────────────────
            Route::get('mata-pelajaran',                    [MataPelajaranController::class, 'index'])->name('mata-pelajaran.index');
            Route::get('mata-pelajaran/template-import',    [MataPelajaranController::class, 'templateImport'])->name('mata-pelajaran.template-import');
            Route::post('mata-pelajaran/import',            [MataPelajaranController::class, 'import'])->name('mata-pelajaran.import');
            Route::post('mata-pelajaran',                   [MataPelajaranController::class, 'store'])->name('mata-pelajaran.store');
            Route::put('mata-pelajaran/{mataPelajaran}',    [MataPelajaranController::class, 'update'])->name('mata-pelajaran.update');
            Route::delete('mata-pelajaran/{mataPelajaran}', [MataPelajaranController::class, 'destroy'])->name('mata-pelajaran.destroy');

            // ── Jadwal Mengajar ───────────────────────────────────────────
            Route::get('jadwal-mengajar/export',
                [JadwalMengajarController::class, 'export'])->name('jadwal-mengajar.export');
            Route::resource('jadwal-mengajar', JadwalMengajarController::class)
                ->except(['create', 'edit', 'show']);
        });

        // ╔══════════════════════════════════════════════════════════════════╗
        // ║  INVENTARIS — master + pengajuan pemakaian (anti double-booking)  ║
        // ╚══════════════════════════════════════════════════════════════════╝
        Route::prefix('inventaris')->name('inventaris.')->group(function () {
            Route::get('/rekap', [InventarisController::class, 'rekap'])->name('rekap');
            Route::get('/',  [InventarisController::class, 'index'])->name('index');
            Route::post('/', [InventarisController::class, 'store'])->name('store');
            Route::patch('/{inventaris}',  [InventarisController::class, 'update'])->name('update');
            Route::delete('/{inventaris}', [InventarisController::class, 'destroy'])->name('destroy');
            // Keputusan pengajuan
            Route::patch('/peminjaman/{peminjaman}/setujui', [InventarisController::class, 'setujui'])->name('peminjaman.setujui');
            Route::patch('/peminjaman/{peminjaman}/tolak',   [InventarisController::class, 'tolak'])->name('peminjaman.tolak');
            Route::patch('/peminjaman/{peminjaman}/selesai', [InventarisController::class, 'selesai'])->name('peminjaman.selesai');
            Route::patch('/peminjaman/{peminjaman}/batal',   [InventarisController::class, 'batal'])->name('peminjaman.batal');
        });

        // ╔══════════════════════════════════════════════════════════════════╗
        // ║  PERIZINAN SANTRI — delegasi petugas + monitor                    ║
        // ╚══════════════════════════════════════════════════════════════════╝
        Route::prefix('perizinan')->name('perizinan.')->group(function () {
            Route::get('/',         [PerizinanController::class, 'index'])->name('index');
            Route::post('/petugas', [PerizinanController::class, 'simpanPetugas'])->name('petugas');
        });

        // Smart Health — delegasi petugas kesehatan + monitor kasus
        Route::prefix('smart-health')->name('smart-health.')->group(function () {
            Route::get('/',         [SmartHealthController::class, 'index'])->name('index');
            Route::post('/petugas', [SmartHealthController::class, 'simpanPetugas'])->name('petugas');
        });

        // ╔══════════════════════════════════════════════════════════════════╗
        // ║  SMART PAYROLL                                                   ║
        // ╚══════════════════════════════════════════════════════════════════╝

        Route::prefix('smart-payroll')->name('smart-payroll.')->group(function () {

            // ── 1. ABSENSI ────────────────────────────────────────────────
            Route::prefix('absensi')->name('absensi.')->group(function () {
                Route::get('/',               [AbsensiController::class, 'index'])->name('index');
                Route::get('/harian',         [AbsensiController::class, 'harian'])->name('harian');
                Route::post('/harian',        [AbsensiController::class, 'storeHarian'])->name('store-harian');
                Route::post('/massal',        [AbsensiController::class, 'massal'])->name('massal');
                Route::get('/mengajar',       [AbsensiController::class, 'mengajar'])->name('mengajar');
                Route::post('/mengajar',      [AbsensiController::class, 'storeMengajar'])->name('store-mengajar');
                Route::get('/rekap',          [AbsensiController::class, 'rekap'])->name('rekap');
                Route::get('/export',         [AbsensiController::class, 'export'])->name('export');
                Route::post('/insert-manual', [AbsensiController::class, 'insertManual'])->name('insert-manual');
                Route::patch('/koreksi-harian/{absensi}',   [AbsensiController::class, 'koreksiHarian'])->name('koreksi-harian');
                Route::patch('/koreksi-mengajar/{absensi}', [AbsensiController::class, 'koreksiMengajar'])->name('koreksi-mengajar');
                Route::get('/koreksi', [AbsensiController::class, 'koreksi'])->name('koreksi.index');
            });

            // ── 2. MONITORING HARIAN ──────────────────────────────────────
            Route::prefix('monitoring')->name('monitoring.')->group(function () {
                Route::get('/',                      [MonitoringController::class, 'index'])->name('index');
                Route::get('/detail/{guru}',         [MonitoringController::class, 'detailGuru'])->name('detail');
                Route::post('/koreksi',              [MonitoringController::class, 'koreksi'])->name('koreksi');
                Route::post('/verifikasi-log/{log}', [MonitoringController::class, 'verifikasiLog'])->name('verifikasi-log');
            });

            // ── 3. KINERJA ────────────────────────────────────────────────
            Route::prefix('kinerja')->name('kinerja.')->group(function () {
                Route::get('/',                               [KinerjaController::class, 'index'])->name('index');
                Route::get('/log-kerja',                      [KinerjaController::class, 'logKerja'])->name('log-kerja');
                Route::post('/verifikasi-bulk',               [KinerjaController::class, 'verifikasiBulk'])->name('verifikasi-bulk');
                Route::post('/hitung-rekap',                  [KinerjaController::class, 'hitungRekap'])->name('hitung-rekap');
                Route::post('/log/{log}/verifikasi',          [KinerjaController::class, 'verifikasi'])->name('log.verifikasi');
                Route::post('/log/{log}/tolak',               [KinerjaController::class, 'tolak'])->name('log.tolak');
                Route::post('/rekap/{rekap}/catatan',         [KinerjaController::class, 'catatanRekap'])->name('rekap.catatan');
                Route::get('/punishment',                     [KinerjaController::class, 'punishmentIndex'])->name('punishment.index');
                Route::get('/guru/{guru}',                    [KinerjaController::class, 'detailGuru'])->name('detail-guru');
                Route::get('/guru/{guru}/raport',             [KinerjaController::class, 'raport'])->name('raport');
                Route::post('/guru/{guru}/override',          [KinerjaController::class, 'overrideSkor'])->name('override');
                Route::post('/guru/{guru}/reset',             [KinerjaController::class, 'resetRekap'])->name('reset');
                Route::post('/reset-semua',                   [KinerjaController::class, 'resetSemua'])->name('reset-semua');
                Route::post('/guru/{guru}/punishment',        [KinerjaController::class, 'punishmentStore'])->name('punishment.store');
                Route::delete('/punishment/{punishment}',     [KinerjaController::class, 'punishmentDestroy'])->name('punishment.destroy');
            });

            // ── 4. TUGAS ──────────────────────────────────────────────────

            // Tugas Jabatan — route spesifik HARUS sebelum resource
            Route::post('tugas-jabatan/realisasi/{realisasi}/verifikasi',
                [TugasJabatanController::class, 'verifikasi'])->name('tugas-jabatan.verifikasi');
            Route::resource('tugas-jabatan', TugasJabatanController::class);

            // ── 4b. LEMBUR ────────────────────────────────────────────────
            Route::prefix('lembur')->name('lembur.')->group(function () {
                Route::get('/',                       [LemburController::class, 'index'])->name('index');
                Route::get('/create',                 [LemburController::class, 'create'])->name('create');
                Route::post('/',                      [LemburController::class, 'store'])->name('store');
                Route::get('/{lembur}',               [LemburController::class, 'show'])->name('show');
                Route::post('/{lembur}/setujui',      [LemburController::class, 'setujui'])->name('setujui');
                Route::post('/{lembur}/tolak',        [LemburController::class, 'tolak'])->name('tolak');
                Route::post('/{lembur}/batalkan',     [LemburController::class, 'batalkan'])->name('batalkan');
                Route::post('/peserta/{peserta}/sahkan',   [LemburController::class, 'sahkanManual'])->name('peserta.sahkan');
                Route::post('/peserta/{peserta}/batalkan', [LemburController::class, 'batalkanPeserta'])->name('peserta.batalkan');
            });

            // Tugas Tambahan — route spesifik HARUS sebelum resource
            Route::post('tugas-tambahan/{tugasTambahan}/assign',
                [TugasTambahanController::class, 'assign'])->name('tugas-tambahan.assign');
            Route::patch('penugasan/{penugasan}/verifikasi',
                [TugasTambahanController::class, 'verifikasi'])->name('tugas-tambahan.verifikasi');
            Route::patch('penugasan/{penugasan}/vakasi',
                [TugasTambahanController::class, 'updateVakasiPenerima'])->name('penugasan.vakasi');
            Route::post('tugas-tambahan/{tugasTambahan}/batalkan',
                [TugasTambahanController::class, 'destroy'])->name('tugas-tambahan.hapus');
            Route::resource('tugas-tambahan', TugasTambahanController::class);

            // Absensi Kegiatan (tugas tipe absen_kegiatan)
            Route::prefix('absensi-kegiatan')->name('absensi-kegiatan.')->group(function () {
                Route::get('/',                                  [AbsensiKegiatanController::class, 'index'])->name('index');
                Route::post('/',                                 [AbsensiKegiatanController::class, 'store'])->name('store');
                // Route spesifik harus sebelum /{absensiKegiatan}
                Route::patch('/peserta/{peserta}',               [AbsensiKegiatanController::class, 'updatePeserta'])->name('update-peserta');
                Route::post('/peserta/{peserta}/hapus',          [AbsensiKegiatanController::class, 'hapusPeserta'])->name('hapus-peserta');
                // /{absensiKegiatan} routes
                Route::get('/{absensiKegiatan}/edit',            [AbsensiKegiatanController::class, 'edit'])->name('edit');
                Route::patch('/{absensiKegiatan}',               [AbsensiKegiatanController::class, 'update'])->name('update');
                Route::post('/{absensiKegiatan}/hapus',          [AbsensiKegiatanController::class, 'destroy'])->name('destroy');
                Route::get('/{absensiKegiatan}',                 [AbsensiKegiatanController::class, 'show'])->name('show');
                Route::post('/{absensiKegiatan}/peserta',        [AbsensiKegiatanController::class, 'tambahPeserta'])->name('tambah-peserta');
                Route::patch('/{absensiKegiatan}/peserta-bulk',  [AbsensiKegiatanController::class, 'updateBulk'])->name('update-bulk');
                Route::post('/{absensiKegiatan}/selesaikan',     [AbsensiKegiatanController::class, 'selesaikan'])->name('selesaikan');
            });

            // ── 5. PENGAJUAN IZIN ─────────────────────────────────────────
            Route::post('pengajuan-izin/{pengajuanIzin}/setujui',
                [PengajuanIzinController::class, 'setujui'])->name('pengajuan-izin.setujui');
            Route::post('pengajuan-izin/{pengajuanIzin}/tolak',
                [PengajuanIzinController::class, 'tolak'])->name('pengajuan-izin.tolak');
            Route::post('pengajuan-izin/{pengajuanIzin}/batalkan',
                [PengajuanIzinController::class, 'batalkan'])->name('pengajuan-izin.batalkan');
            // Izin Sementara (admin buatkan atas nama guru) — JSON (axios)
            Route::post('pengajuan-izin/sementara/preview',          [PengajuanIzinController::class, 'sementaraPreview'])->name('pengajuan-izin.sementara.preview');
            Route::post('pengajuan-izin/sementara',                  [PengajuanIzinController::class, 'sementaraStore'])->name('pengajuan-izin.sementara.store');
            Route::post('pengajuan-izin/sementara/tunjuk-pengganti', [PengajuanIzinController::class, 'sementaraTunjukPengganti'])->name('pengajuan-izin.sementara.tunjuk-pengganti');
            Route::post('pengajuan-izin/{pengajuanIzin}/sementara-batal', [PengajuanIzinController::class, 'sementaraBatal'])->name('pengajuan-izin.sementara-batal');
            Route::resource('pengajuan-izin', PengajuanIzinController::class)
                ->only(['index', 'create', 'store', 'show']);

            // ── 6. HARI LIBUR ─────────────────────────────────────────────
            Route::post('hari-libur/darurat',
                [HariLiburController::class, 'storeDarurat'])->name('hari-libur.darurat');
            Route::post('hari-libur/import-nasional',
                [HariLiburController::class, 'importNasional'])->name('hari-libur.import-nasional');
            Route::patch('hari-libur/{hariLibur}/toggle',
                [HariLiburController::class, 'toggleAktif'])->name('hari-libur.toggle');
            Route::post('hari-libur/{hariLibur}/batalkan',
                [HariLiburController::class, 'batalkanDarurat'])->name('hari-libur.batalkan');
            Route::resource('hari-libur', HariLiburController::class)
                ->except(['create', 'edit', 'show']);

            // ── 6b. LIBUR INDIVIDU TENAGA PENDIDIK (guru mukim) ───────────
            Route::post('libur-tendik/kelola-mukim',
                [LiburTendikController::class, 'kelolaMukim'])->name('libur-tendik.kelola-mukim');
            Route::post('libur-tendik/generate',
                [LiburTendikController::class, 'generate'])->name('libur-tendik.generate');
            Route::post('libur-tendik/tukar',
                [LiburTendikController::class, 'tukar'])->name('libur-tendik.tukar');
            Route::patch('libur-tendik/{liburTendik}/pindah',
                [LiburTendikController::class, 'pindah'])->name('libur-tendik.pindah');
            Route::resource('libur-tendik', LiburTendikController::class)
                ->only(['index', 'store', 'destroy']);

            // Template Bot WhatsApp (Fonnte)
            Route::get('setting-wa',       [SettingWaController::class, 'index'])->name('setting-wa.index');
            Route::patch('setting-wa',     [SettingWaController::class, 'update'])->name('setting-wa.update');
            Route::post('setting-wa/test', [SettingWaController::class, 'test'])->name('setting-wa.test');

            // Monitor Outbox WhatsApp
            Route::get('wa-outbox',                       [WaOutboxController::class, 'index'])->name('wa-outbox.index');
            Route::post('wa-outbox/retry-gagal',          [WaOutboxController::class, 'retryGagal'])->name('wa-outbox.retry-gagal');
            Route::post('wa-outbox/{waOutbox}/retry',     [WaOutboxController::class, 'retry'])->name('wa-outbox.retry');

            // Kotak Masuk WhatsApp (balasan wali)
            Route::get('wa-inbox',                  [WaInboxController::class, 'index'])->name('wa-inbox.index');
            Route::post('wa-inbox/baca-semua',      [WaInboxController::class, 'bacaSemua'])->name('wa-inbox.baca-semua');
            Route::patch('wa-inbox/{waInbox}/baca', [WaInboxController::class, 'baca'])->name('wa-inbox.baca');

            // ── 7. PENGGAJIAN ─────────────────────────────────────────────
            Route::patch('periode/{periode}/kunci',
                [PeriodePenggajianController::class, 'kunci'])->name('periode.kunci');
            Route::resource('periode', PeriodePenggajianController::class);

            Route::prefix('penggajian')->name('penggajian.')->group(function () {
                Route::get('/',                             [PenggajianController::class, 'index'])->name('index');
                Route::post('/{periode}/generate',          [PenggajianController::class, 'generate'])->name('generate');
                Route::post('/{periode}/generate/{guru}',   [PenggajianController::class, 'generateSatu'])->name('generate-satu');
                Route::patch('/{periode}/finalisasi-semua', [PenggajianController::class, 'finalisasiSemua'])->name('finalisasi-semua');
                Route::get('/{periode}/export',             [PenggajianController::class, 'export'])->name('export');
                Route::get('/{periode}/preview',            [PenggajianController::class, 'preview'])->name('preview');
                Route::get('/{periode}',                    [PenggajianController::class, 'detail'])->name('detail');
                Route::patch('/{penggajian}/override',      [PenggajianController::class, 'override'])->name('override');
                Route::patch('/{penggajian}/penyesuaian-liburan', [PenggajianController::class, 'penyesuaianLiburan'])->name('penyesuaian-liburan');
                Route::patch('/{penggajian}/finalisasi',    [PenggajianController::class, 'finalisasi'])->name('finalisasi');
                Route::get('/{penggajian}/slip',            [PenggajianController::class, 'slip'])->name('slip');
            });

            // ── 8. LAPORAN ────────────────────────────────────────────────
            Route::prefix('laporan')->name('laporan.')->group(function () {
                Route::get('/ringkasan',            [LaporanController::class, 'ringkasan'])->name('ringkasan');
                Route::get('/kehadiran',            [LaporanController::class, 'kehadiran'])->name('kehadiran');
                Route::get('/mengajar',             [LaporanController::class, 'mengajar'])->name('mengajar');
                Route::get('/absensi',              [LaporanController::class, 'absensi'])->name('absensi');
                Route::get('/penggajian',           [LaporanController::class, 'penggajian'])->name('penggajian');
                Route::get('/penggajian/{penggajian}/slip', [LaporanController::class, 'slipGaji'])->name('slip-gaji');
                Route::get('/vakasi',               [LaporanController::class, 'vakasi'])->name('vakasi');
                Route::get('/vakasi/{penggajian}/detail', [LaporanController::class, 'vakasiDetail'])->name('vakasi-detail');
                Route::get('/guru/{guru}',          [LaporanController::class, 'detailGuru'])->name('detail-guru');
                Route::get('/guru/{guru}/export',   [LaporanController::class, 'exportDetailGuru'])->name('export-guru');
                Route::get('/export/{tipe}',        [LaporanController::class, 'export'])->name('export');
            });

            // ══════════════════════════════════════════════════════════════
            // PENGATURAN
            // ══════════════════════════════════════════════════════════════

            // ── Setting Gaji (Pokok + Vakasi + Jam Kerja) ─────────────────
            Route::prefix('setting-gaji')->name('setting-gaji.')->group(function () {

                Route::resource('pokok', SettingGajiController::class)
                    ->parameters(['pokok' => 'pokoh']);

                Route::get('vakasi',               [SettingGajiController::class, 'vakasiIndex'])->name('vakasi.index');
                Route::get('vakasi/create',        [SettingGajiController::class, 'vakasiCreate'])->name('vakasi.create');
                Route::post('vakasi',              [SettingGajiController::class, 'vakasiStore'])->name('vakasi.store');
                Route::get('vakasi/{vakasi}/edit', [SettingGajiController::class, 'vakasiEdit'])->name('vakasi.edit');
                Route::put('vakasi/{vakasi}',      [SettingGajiController::class, 'vakasiUpdate'])->name('vakasi.update');
                Route::delete('vakasi/{vakasi}',   [SettingGajiController::class, 'vakasiDestroy'])->name('vakasi.destroy');

                Route::patch('jam-kerja/{jamKerja}/set-default',
                    [SettingGajiController::class, 'setDefault'])->name('jam-kerja.set-default');
                Route::post('jam-kerja/{jamKerja}/duplicate',
                    [SettingGajiController::class, 'jamKerjaDuplicate'])->name('jam-kerja.duplicate');
                Route::post('jam-kerja/{jamKerja}/generate',
                    [SettingGajiController::class, 'jamKerjaGenerate'])->name('jam-kerja.generate');
                Route::get('jam-kerja',                 [SettingGajiController::class, 'jamKerjaIndex'])->name('jam-kerja.index');
                Route::get('jam-kerja/create',          [SettingGajiController::class, 'jamKerjaCreate'])->name('jam-kerja.create');
                Route::post('jam-kerja',                [SettingGajiController::class, 'jamKerjaStore'])->name('jam-kerja.store');
                Route::get('jam-kerja/{jamKerja}/edit', [SettingGajiController::class, 'jamKerjaEdit'])->name('jam-kerja.edit');
                Route::put('jam-kerja/{jamKerja}',      [SettingGajiController::class, 'jamKerjaUpdate'])->name('jam-kerja.update');
                Route::delete('jam-kerja/{jamKerja}',   [SettingGajiController::class, 'jamKerjaDestroy'])->name('jam-kerja.destroy');
            });

            // ── Setting Kinerja ───────────────────────────────────────────
            Route::post('setting-kinerja/{settingKinerja}/set-default',
                [SettingKinerjaController::class, 'setDefault'])->name('setting-kinerja.set-default');
            Route::post('setting-kinerja/{settingKinerja}/preview',
                [SettingKinerjaController::class, 'preview'])->name('setting-kinerja.preview');
            Route::resource('setting-kinerja', SettingKinerjaController::class)
                ->except(['create', 'edit', 'show']);

            // ── Setting Lokasi Absensi ────────────────────────────────────
            Route::post('setting-lokasi/test',
                [SettingLokasiAbsensiController::class, 'testValidasi'])->name('setting-lokasi.test');
            Route::patch('setting-lokasi/{settingLokasi}/toggle',
                [SettingLokasiAbsensiController::class, 'toggleAktif'])->name('setting-lokasi.toggle');
            Route::post('setting-lokasi/{settingLokasi}/assign-guru',
                [SettingLokasiAbsensiController::class, 'assignGuru'])->name('setting-lokasi.assign-guru');
            Route::post('setting-lokasi/{settingLokasi}/unassign-guru',
                [SettingLokasiAbsensiController::class, 'unassignGuru'])->name('setting-lokasi.unassign-guru');
            Route::post('setting-lokasi/guru/{guru}/assign',
                [SettingLokasiAbsensiController::class, 'assignLokasiToGuru'])->name('setting-lokasi.assign-to-guru');
            Route::resource('setting-lokasi', SettingLokasiAbsensiController::class)
                ->except(['create', 'edit', 'show']);

            // ── Setting Notifikasi ────────────────────────────────────────
            Route::get('setting-notifikasi',
                [\App\Http\Controllers\Superadmin\SettingNotifikasiController::class, 'index'])->name('setting-notifikasi.index');
            Route::post('setting-notifikasi/broadcast',
                [\App\Http\Controllers\Superadmin\SettingNotifikasiController::class, 'broadcast'])->name('setting-notifikasi.broadcast');
            Route::put('setting-notifikasi/{setting}',
                [\App\Http\Controllers\Superadmin\SettingNotifikasiController::class, 'update'])->name('setting-notifikasi.update');

            // ── Kegiatan Penting Guru (tracking oleh guru piket) ──────────
            Route::get('kegiatan-penting/laporan',
                [\App\Http\Controllers\Superadmin\KegiatanPentingController::class, 'laporan'])->name('kegiatan-penting.laporan');
            Route::patch('kegiatan-penting/{kegiatanPenting}/toggle',
                [\App\Http\Controllers\Superadmin\KegiatanPentingController::class, 'toggle'])->name('kegiatan-penting.toggle');
            Route::resource('kegiatan-penting',
                \App\Http\Controllers\Superadmin\KegiatanPentingController::class)->only(['index', 'store', 'update', 'destroy']);

            // ── Jadwal Shift (satpam) ─────────────────────────────────────
            Route::post('jadwal-shift/rotasi',
                [\App\Http\Controllers\Superadmin\JadwalShiftController::class, 'rotasi'])->name('jadwal-shift.rotasi');
            Route::get('jadwal-shift',
                [\App\Http\Controllers\Superadmin\JadwalShiftController::class, 'index'])->name('jadwal-shift.index');
            Route::post('jadwal-shift',
                [\App\Http\Controllers\Superadmin\JadwalShiftController::class, 'store'])->name('jadwal-shift.store');
            Route::delete('jadwal-shift/{jadwalShift}',
                [\App\Http\Controllers\Superadmin\JadwalShiftController::class, 'destroy'])->name('jadwal-shift.destroy');

            // ── Setting Potongan ──────────────────────────────────────────
            Route::patch('setting-potongan/{settingPotongan}/toggle',
                [SettingPotonganController::class, 'toggleStatus'])->name('setting-potongan.toggle');
            Route::resource('setting-potongan', SettingPotonganController::class);

            // ── Potongan Gaji per Guru (murni, terpisah dari absensi) ─────
            Route::get('potongan',                 [\App\Http\Controllers\Superadmin\PotonganController::class, 'index'])->name('potongan.index');
            Route::get('potongan/guru/{tenagaPendidik}',  [\App\Http\Controllers\Superadmin\PotonganController::class, 'guru'])->name('potongan.guru');
            Route::post('potongan/guru/{tenagaPendidik}', [\App\Http\Controllers\Superadmin\PotonganController::class, 'simpanGuru'])->name('potongan.guru.simpan');
            Route::post('potongan/jenis',                 [\App\Http\Controllers\Superadmin\PotonganController::class, 'storeJenis'])->name('potongan.jenis.store');
            Route::put('potongan/jenis/{jenisPotongan}',  [\App\Http\Controllers\Superadmin\PotonganController::class, 'updateJenis'])->name('potongan.jenis.update');
            Route::patch('potongan/jenis/{jenisPotongan}/toggle', [\App\Http\Controllers\Superadmin\PotonganController::class, 'toggleJenis'])->name('potongan.jenis.toggle');
            Route::delete('potongan/jenis/{jenisPotongan}', [\App\Http\Controllers\Superadmin\PotonganController::class, 'destroyJenis'])->name('potongan.jenis.destroy');

            // ── Setting Jenis Pengajuan Izin ──────────────────────────────
            Route::patch('setting-pengajuan/{settingPengajuan}/toggle-aktif',
                [SettingJenisPengajuanController::class, 'toggleAktif'])->name('setting-pengajuan.toggle-aktif');
            Route::resource('setting-pengajuan', SettingJenisPengajuanController::class);

            // ── Status Kepegawaian ────────────────────────────────────────
            Route::post('tenaga-pendidik/{tenagaPendidik}/ubah-status',
                [TenagaPendidikController::class, 'ubahStatus'])->name('tenaga-pendidik.ubah-status');
            Route::patch('tenaga-pendidik/{tenagaPendidik}/aktifkan',
                [TenagaPendidikController::class, 'aktifkanKembali'])->name('tenaga-pendidik.aktifkan');
            Route::get('tenaga-pendidik/{tenagaPendidik}/riwayat-status',
                [TenagaPendidikController::class, 'riwayatStatus'])->name('tenaga-pendidik.riwayat-status');
        });

        // ╔══════════════════════════════════════════════════════════════════╗
        // ║  SMART EDUCATION                                                 ║
        // ╚══════════════════════════════════════════════════════════════════╝

        Route::prefix('smart-education')->name('smart-education.')->group(function () {

            // ── Santri ────────────────────────────────────────────────────
            Route::get('santri',             [SantriController::class, 'index'])->name('santri.index');
            Route::get('santri/template-import', [SantriController::class, 'templateImport'])->name('santri.template-import');
            Route::post('santri/import',     [SantriController::class, 'import'])->name('santri.import');
            Route::post('santri',            [SantriController::class, 'store'])->name('santri.store');
            Route::put('santri/{santri}',    [SantriController::class, 'update'])->name('santri.update');
            Route::delete('santri/{santri}', [SantriController::class, 'destroy'])->name('santri.destroy');
            Route::patch('santri/{santri}/aktifkan', [SantriController::class, 'aktifkan'])->name('santri.aktifkan');
            Route::patch('santri/{santri}/reset-password-portal', [SantriController::class, 'resetPasswordPortal'])->name('santri.reset-password-portal');
            Route::patch('santri/{santri}/set-password-portal', [SantriController::class, 'setPasswordPortal'])->name('santri.set-password-portal');

            // ── Kelas ─────────────────────────────────────────────────────
            Route::get('kelas',            [KelasController::class, 'index'])->name('kelas.index');
            Route::post('kelas',           [KelasController::class, 'store'])->name('kelas.store');
            Route::put('kelas/{kelas}',    [KelasController::class, 'update'])->name('kelas.update');
            Route::delete('kelas/{kelas}', [KelasController::class, 'destroy'])->name('kelas.destroy');
            Route::get('kelas/{kelas}/santri', [KelasController::class, 'santriKelas'])->name('kelas.santri');
            Route::post('kelas/{kelas}/naik-kelas', [KelasController::class, 'naikKelas'])->name('kelas.naik-kelas');

            // ── Ekstrakurikuler ───────────────────────────────────────────
            Route::get('ekstrakurikuler',  [\App\Http\Controllers\Superadmin\EkstrakurikulerController::class, 'index'])->name('ekstrakurikuler.index');
            Route::post('ekstrakurikuler', [\App\Http\Controllers\Superadmin\EkstrakurikulerController::class, 'store'])->name('ekstrakurikuler.store');
            Route::put('ekstrakurikuler/{ekstrakurikuler}',    [\App\Http\Controllers\Superadmin\EkstrakurikulerController::class, 'update'])->name('ekstrakurikuler.update');
            Route::delete('ekstrakurikuler/{ekstrakurikuler}', [\App\Http\Controllers\Superadmin\EkstrakurikulerController::class, 'destroy'])->name('ekstrakurikuler.destroy');
            Route::get('ekstrakurikuler/{ekstrakurikuler}/anggota',  [\App\Http\Controllers\Superadmin\EkstrakurikulerController::class, 'anggota'])->name('ekstrakurikuler.anggota');
            Route::post('ekstrakurikuler/{ekstrakurikuler}/anggota', [\App\Http\Controllers\Superadmin\EkstrakurikulerController::class, 'simpanAnggota'])->name('ekstrakurikuler.anggota.simpan');
            Route::get('ekstrakurikuler/{ekstrakurikuler}/monitoring', [\App\Http\Controllers\Superadmin\EkstrakurikulerController::class, 'monitoring'])->name('ekstrakurikuler.monitoring');

            // ── Jurnal Mengajar (monitoring absensi santri) ───────────────
            Route::get('jurnal-mengajar', [JurnalMengajarController::class, 'index'])->name('jurnal.index');

            // ── Laporan (pembelajaran, tahfidz, tahsin) ───────────────────
            Route::get('laporan', [EducationLaporanController::class, 'index'])->name('laporan.index');
            Route::get('laporan/tahfidz', [EducationLaporanController::class, 'tahfidz'])->name('laporan.tahfidz');
            Route::get('laporan/tahsin', [EducationLaporanController::class, 'tahsin'])->name('laporan.tahsin');

            // ── Smart Tahfidz (hub + setting penilaian) ───────────────────
            Route::get('tahfidz', [EducationTahfidzController::class, 'index'])->name('tahfidz.index');
            Route::put('tahfidz/setting', [EducationTahfidzController::class, 'updateSetting'])->name('tahfidz.setting');
            Route::post('tahfidz/generate-jadwal', [EducationTahfidzController::class, 'generateJadwal'])->name('tahfidz.generate-jadwal');
            Route::post('tahfidz/sinkronisasi', [EducationTahfidzController::class, 'sinkronisasi'])->name('tahfidz.sinkronisasi');

            // ── Monitoring Tahfidz (progres hafalan santri) ───────────────
            Route::get('tahfidz-monitoring', [MonitoringTahfidzController::class, 'index'])->name('tahfidz-monitoring.index');
            Route::get('tahfidz-monitoring/{santri}', [MonitoringTahfidzController::class, 'detail'])->name('tahfidz-monitoring.detail');

            // ── Materi Tahsin (setting per level) ─────────────────────────
            Route::get('tahsin', [EducationTahsinController::class, 'index'])->name('tahsin.index');
            Route::post('tahsin/generate-jadwal', [EducationTahsinController::class, 'generateJadwal'])->name('tahsin.generate-jadwal');
            Route::get('materi-tahsin',           [MateriTahsinController::class, 'index'])->name('materi-tahsin.index');
            Route::post('materi-tahsin',          [MateriTahsinController::class, 'store'])->name('materi-tahsin.store');
            Route::put('materi-tahsin/{materi}',  [MateriTahsinController::class, 'update'])->name('materi-tahsin.update');
            Route::delete('materi-tahsin/{materi}', [MateriTahsinController::class, 'destroy'])->name('materi-tahsin.destroy');

            // ── Monitoring Tahsin (progres level & materi santri) ─────────
            Route::get('tahsin-monitoring', [MonitoringTahsinController::class, 'index'])->name('tahsin-monitoring.index');
            Route::get('tahsin-monitoring/{santri}', [MonitoringTahsinController::class, 'detail'])->name('tahsin-monitoring.detail');
        });

        // ══════════════ SMART HABBIT ══════════════
        Route::prefix('smart-habbit')->name('smart-habbit.')->group(function () {
            // Setting Smart Controlling
            Route::get('controlling', [ControllingSettingController::class, 'index'])->name('controlling.index');
            Route::post('controlling/periode', [ControllingSettingController::class, 'periodeStore'])->name('controlling.periode.store');
            Route::post('controlling/periode/{periode}/activate', [ControllingSettingController::class, 'periodeActivate'])->name('controlling.periode.activate');
            Route::post('controlling/periode/{periode}/duplikat', [ControllingSettingController::class, 'periodeDuplikat'])->name('controlling.periode.duplikat');
            Route::delete('controlling/periode/{periode}', [ControllingSettingController::class, 'periodeDestroy'])->name('controlling.periode.destroy');
            Route::post('controlling/kegiatan', [ControllingSettingController::class, 'kegiatanStore'])->name('controlling.kegiatan.store');
            Route::put('controlling/kegiatan/{kegiatan}', [ControllingSettingController::class, 'kegiatanUpdate'])->name('controlling.kegiatan.update');
            Route::delete('controlling/kegiatan/{kegiatan}', [ControllingSettingController::class, 'kegiatanDestroy'])->name('controlling.kegiatan.destroy');
            Route::post('controlling/jadwal', [ControllingSettingController::class, 'jadwalStore'])->name('controlling.jadwal.store');
            Route::delete('controlling/jadwal/{jadwal}', [ControllingSettingController::class, 'jadwalDestroy'])->name('controlling.jadwal.destroy');
            // Kiosk scan (web admin)
            Route::get('controlling/scan', [ControllingSettingController::class, 'scanPage'])->name('controlling.scan');
            Route::post('controlling/scan', [ControllingSettingController::class, 'scanSubmit'])->name('controlling.scan.submit');
            // Kartu santri ber-barcode (NIP)
            Route::get('controlling/kartu', [ControllingSettingController::class, 'kartu'])->name('controlling.kartu');
            Route::get('controlling/barcode/{nip}', [ControllingSettingController::class, 'barcode'])->name('controlling.barcode');
            // Rekap controlling (per santri & per kegiatan) + detail harian per sesi
            Route::get('controlling/rekap', [ControllingRekapController::class, 'index'])->name('controlling.rekap');
            Route::get('controlling/harian', [ControllingRekapController::class, 'harian'])->name('controlling.harian');

            // Smart Eksekusi + Monitor Outbox
            Route::get('eksekusi', [SmartEksekusiController::class, 'index'])->name('eksekusi.index');
            Route::post('eksekusi', [SmartEksekusiController::class, 'store'])->name('eksekusi.store');
            Route::get('outbox', [SmartEksekusiController::class, 'outbox'])->name('outbox.index');
            Route::post('outbox/{outbox}/retry', [SmartEksekusiController::class, 'retry'])->name('outbox.retry');
            Route::post('sync-kode', [SmartEksekusiController::class, 'syncKode'])->name('sync-kode');
        });

        // ── GURU PIKET (penilaian kinerja keliling) ───────────────────
        Route::prefix('piket')->name('piket.')->group(function () {
            // Tahap 2 — Rubrik kategori penilaian
            Route::get('kategori',               [PiketKategoriController::class, 'index'])->name('kategori.index');
            Route::post('kategori',              [PiketKategoriController::class, 'store'])->name('kategori.store');
            Route::put('kategori/{kategori}',    [PiketKategoriController::class, 'update'])->name('kategori.update');
            Route::delete('kategori/{kategori}', [PiketKategoriController::class, 'destroy'])->name('kategori.destroy');

            // Tahap 3 — Penunjukan piket harian
            Route::get('jadwal',             [PiketJadwalController::class, 'index'])->name('jadwal.index');
            Route::post('jadwal',            [PiketJadwalController::class, 'store'])->name('jadwal.store');
            Route::delete('jadwal/{jadwal}', [PiketJadwalController::class, 'destroy'])->name('jadwal.destroy');
            // Tahap 7 — peninjauan sanggah penilaian
            Route::get('sanggah',                  [PiketSanggahController::class, 'index'])->name('sanggah.index');
            Route::post('sanggah/{penilaian}/tinjau', [PiketSanggahController::class, 'tinjau'])->name('sanggah.tinjau');
        });
    });