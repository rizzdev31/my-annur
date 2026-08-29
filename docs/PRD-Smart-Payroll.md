# PRD — An Nur Smart System: Modul **Smart Payroll**

> **Status dokumen:** Baseline / handoff. Disusun saat modul Smart Payroll dianggap fungsional dan akan dijeda untuk pengembangan modul berikutnya (**Smart Education** — absensi santri, jurnal mengajar, smart habit, dll. di sesi terpisah).
> **Versi app:** Laravel 12 + Inertia.js + Vue 3 (admin web) · Flutter + Sanctum API (aplikasi guru) · MariaDB (`systemdb`, XAMPP).
> **Tanggal baseline:** 2026-06-17.

---

## 1. Tujuan & Ruang Lingkup

Smart Payroll adalah modul **penggajian berbasis aktivitas (activity-based payroll)** untuk tenaga pendidik pesantren. Gaji setiap guru per periode dihitung otomatis dari gabungan: gaji pokok per jabatan + berbagai komponen **vakasi** (honor berbasis kehadiran/aktivitas) − **potongan** (absensi, wajib, manual, punishment).

Prinsip desain:

- **Transparan** — setiap rupiah pendapatan/potongan tercatat sebagai baris `detail_penggajian` dan muncul di slip gaji.
- **Idempoten** — generate ulang aman; nilai manual (penyesuaian liburan) dipertahankan.
- **Tahan-error** — tiap komponen dibungkus `try/catch`; kegagalan satu komponen tidak membatalkan keseluruhan; peringatan disimpan di `catatan`.
- **Fleksibel** — tarif vakasi & potongan dapat berlaku per individu / per jabatan / semua.

Modul ini **tidak** mencakup: payroll non-guru (staf umum), pajak (PPh), bank transfer otomatis. Itu di luar lingkup baseline.

---

## 2. Aktor & Peran

| Aktor | Channel | Middleware | Kemampuan |
|---|---|---|---|
| **Super Admin** | Web (Inertia/Vue) | `auth` + `role:super_admin` | Seluruh konfigurasi, absensi, verifikasi, generate & finalisasi gaji, laporan. |
| **Tenaga Pendidik (Guru)** | Mobile (Flutter) | `auth:sanctum` + `role.api:tenaga_pendidik` | Absen (foto+GPS), absen mengajar, ajukan izin/lembur, kerjakan tugas, lihat kinerja & slip gaji **miliknya sendiri**. |

Prefix route: web `admin.smart-payroll.*`, API `/api/v1/*`.

---

## 3. Arsitektur

```
┌─ Admin Web ───────────────┐        ┌─ Aplikasi Guru (Flutter) ─┐
│ Inertia + Vue 3 (SPA)     │        │ Riverpod + Dio            │
│ AdminLayout + Sidebar     │        │ Sanctum Bearer token      │
└──────────┬────────────────┘        └──────────┬────────────────┘
           │ Inertia (web.php)                   │ REST (api.php /v1)
           ▼                                     ▼
┌──────────────────────────── Laravel 12 ────────────────────────────┐
│ Controllers/Superadmin/*           Controllers/Api/*                │
│ ── Services (logika inti) ──────────────────────────────────────── │
│ PayrollCalculationService   ← jantung penggajian                    │
│ KinerjaCalculationService · KinerjaJabatanService                   │
│ AbsensiKalkulasiService · LokasiAbsensiService · LemburService      │
│ PengajuanIzinService · StatusKepegawaianService · SlipGajiBuilder   │
│ ExceptionHandlingService · TimezoneHelper                           │
└──────────────────────────── MariaDB (systemdb) ────────────────────┘
```

**Catatan ops:** MySQL dikelola via **XAMPP Control Panel** (selalu Stop MySQL via XAMPP sebelum shutdown). Frontend dev: `npm run dev`. Backend: `php artisan serve` / Apache. Perubahan PHP butuh restart web-server / opcache clear.

---

## 4. Modul Fungsional (peta menu sidebar)

### 4.1 Absensi (`absensi.*`)
- **Absensi Harian** — check-in/out guru (foto + GPS, divalidasi geofence). Status: `hadir`, `terlambat`, `dinas_luar`, `izin`, `izin_sakit`, `sakit`, `alfa`. Admin bisa input manual & massal.
- **Absensi Mengajar** — per sesi JP terhadap `JadwalMengajar`. Status berhak vakasi: `hadir`/`terlaksana`/`libur`/`izin`. `tidak_terlaksana` → `jp_terlaksana=0` (tidak dibayar).
- **Rekap Absensi** & **Koreksi Absensi** — model `KoreksiAbsensi` untuk audit perubahan.

### 4.2 Monitoring Harian (`monitoring.*`)
Pemantauan kehadiran & `LogKerjaHarian` real-time per guru; verifikasi log.

### 4.3 Kinerja (`kinerja.*`)
- **Rekap Kinerja** (`RekapKinerjaBulanan`) — skor bulanan; grade A–E via `SettingKinerja::getGrade()`.
- **Log Kerja** (`LogKerjaHarian`) — verifikasi/tolak.
- **Punishment** (`PunishmentKinerja`) — jenis: `potongan` (→ payroll), `evaluasi`, `peringatan`, `pencopotan` (→ cabut jabatan). Komponen `potongan` memotong gaji pada periode bulan/tahun yang sama.
- Skor kinerja memakai **komponen tugas berbasis frekuensi** (harian/mingguan/bulanan/insidental) dengan target occurrence per bulan.

### 4.4 Tugas (`tugas-jabatan`, `tugas-tambahan`, `absensi-kegiatan`, `lembur`)
- **Tugas Jabatan** (`TugasJabatan` + `RealisasiTugasJabatan`) — kewajiban harian per jabatan, deadline harian di jam masuk, rekap per bulan. **Tidak menghasilkan vakasi** → dinilai di **Kinerja**. Opsi `perlu_verifikasi`. Field `realisasi.terlambat`.
- **Tugas Tambahan** (`TugasTambahan` + `PenugasanTambahan`) — `tipe_pengerjaan`: `mandiri` / `absen_kegiatan`. Menghasilkan vakasi saat `disetujui` + `status_pengerjaan=selesai` + `dilaporkan_pada` dalam periode.
- **Absensi Kegiatan** (`AbsensiKegiatan` + `AbsensiKegiatanPeserta`) — peserta yang hadir mendapat snapshot `nominal_vakasi`.
- **Lembur** (`Lembur` + `LemburPeserta`) — admin set jam mulai + durasi; guru upload **bukti foto + GPS strict (geofence wajib)**. Pengesahan: otomatis (dalam window waktu) atau **manual admin** (toleransi 1 jam lewat batas → admin boleh approve/batalkan). Vakasi flat per event dari setting.

### 4.5 Pengajuan Izin (`pengajuan-izin.*`)
`PengajuanIzin` + `KuotaIzinTahunan` + `SettingJenisPengajuan`. Alur: ajukan (guru) → setujui/tolak/batalkan (admin). Badge pending di sidebar.

### 4.6 Hari Libur (`hari-libur.*`)
`HariLibur` — libur tetap, darurat, import nasional. **Mengurangi `total_hari_kerja`** pada perhitungan payroll.

### 4.7 Penggajian (`periode.*`, `penggajian.*`)
- **Periode Gaji** (`PeriodePenggajian`) — `bulan`, `tahun`, `tanggal_mulai`, `tanggal_selesai`, `status`; bisa **dikunci**.
- **Data Gaji** (`Penggajian` + `DetailPenggajian`) — generate semua / generate satu guru, preview, override, penyesuaian liburan, finalisasi (per guru / semua), export, slip.

### 4.8 Laporan (`laporan.*`)
Ringkasan · Kehadiran · Absensi Mengajar · Absensi · Penggajian (+slip) · **Vakasi** (+detail). Tarif vakasi mengajar di laporan diselaraskan dengan payroll via `PayrollCalculationService::tarifPerJpMengajar()`.

### 4.9 Pengaturan
- **Setting Gaji**: Gaji Pokok (`SettingGajiPokok` + `GajiPokokIndividu`), **Vakasi** (`SettingVakasi`), Jam Kerja (`SettingJamKerja`).
- **Setting Kinerja** (`SettingKinerja`) — bobot komponen & ambang grade.
- **Lokasi Absensi** (`SettingLokasiAbsensi`) — titik + radius geofence, assign per guru.
- **Setting Potongan** (`SettingPotongan`).
- **Setting Jenis Pengajuan** (`SettingJenisPengajuan`).

---

## 5. Mesin Payroll — `PayrollCalculationService::hitung()`

Entry point: `hitung(TenagaPendidik $guru, PeriodePenggajian $periode, bool $dryRun = false): array`.
Menghasilkan 1 baris `Penggajian` + N baris `DetailPenggajian` (kecuali `dryRun`).

### 5.1 Komponen Pendapatan

| # | Komponen | Kolom | Sumber & Rumus |
|---|---|---|---|
| 1 | **Gaji Pokok** | `gaji_pokok` | `guru->getGajiPokokAktif()`; multi-jabatan dijumlah, breakdown per jabatan. |
| 2 | **Vakasi Absen Harian** | `vakasi_absen_harian` | `hadir × nominal`. `hadir` = status `hadir`+`terlambat`+`dinas_luar`. |
| 3 | **Vakasi Mengajar** | `vakasi_mengajar` | `total_jp × nominal`. `total_jp` = Σ `jp_terlaksana` dari status `hadir/terlaksana/libur/izin`. Breakdown: JP aktual, JP saat libur, JP saat izin. |
| 4 | **Vakasi Tugas Jabatan** | `vakasi_tugas_jabatan` | **Selalu 0** (kebijakan baru → dinilai di Kinerja). Lihat §8 & §9. |
| 5 | **Vakasi Tugas Tambahan** | `vakasi_tugas_tambahan` | Penugasan `disetujui` + `selesai` + `dilaporkan_pada` ∈ periode. Nominal prioritas: override penerima → setting penerima → override tugas → setting tugas. Tugas Rp 0 tetap dicatat (transparansi). |
| 6 | **Vakasi Peserta Kegiatan** | `vakasi_peserta_kegiatan` | Peserta kegiatan `selesai` dengan `vakasi_diberikan=true` & `nominal_vakasi>0`. Flat per kegiatan. |
| 6b | **Vakasi Lembur** | `vakasi_lembur` | `LemburPeserta` status `sah`, lembur tidak `ditolak/dibatalkan`, tanggal ∈ periode. Flat dari snapshot `nominal_vakasi`. Label menandai pengesahan manual admin. |

`total_pendapatan` = jumlah seluruh komponen pendapatan di atas (termasuk peserta kegiatan & lembur).

### 5.2 Resolusi Tarif Vakasi — `getVakasiUntukGuru($tipe, $guru)`

Memilih 1 `SettingVakasi` aktif & berlaku (cek `berlaku_mulai`/`berlaku_selesai`) dengan **prioritas**:

1. **Per individu** — `tenaga_pendidik_ids` JSON berisi `guru->id`.
2. **Per jabatan** — `jabatan_ids` JSON berisi salah satu jabatan aktif guru (`jabatanGuru` tanpa `berlaku_selesai`; fallback `jabatan_id`).
3. **Semua** — `berlaku_untuk_semua=true` atau `lingkup='semua'`.

`tipe_aktivitas`: `absen_harian`, `absen_mengajar`, `tugas_jabatan` (tidak dibayar lagi), `lembur`. (Vakasi tugas tambahan ditentukan per tugas, bukan via resolver ini.)

### 5.3 Komponen Potongan — `hitungPotongan()`

Iterasi `SettingPotongan::aktif()` yang `berlakuUntukGuru($guru)`. **Semua potongan aktif dihitung** — `tampil_di_slip` tidak boleh menyembunyikan dari perhitungan.

| `tipe_pemicu` | Perhitungan | Bucket |
|---|---|---|
| `per_keterlambatan` | `nominal × jumlah terlambat` | keterlambatan |
| `per_menit_keterlambatan` | `nominal/menit × Σ menit_terlambat` | keterlambatan |
| `per_alfa` | `nominal × jumlah alfa` | alfa |
| `per_bulan` | flat; bucket per `kategori` (`absensi`→keterlambatan, `wajib/simpanan/pinjaman`→tetap, lainnya→lainnya) | sesuai kategori |
| `persen_gaji` | `% × gaji_pokok`; `wajib/simpanan/pinjaman`→tetap, lainnya→lainnya | tetap/lainnya |
| `manual` | **dilewati** saat auto-generate (di-input per guru via override) | — |

Tambahan potongan di luar `hitungPotongan`:
- **Penyesuaian Liburan** (`potongan_liburan` + `keterangan_liburan`) — **manual** per guru; **dipertahankan** saat generate ulang.
- **Punishment Kinerja** — `PunishmentKinerja` `jenis='potongan'` pada `bulan/tahun` periode → ditambahkan ke `potongan_lainnya`.

`potongan_lainnya` (final) = potongan lainnya + punishment.
`total_potongan` = keterlambatan + alfa + tetap + potongan_lainnya + potongan_liburan.

### 5.4 Totalisasi & Status

```
gaji_bersih = max(0, total_pendapatan − total_potongan)
```

Lifecycle `Penggajian.status`: `draft` (hasil generate) → `final` (finalisasi, set `difinalisasi_oleh`) → `dibayar` (set `dibayar_pada`). `PeriodePenggajian` dapat **dikunci** untuk mencegah perubahan.

### 5.5 Idempotency, Konkurensi & Error Handling

- `simpanKeDatabase()` dalam `DB::transaction` + `lockForUpdate()` pada baris `(guru, periode)` → cegah double-generate di request bersamaan.
- Update-or-create baris `Penggajian`; **detail dihapus & dibangun ulang** tiap generate.
- Tiap komponen dibungkus `try/catch`; pesan dikumpulkan ke `_errors`, disimpan ke kolom `catatan` sebagai `"Peringatan komponen: ..."`.
- `normalizeTipe()` memetakan tipe detail ke ENUM `detail_penggajian.tipe` yang valid (cegah "Data truncated").

ENUM `detail_penggajian.tipe`: `gaji_pokok, vakasi_absen, vakasi_mengajar, vakasi_tugas_jabatan, vakasi_tugas_tambahan, vakasi_peserta_kegiatan, vakasi_lembur, tunjangan, potongan_terlambat, potongan_alfa, potongan_bpjs, potongan_lain, penyesuaian_liburan, lainnya`.

### 5.6 Slip Gaji
`SlipGajiBuilder` membangun struktur slip bersama (admin web, laporan, **dan Flutter** — desain disinkronkan), termasuk `instansi()` (kop/logo). Sumber data: `Penggajian` + `DetailPenggajian`.

---

## 6. Skema Data Inti (ringkas)

**`penggajian`** (lihat `app/Models/Penggajian.php` untuk fillable lengkap):
`periode_penggajian_id, tenaga_pendidik_id, jabatan_id, gaji_pokok, vakasi_absen_harian, vakasi_mengajar, vakasi_tugas_jabatan, vakasi_tugas_tambahan, vakasi_peserta_kegiatan, vakasi_lembur, tunjangan_lainnya, potongan_keterlambatan, potongan_alfa, potongan_tetap, potongan_lainnya, potongan_liburan, keterangan_liburan, total_pendapatan, total_potongan, gaji_bersih, total_hari_kerja, total_hadir, total_izin, total_sakit, total_alfa, total_terlambat, total_jp_mengajar, status, dibayar_pada, catatan, ada_koreksi_manual, difinalisasi_oleh`.

**`detail_penggajian`**: `penggajian_id, tipe (ENUM), keterangan, jumlah_satuan, satuan, nilai_per_satuan, subtotal, referensi_ids (JSON)`.

Model terkait (lihat `app/Models/`): `PeriodePenggajian, TenagaPendidik, Jabatan, JabatanGuru, AbsensiHarian, AbsensiMengajar, JadwalMengajar, MataPelajaran, TahunAjaran, TugasJabatan, RealisasiTugasJabatan, TugasTambahan, PenugasanTambahan, AbsensiKegiatan(+Peserta), Lembur(+Peserta), PengajuanIzin, KuotaIzinTahunan, HariLibur, RekapKinerjaBulanan, LogKerjaHarian, PunishmentKinerja, SettingGajiPokok, GajiPokokIndividu, SettingVakasi, SettingJamKerja, SettingKinerja, SettingLokasiAbsensi, SettingPotongan, SettingJenisPengajuan, RiwayatStatusKepegawaian, KoreksiAbsensi, LogAktivitas`.

> Migrasi terkonsolidasi penting: `*_create_lembur_tables`, `*_tugas_jabatan_kinerja_punishment` (menambah `tugas_jabatan.perlu_verifikasi`, `realisasi_tugas_jabatan.terlambat`, tabel `punishment_kinerja`).

---

## 7. API Surface (Aplikasi Guru) — `/api/v1`

Auth: `POST /auth/login` → Bearer (Sanctum). Profil: `/profile*`.

| Grup | Endpoint kunci |
|---|---|
| Absensi | `GET /absensi/hari-ini`, `/riwayat`, `/rekap/{bulan}/{tahun}`; `POST /check-in`, `/check-out` (foto+GPS); mengajar: `mengajar/hari-ini`, `/absen`, `/konfirmasi-izin`, `/mulai`, `/selesai`, `/riwayat`. |
| Jadwal | `GET /jadwal`, `/hari-ini`, `/minggu-ini`. |
| Tugas | `GET /tugas`, `/aktif`, `/{penugasan}`; `POST /{penugasan}/mulai`, `/laporan`; jabatan: `GET /tugas/jabatan/list`, `POST /tugas/jabatan/{id}/realisasi`. |
| Lembur | `GET /lembur`, `POST /lembur/ajukan`, `POST /lembur/{peserta}/bukti` (foto+GPS). |
| Kegiatan | `GET /kegiatan`, `POST /kegiatan`, detail/peserta/absensi-bulk/selesaikan. |
| Payroll | `GET /payroll/riwayat`, `/terkini`, `/{periode}`, `/{penggajian}/slip` (hanya milik sendiri). |
| Kinerja | `GET /kinerja/bulan-ini`, `/riwayat`, `/nilai-status`, `/punishment`. |
| Izin | `GET /izin/jenis`, `GET /izin`, `POST /izin`, `DELETE /izin/{id}`. |
| Notifikasi | `index`, `unread-count`, `mark baca`, `baca-semua`. |

---

## 8. Aturan Bisnis Kunci (jangan diubah tanpa pertimbangan)

1. **`dinas_luar` = hadir** — guru bertugas resmi di luar tetap dapat vakasi harian.
2. **JP saat `libur` & `izin` tetap dibayar** (kebijakan pesantren); hanya `tidak_terlaksana` yang tidak.
3. **Tugas Jabatan TIDAK menghasilkan vakasi** — penyelesaiannya menjadi faktor **Kinerja**, bukan honor.
4. **Punishment `potongan` wajib terhubung ke payroll** (periode bulan/tahun sama).
5. **Lembur memakai geofence strict (opsi GPS wajib)** karena menyangkut gaji; tidak ada override nominal — admin hanya memilih setting.
6. **Semua potongan aktif dihitung & tampil di slip** — `tampil_di_slip` bukan alat menyembunyikan dari perhitungan.
7. **Penyesuaian liburan manual dipertahankan** saat generate ulang.

---

## 9. Status Implementasi & Analisis (terakhir)

### ✅ Sudah berfungsi & teruji
- Generate gaji penuh (semua komponen pendapatan + potongan), idempoten, lock konkurensi, exception per komponen.
- Vakasi fleksibel (individu→jabatan→semua) dengan prioritas benar.
- Lembur end-to-end (admin + guru, GPS strict, grace 1 jam, sah manual) → `vakasi_lembur` masuk payroll.
- Punishment `potongan` → `potongan_lainnya` (terverifikasi: 100k → potongan_lainnya naik, total_potongan naik).
- Tugas Jabatan tanpa vakasi, dinilai di kinerja; task harian muncul ulang sesuai window frekuensi.
- Slip gaji tersinkron antara admin, laporan, dan Flutter.

### ⚠️ Catatan / utang teknis (untuk diperhatikan saat lanjut)
1. ~~`hitungVakasiTugasJabatan()` masih ada tapi tidak dipanggil.~~ **✅ Beres (2026-06-17)** — method dead code dihapus. Komponen #4 tetap `$vakasiTugasJabatan = ['total'=>0,'detail'=>[]]` (zero eksplisit yang disengaja + terdokumentasi; kolom `vakasi_tugas_jabatan` tetap diisi 0 demi kompatibilitas). Jika kelak kebijakan berubah, hitung ulang lewat resolver `getVakasiUntukGuru('tugas_jabatan', $guru)` (masih ada) atau flag setting.
2. ~~`SettingVakasiController` adalah dead code.~~ **✅ Beres (2026-06-17)** — file dihapus; fitur Setting Vakasi sepenuhnya dilayani `SettingGajiController@vakasi*`. (Dulu sempat jadi jebakan: controller ini terlihat aktif padahal tidak ter-route → sumber bug "tipe aktivitas invalid".)
3. **`total_hari_kerja`** = hari kalender − hari libur; tidak mengurangi izin yang disetujui. Cukup untuk vakasi (dibayar per `hadir`), tapi perlu diperjelas bila kelak dipakai untuk rasio kehadiran resmi.
4. **Pola Vue runtime-template** — beberapa komponen inline pernah memakai `template:` string (tidak ter-compile di runtime build). Bila menambah komponen fungsional inline, gunakan `render()`/`h()` atau deklarasikan `.props`.
5. **Validasi enum kolom** diubah via `DB::statement(ALTER ... MODIFY ENUM)` — saat menambah `tipe`/status baru, perbarui ENUM **dan** `normalizeTipe()`/whitelist terkait.

### 🔭 Belum ada (kandidat roadmap, bukan bug)
- Pajak/PPh, slip PDF batch, ekspor bank (payroll file), riwayat audit perubahan nominal per field, dashboard payroll khusus per periode (tren), notifikasi push saat slip terbit.

---

## 10. Integrasi dengan **Smart Education** (modul berikutnya)

Smart Education (absensi santri, jurnal mengajar, smart habit, dll.) akan hidup di app Laravel yang sama. Titik temu & pedoman agar tidak tabrakan:

- **Model bersama:** `User` (auth + role), `TenagaPendidik`, `Jabatan`, `TahunAjaran`, `MataPelajaran`, `JadwalMengajar` kemungkinan dipakai ulang. **Jangan** mengubah kontrak field-nya tanpa cek dampak ke payroll.
- **Namespacing:** buat prefix route & controller terpisah, mis. `admin.smart-education.*` (web) dan `/api/v1/education/*` (API) + folder `Controllers/Superadmin/Education/`. Sidebar: tambahkan section baru, **gunakan `Components/Sidebar/icons.js` bersama** (sumber ikon tunggal) agar konsisten.
- **Role baru:** kemungkinan butuh `santri` / `wali`. Tambah role + middleware `role.api:*` mengikuti pola yang ada.
- **Jurnal mengajar** kemungkinan beririsan dengan `AbsensiMengajar` — putuskan apakah jurnal memperkaya record absensi mengajar yang ada atau tabel terpisah, agar **vakasi mengajar tidak terhitung ganda**.
- **Smart habit / absensi santri** sebaiknya tabel & service sendiri; hindari menyentuh `PayrollCalculationService`.
- Konvensi verifikasi tetap: lint `php -l`, uji aman via `artisan tinker` (`DB::beginTransaction`/`rollBack`), `npm run build`, `flutter analyze`.

---

## 11. Cara Melanjutkan (quick start sesi berikutnya)

```bash
# 1. Nyalakan MySQL via XAMPP Control Panel (jangan lupa Stop sebelum shutdown)
# 2. Backend
php artisan serve            # atau Apache
php artisan optimize:clear   # bila ada perubahan PHP (cache aman dibersihkan)
# 3. Frontend admin (selalu fresh)
npm run dev                  # dev
npm run build                # verifikasi build produksi
# 4. Aplikasi guru
flutter analyze              # di repo Flutter terpisah
```

**File jantung yang harus dibaca lebih dulu saat menyentuh payroll:**
`app/Services/PayrollCalculationService.php` · `app/Services/SlipGajiBuilder.php` · `app/Models/Penggajian.php` · `app/Http/Controllers/Superadmin/PenggajianController.php` · `routes/web.php` (blok `smart-payroll`).
