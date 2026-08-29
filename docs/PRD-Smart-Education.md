# PRD — An Nur Smart System: Modul **Smart Education**

> **Status:** Baseline / desain awal. Disusun sebelum implementasi Fase 1.
> **Relasi modul:** Berdampingan dengan **Smart Payroll** (lihat `docs/PRD-Smart-Payroll.md`). Smart Education **tidak mengubah logika gaji**; ia hanya menambah data (santri/kelas) dan memperkaya alur pengisian. Komponen mengajar (sekolah & tahfidz) tetap mengalir ke vakasi seperti biasa.
> **Stack:** Laravel 12 (backend/logic) · Flutter (frontend tenaga pendidik) · MariaDB `systemdb` (XAMPP).
> **Tanggal baseline:** 2026-06-19.

---

## 1. Tujuan & Ruang Lingkup

Smart Education adalah subsistem untuk **mengelola kegiatan santri**: kehadiran masuk sekolah, kegiatan belajar-mengajar (absen santri + jurnal mengajar), dan pembelajaran Tahfidz (tracking hafalan). Backend Laravel mengolah data & logika; aplikasi Flutter dipakai **tenaga pendidik** (santri tidak login).

Lingkup baseline:
- **Database Santri** (master data santri + kelas).
- **Absensi Masuk Sekolah** (barcode, hanya check-in).
- **Jurnal Mengajar (sekolah)** — absen santri per kelas + isi jurnal, memperluas `AbsensiMengajar` yang sudah ada.
- **Smart Tahfidz** — alur tersendiri: jadwal, absen, jurnal setoran (ziyadah/murojaah/tahsin).
- **Persiapan integrasi** WhatsApp (notifikasi ortu) & ramahanak (tarik absensi per NIP) — *lapisan tipis/outbox, dikerjakan penuh setelah inti jalan*.

Di luar lingkup baseline: rapor akademik penuh, SPP/keuangan santri, akun login santri/wali, integrasi WA/ramahanak yang fungsional penuh.

---

## 2. Keputusan Desain (terkunci)

| # | Keputusan | Pilihan |
|---|---|---|
| 1 | Sinkronisasi `kelas` (kini teks bebas di `jadwal_mengajar`) | **Additif + backfill** — tabel master `kelas` + kolom `kelas_id` baru; kolom string `kelas` lama dipertahankan & diisi otomatis. Payroll/absen berjalan tidak terganggu. |
| 2 | Model Kelas Sekolah vs Tahfidz | **Satu tabel `kelas` + kolom `jenis`** (`sekolah`\|`tahfidz`). Santri bisa punya kelas sekolah DAN tahfidz via pivot `kelas_santri`. **Penjadwalan & jurnal tahfidz tetap sistem tersendiri** (alur beda dari mengajar kelas). |
| 3 | Status absensi santri | **3 status: `hadir`, `telat`, `alpha`.** Ditarik per **NIP** ke ramahanak untuk poin kebiasaan (hadir=baik, telat=ringan, alpha=berat). Integrasi ramahanak menyusul. |
| 4 | Kedalaman tracking Tahfidz | **Per setoran:** surah + ayat mulai–selesai + jenis (`ziyadah`/`murojaah`/`tahsin`) + nilai + catatan. |
| 5 | Tahfidz ↔ Payroll | Alur tahfidz **disendirikan**, tapi **terhubung ke payroll/vakasi sebagaimana mengajar kelas** (lihat §6.4 untuk mekanisme). |

---

## 3. Model Data Baru

### 3.1 `santri`
NIP (unik), nama_lengkap, nama_panggilan, email (nullable), jenis_kelamin (`L`/`P`), tempat_lahir, tanggal_lahir, no_whatsapp, foto, is_aktif.
> Tidak terhubung ke tabel `users` (santri tidak login). NIP = kunci tarik data ke ramahanak.

### 3.2 `kelas`
nama, jenis (`sekolah`/`tahfidz`), tingkat (nullable), tahun_ajaran_id (FK, nullable), wali_kelas_id (FK `tenaga_pendidik`, nullable), is_aktif.

### 3.3 `kelas_santri` (pivot many-to-many)
kelas_id, santri_id, (opsional: tanggal_masuk, is_aktif). Memungkinkan 1 santri di kelas sekolah + kelas tahfidz sekaligus.

### 3.4 `jadwal_mengajar` (perubahan ADITIF)
+ `kelas_id` (FK nullable). Kolom string `kelas` **dipertahankan** & di-backfill. Tidak ada kolom yang dihapus → payroll aman.

### 3.5 Jurnal & Absen Sekolah (memperluas yang ada)
- `AbsensiMengajar` (sudah ada: `materi`, `foto_mengajar`) dipakai sebagai "kepala" sesi mengajar sekolah.
- **`absensi_santri`** (baru): absensi_mengajar_id (FK), santri_id (FK), status (`hadir`/`telat`/`alpha`), catatan. → ini sumber data ramahanak.
- Jurnal materi cukup memakai field `materi`/`foto_mengajar` di `AbsensiMengajar` (hindari tabel jurnal terpisah agar tidak dobel).

### 3.6 Absensi Masuk Sekolah
**`absensi_masuk`** (baru): santri_id (FK), tanggal, jam_scan, status (`hadir`/`telat`), metode (`barcode`), sumber. Hanya check-in (tanpa check-out). `telat` dihitung dari ambang jam masuk (setting).

### 3.7 Smart Tahfidz (subsistem tersendiri)
- **`jadwal_tahfidz`**: kelas_id (jenis=tahfidz), tenaga_pendidik_id, hari/jam, is_aktif. (Terpisah dari `jadwal_mengajar`.)
- **`sesi_tahfidz`**: jadwal_tahfidz_id, tenaga_pendidik_id, tanggal, status, foto (kepala sesi — analog `AbsensiMengajar`, untuk vakasi).
- **`absensi_tahfidz_santri`**: sesi_tahfidz_id, santri_id, status (`hadir`/`telat`/`alpha`). → juga sumber ramahanak.
- **`setoran_tahfidz`**: sesi_tahfidz_id (nullable), santri_id, tenaga_pendidik_id, tanggal, jenis (`ziyadah`/`murojaah`/`tahsin`), surah, ayat_mulai, ayat_selesai, nilai, catatan.

### 3.8 Persiapan Integrasi (prep — belum fungsional penuh)
- **`outbox_notifikasi`** (baru): tujuan (no_wa), template, payload (JSON), status (`pending`/`terkirim`/`gagal`), channel (`whatsapp`). Diisi event absen masuk; pengirim WA nyata menyusul.
- **Endpoint agregasi ramahanak**: ringkasan absensi per santri (NIP) → {hadir, telat, alpha} dalam rentang tanggal. Dibuat read-only, dikerjakan setelah inti jalan.

---

## 4. Aturan & Status Absensi

Status santri di seluruh modul: **`hadir`, `telat`, `alpha`** (3 saja, sesuai keputusan).
- **hadir** → poin baik.
- **telat** → poin kurang (ringan).
- **alpha** → poin buruk (berat).

> Catatan: jika kelak butuh `izin`/`sakit` untuk laporan internal, dapat ditambah sebagai status netral tanpa mengganggu tarikan ramahanak (yang hanya membaca 3 status di atas).

---

## 5. Alur (Flow)

### 5.1 Absen Masuk Sekolah (barcode, check-in saja)
1. Santri scan barcode di gerbang saat jam masuk.
2. Sistem catat `absensi_masuk` → status `hadir`/`telat` (bandingkan `jam_scan` vs ambang jam masuk).
3. Buat baris `outbox_notifikasi` (prep WA ke ortu: "Ananda X telah masuk sekolah pukul ...").
4. Tidak ada check-out.

### 5.2 Jurnal Mengajar Sekolah (Flutter, guru)
1. Guru buka sesi dari `JadwalMengajar` (kini ber-`kelas_id`).
2. **Absen santri** kelas tsb (daftar santri via `kelas_santri`) → status hadir/telat/alpha → simpan `absensi_santri`.
3. **Isi jurnal**: materi + foto mengajar (field di `AbsensiMengajar`).
4. **Kirim** → `AbsensiMengajar` ter-set terlaksana → tetap mengalir ke `vakasi_mengajar` payroll (tanpa perubahan logika gaji).

### 5.3 Smart Tahfidz (alur sendiri)
1. Guru buka sesi dari `jadwal_tahfidz` → buat `sesi_tahfidz`.
2. **Absen santri tahfidz** → `absensi_tahfidz_santri` (hadir/telat/alpha).
3. **Catat setoran** per santri → `setoran_tahfidz` (jenis, surah, ayat mulai–selesai, nilai, catatan).
4. **Kirim/selesai** → sesi sah → feed ke payroll (lihat §6.4).

---

## 6. Integrasi & Dampak ke Modul Lain

### 6.1 Reuse (jangan duplikasi)
`TenagaPendidik`, `MataPelajaran`, `TahunAjaran`, `JadwalMengajar`, `AbsensiMengajar` dipakai ulang.

### 6.2 Smart Payroll
- Mengajar **sekolah** sudah otomatis terhitung lewat `AbsensiMengajar` → `vakasi_mengajar`. **Tidak ada perubahan logika payroll.**
- Perubahan `jadwal_mengajar` hanya **aditif** (`kelas_id`).

### 6.3 Penentuan Kelas (akar masalah lama — teratasi)
`jadwal_mengajar.kelas` (string) → di-backfill ke `kelas` master + `kelas_id`. Absen santri kini bersumber dari `kelas_santri`, bukan tebakan string.

### 6.4 Tahfidz → Payroll (mekanisme yang disarankan)
Tahfidz beralur sendiri tetapi vakasinya dibayar "seperti mengajar". Opsi implementasi (akan dikonfirmasi saat Fase 4):
- **Opsi A (disarankan, minim ubah payroll):** saat `sesi_tahfidz` disahkan, buat/cerminkan record `AbsensiMengajar` (atau sumber JP setara) sehingga payroll membaca lewat jalur `vakasi_mengajar` yang sudah ada.
- **Opsi B:** tambah komponen `vakasi_tahfidz` baru di `PayrollCalculationService` + `SettingVakasi` tipe `absen_tahfidz`. Lebih eksplisit, tapi menyentuh mesin payroll.

### 6.5 ramahanak & WhatsApp (prep)
- ramahanak menarik {hadir, telat, alpha} per **NIP** dari gabungan: absen masuk + absen mengajar sekolah + absen tahfidz.
- WA notif via `outbox_notifikasi` (driver nyata menyusul).

---

## 7. Arsitektur & Konvensi

- Route web: `admin.smart-education.*`; API: `/api/v1/education/*`.
- Controller: `app/Http/Controllers/Superadmin/Education/*` & `app/Http/Controllers/Api/Education/*`.
- Sidebar: section baru "Smart Education", **pakai `resources/js/Components/Sidebar/icons.js` bersama**.
- Role API guru tetap `role.api:tenaga_pendidik`.
- Verifikasi: `php -l`, `artisan tinker` (transaksi + rollback), `npm run build`, `flutter analyze`. Migrasi bersifat **aditif** (aman untuk DB produksi).

---

## 8. Rencana Bertahap (phasing — anti-overload)

| Fase | Isi | Sentuh payroll? |
|---|---|---|
| **1. Fondasi data** | `santri`, `kelas`, `kelas_santri`, `kelas_id` aditif di `jadwal_mengajar` + backfill. Model + CRUD admin Santri & Kelas. | Tidak |
| **2. Absen Masuk Sekolah** | `absensi_masuk` (barcode check-in) + `outbox_notifikasi` (prep WA). | Tidak |
| **3. Jurnal Mengajar Sekolah** | `absensi_santri` + perluasan alur `AbsensiMengajar` (Flutter). | Tidak (lewat jalur lama) |
| **4. Smart Tahfidz** | `jadwal_tahfidz`, `sesi_tahfidz`, `absensi_tahfidz_santri`, `setoran_tahfidz` + jembatan vakasi (§6.4). | Sedikit (Opsi A/B) |
| **5. Integrasi** | Endpoint agregasi ramahanak (NIP) + driver WA nyata + lapisan poin kebiasaan. | Tidak |

---

## 9. Catatan Risiko & Anti-Overload

1. **Jangan bikin "jurnal mengajar" paralel** — perkaya `AbsensiMengajar` agar JP tidak terhitung dobel di payroll.
2. **Migrasi selalu aditif** ke tabel lama (`jadwal_mengajar`) — tidak menghapus kolom string `kelas` sampai semua alur baru terbukti stabil.
3. **Santri tanpa auth** — hindari kompleksitas akun & izin yang tidak perlu.
4. **Integrasi WA/ramahanak ditunda** sampai inti jalan; cukup outbox + endpoint read-only sebagai titik sambung.
5. **Tahfidz↔payroll** pilih Opsi A bila memungkinkan agar mesin payroll tidak diutak-atik.
