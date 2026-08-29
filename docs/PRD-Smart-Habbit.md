# PRD — Smart Habbit (An-Nur Smart System)

> Status: **DRAFT / perencanaan**. Dibuat sebagai acuan flow sebelum implementasi.
> Modul ini adalah kelanjutan setelah **Smart Education**. Pengerjaan **bertahap & sinkron**.
> Laporan & output akhir **ditentukan belakangan** setelah logic utama berjalan.

---

## 1. Ringkasan & Tujuan

**Smart Habbit** berfungsi **mengukur dan mengontrol kebiasaan santri** — mulai dari
**absensi sekolah/asrama** sampai **perilaku (habits)**.

Karakter utama modul ini: **hasilnya dikirim ke aplikasi lain (Ramah Anak) lewat API.**
- Hasil absensi (**hadir / telat / alpha**) → dikirim ke Ramah Anak.
- Laporan perilaku/habits (pelanggaran, apresiasi, konselor) → dikirim ke Ramah Anak.

Kunci sinkronisasi antar-aplikasi: **NIP santri**.

### Sub-fitur
1. **Smart Controlling** — kontrol kehadiran santri pada kegiatan terjadwal (absen masuk/pulang
   sekolah, kegiatan asrama: sholat berjamaah, tidur, dhuha, dll, serta kajian).
2. **Smart Eksekusi** — pelaporan tindakan santri oleh tenaga pendidik (pelanggaran / apresiasi / konselor).
3. **Habit scoring** *(fase lanjutan)* — penilaian baik/buruk kebiasaan dari akumulasi data di atas.

> Catatan: kebutuhan lama **"Fase 2 — Absen Masuk Sekolah (barcode)"** kini **masuk ke dalam
> Smart Controlling** (kegiatan "absen masuk" & "absen pulang" adalah bagian dari jadwal controlling).

---

## 2. Integrasi dengan "Ramah Anak" (aplikasi eksternal)

- Ramah Anak **sudah deployed**. Konfigurasi dilakukan via **SSH** (kredensial/endpoint disiapkan saat fase integrasi).
- Komunikasi lewat **REST API** (HTTP). An-Nur = pengirim (producer), Ramah Anak = penerima (consumer).
- **Sinkronisasi identitas santri = NIP** (NIP An-Nur harus cocok dengan identitas santri di Ramah Anak).
- Data yang dikirim:
  - **Hasil absensi** (hadir/telat/alpha) per santri per kegiatan/tanggal.
  - **Laporan perilaku** (pelaku/korban + kategori pelanggaran/apresiasi/konselor).
- Variabel pelaporan (nama pelanggaran, kategori, dll) **mengikuti master di Ramah Anak** — An-Nur
  mengambil daftar/ID yang relevan dari Ramah Anak, lalu mengirim laporan dengan format & ID tersebut.

### Yang perlu didapat dari Ramah Anak (saat fase integrasi)
- Base URL API + skema autentikasi (API key / token / Bearer).
- Endpoint & payload untuk: (a) push absensi, (b) lookup/sinkron santri by NIP, (c) master pelanggaran/apresiasi/konselor, (d) submit laporan perilaku.
- Aturan respons & error (idempotency, retry, kode error).

> **TBD / perlu konfirmasi:** spesifikasi endpoint Ramah Anak (kontrak API) belum ada di dokumen ini —
> akan diisi setelah eksplorasi via SSH.

---

## 3. Aktor & Peran

| Aktor | Peran |
|---|---|
| **Admin / Petugas scan** | Mengoperasikan **device scan barcode** untuk absensi Smart Controlling (santri tidak pakai akun; cukup kartu ber-barcode). |
| **Petugas kegiatan / guru ngaji** | Melakukan absensi kajian (scan barcode) di tengah kegiatan; terkait **vakasi**. |
| **Tenaga pendidik (semua guru)** | Membuat laporan perilaku santri lewat **Smart Eksekusi**. |
| **Superadmin** | Mengatur **Setting Controlling** (jadwal & kegiatan per periode 1 bulan), memantau rekap. |
| **Santri** | Objek yang diabsen & dinilai; **tidak** login — identitas via **kartu barcode (NIP)**. |

---

## 4. Smart Controlling

### 4.1 Tujuan
Mengontrol santri lewat **kehadiran pada kegiatan terjadwal** (sekolah & asrama), berbasis **jadwal
per periode 1 bulan**. Santri **wajib absen** pada tiap kegiatan terjadwal.

### 4.2 Jawaban absensi (inti)
Setiap absensi kegiatan hanya punya **3 nilai**: **Hadir**, **Telat**, **Alpha**.
- 3 nilai ini menjadi dasar **habit scoring** (baik/buruk) ke depan.
- 3 nilai ini juga **dikirim ke Ramah Anak** (jadi "kunci" penilaian di sana).

### 4.3 Mekanisme absen
- **Bukan tenaga pendidik** yang melakukan absen (kecuali kajian — lihat 4.6).
- Absen via **scan barcode** oleh admin/petugas pada **device khusus scanner**.
- Hasil absen **disimpan per santri** (riwayat harian) + **rekap** apakah kegiatan **berjalan / tidak**.

### 4.4 Setting Controlling (oleh Superadmin)
- **Periode = 1 bulan.** Tiap periode berisi susunan **kegiatan + jadwal harian**.
- Antar-bulan bisa **sama** (kegiatan identik) atau **berbeda** (kegiatan/jam beda).
- Isi setting:
  - **Daftar kegiatan** (mis. Sholat Subuh, Absen Masuk, Sholat Dzuhur, Absen Pulang, Sholat Ashar,
    Sholat Maghrib, Sholat Isya, Tidur, Sholat Dhuha, dll).
  - **Jadwal** tiap kegiatan: hari + jam mulai–selesai (window absen).
- Window jam menentukan logika **Hadir/Telat/Alpha**:
  - Scan dalam window awal → **Hadir**.
  - Scan setelah ambang tertentu (masih dalam window) → **Telat**.
  - Tidak scan sampai window tutup → **Alpha** (auto).

> **Perlu konfirmasi:** ambang "telat" (mis. X menit setelah jam mulai) — apakah per kegiatan atau global.

### 4.5 Flow contoh (jadwal harian — ilustrasi)
Contoh hari **Senin** (jam bersifat **ilustrasi**, akan difinalkan saat setting):

| Kegiatan | Window absen |
|---|---|
| Sholat Subuh | 04.30 – 04.45 |
| Sholat Dhuha | (pagi, jam TBD) |
| Absen Masuk Sekolah | 06.30 – 07.00 |
| Sholat Dzuhur | 12.30 – 12.45 |
| Absen Pulang | 14.30 – 15.00 |
| Sholat Ashar | 15.10 – 15.25 |
| Sholat Maghrib | 17.20 – 17.30 |
| Sholat Isya | 19.00 – 19.15 |
| Absen Tidur | 21.45 – 22.00 |

> Beberapa jam pada pesan asli terbaca typo (mis. Subuh "04.30–04.15", Dhuha "03.15–13.30") →
> akan dikoreksi di setting. Yang pasti: tiap kegiatan punya window, hasil = Hadir/Telat/Alpha.

### 4.6 Flow kedua — Absensi Kajian (terkait vakasi)
- Hari tertentu ada **kajian** (mis. **Jumat pagi, Ahad pagi, Senin pagi**).
- Absensi santri untuk kajian **dilakukan oleh petugas/guru ngaji** dengan **scan barcode** di
  **tengah kegiatan** (tidak harus tepat di jam mulai) — **bebas selama masih dalam rentang kajian**.
- Karena **berhubungan dengan vakasi** (tiap guru ngaji), **waktu kajian ditentukan** di setting,
  namun **eksekusi scan fleksibel** asal masih dalam window kajian.
- Setting khusus untuk jenis kegiatan **"kajian"** disediakan (penanda agar diperlakukan fleksibel + relasi ke guru/vakasi bila relevan).

> **Perlu konfirmasi:** apakah absensi kajian ini ikut menghasilkan vakasi mengajar untuk guru ngaji
> (seperti tugas tambahan/vakasi yang sudah ada), atau hanya pencatatan kehadiran santri.

### 4.7 Keluaran Smart Controlling
- **Riwayat absen harian per santri** (per kegiatan: Hadir/Telat/Alpha + waktu scan).
- **Rekap kegiatan** (berjalan/tidak) per periode.
- **Push ke Ramah Anak**: hasil Hadir/Telat/Alpha per santri (by NIP).

---

## 5. Smart Eksekusi

### 5.1 Tujuan
Wadah **pelaporan tindakan/perilaku santri** oleh **semua tenaga pendidik** → diteruskan ke **Ramah Anak**.

### 5.2 Flow
- Tenaga pendidik membuat laporan dengan:
  - **Kategori subjek (2)**: **Pelaku** dan/atau **Korban** (santri terkait).
  - **Kategori laporan (3)**: **Pelanggaran**, **Apresiasi**, **Konselor**.
- **Nama pelanggaran/kategori sudah di-setting di Ramah Anak.** An-Nur mengambil daftar/ID-nya, lalu
  laporan mengikuti **format & ID** dari Ramah Anak.
- Setelah **submit** → laporan **dikirim ke Ramah Anak** (status terkirim/gagal dicatat).

### 5.3 Keluaran Smart Eksekusi
- Catatan laporan di An-Nur (untuk audit lokal) + status pengiriman.
- Payload terkirim ke Ramah Anak: pelapor (guru), santri (pelaku/korban by NIP), kategori, ID variabel, deskripsi, waktu.

> **Perlu konfirmasi:** field wajib dari Ramah Anak (mis. lokasi, bukti foto, severity, tindak lanjut).

---

## 6. Sketsa Model Data (USULAN — bisa berubah)

> Bersifat indikatif. Final menyesuaikan kontrak Ramah Anak. Semua migrasi harus **ADITIF**
> (tidak merusak Smart Payroll/Education yang berjalan).

**Smart Controlling**
- `controlling_periode` — bulan/tahun, label, is_aktif.
- `controlling_kegiatan` — nama, jenis (`harian` | `kajian`), default window, keterangan.
- `controlling_jadwal` — periode_id, kegiatan_id, hari, jam_mulai, jam_selesai, ambang_telat, (kajian: guru/vakasi opsional).
- `controlling_absensi` — santri_id (NIP), jadwal_id/kegiatan_id, tanggal, status (`hadir|telat|alpha`),
  jam_scan, petugas_id, dikirim_ke_ramah_anak (bool), ref_ramah_anak.

**Smart Eksekusi**
- `eksekusi_laporan` — pelapor_id (guru), kategori_laporan (`pelanggaran|apresiasi|konselor`),
  variabel_ramah_anak_id, deskripsi, waktu, status_kirim, ref_ramah_anak.
- `eksekusi_laporan_santri` — laporan_id, santri_id, peran (`pelaku|korban`).

**Integrasi**
- `ramah_anak_outbox` *(opsional)* — antrian/log pengiriman (payload, status, percobaan, response) untuk retry & audit.

> Identitas santri tetap pakai tabel `santri` yang ada; **NIP** sebagai kunci sinkron ke Ramah Anak.

---

## 7. Tahapan Pengerjaan (BERTAHAP & SINKRON)

> Urutan ini sesuai instruksi: integrasi dulu, mulai dari absensi Smart Education, baru Controlling & Eksekusi.

### Fase A — Integrasi Ramah Anak + Absensi Smart Education → Ramah Anak
1. **Eksplorasi Ramah Anak** (via SSH): dapatkan kontrak API (auth, endpoint, payload, master variabel),
   pastikan **NIP** dapat memetakan santri di kedua sistem.
2. Buat **layanan integrasi** di An-Nur (HTTP client + config kredensial via `.env`, outbox/log, retry).
3. **Hubungkan absensi siswa di Smart Education** (hasil `absensi_santri` / kehadiran) supaya tiap
   hasil absen terkirim ke Ramah Anak dengan **sinkronisasi NIP**.
4. Verifikasi end-to-end: absen di An-Nur → terkirim & tercatat di Ramah Anak.

### Fase B — Smart Controlling
1. **Setting Controlling** (periode 1 bulan, kegiatan harian + kajian, window & ambang telat).
2. **Device scan barcode** (alur admin/petugas) — absen Hadir/Telat/Alpha by NIP, auto-Alpha bila window tutup.
3. **Absensi kajian** (fleksibel dalam window; relasi vakasi bila dikonfirmasi).
4. **Riwayat per santri** + **rekap kegiatan**.
5. **Push hasil Hadir/Telat/Alpha ke Ramah Anak**.

### Fase C — Smart Eksekusi
1. Ambil **master variabel** (pelanggaran/apresiasi/konselor) dari Ramah Anak.
2. Form pelaporan oleh guru (pelaku/korban + kategori).
3. **Submit → kirim ke Ramah Anak** + status & log.

### Fase D — Habit scoring & Laporan akhir *(belakangan)*
- Skor baik/buruk kebiasaan dari akumulasi Controlling + Eksekusi.
- Laporan/rekap final (per santri, per periode) — **ditentukan setelah logic utama jalan**.

---

## 8. Prinsip & Batasan Teknis (mengikuti sistem berjalan)
- **Stack:** Laravel 12 + Inertia/Vue (admin) + Flutter (guru) + MariaDB (XAMPP).
- **Migrasi ADITIF** — tidak mengubah skema payroll/education yang sudah jalan.
- **Santri tidak login** untuk Controlling — identitas via barcode (NIP).
- **Idempotency & retry** untuk pengiriman ke Ramah Anak (hindari dobel kirim).
- **Keamanan:** kredensial Ramah Anak di `.env`; jangan hardcode. Validasi NIP sebelum kirim.
- Verifikasi tiap fase: `php -l`, `migrate --force`, `npm run build`, `flutter analyze`, uji tinker.

---

## 9. Pertanyaan Terbuka (perlu dikonfirmasi sebelum/di Fase A)
1. **Kontrak API Ramah Anak**: base URL, auth, endpoint absensi, endpoint laporan, master variabel.
2. **Pemetaan NIP**: apakah NIP An-Nur == identitas santri Ramah Anak? Ada endpoint verifikasi?
3. **Mode kirim absensi**: real-time tiap scan, atau batch (mis. tiap akhir window/hari)?
4. **Ambang Telat**: definisi (menit) per kegiatan / global.
5. **Kajian & vakasi**: absensi kajian menghasilkan vakasi guru ngaji atau hanya kehadiran santri?
6. **Device scan**: web (browser kamera/scanner) atau app Flutter khusus? Satu device terpusat atau banyak?
7. **Smart Eksekusi**: field wajib Ramah Anak (bukti, severity, lokasi, tindak lanjut)?
8. **Penanganan offline** device scan (antri lalu sinkron) — diperlukan atau tidak.

---

---

## 10. Audit Kesiapan terhadap Sistem Berjalan (hasil cek codebase 2026-06-21)

> Verdict: **PRD layak jalan di aplikasi ini.** Banyak fondasi sudah tersedia & bisa dipakai ulang.
> Berikut peta reuse, temuan, dan solusi konkret.

### 10.1 Sudah ada & bisa dipakai ulang (✅)
| Kebutuhan PRD | Yang sudah ada | Catatan |
|---|---|---|
| Kunci sinkron Ramah Anak = **NIP** | `santri.nip` **terindeks, 0 duplikat** | Pastikan constraint **UNIQUE** dipertahankan. Aman jadi kunci. |
| Jawaban absen **Hadir/Telat/Alpha** | `absensi_santri.status` = `enum('hadir','telat','alpha')` | Konvensi sama persis → dipakai untuk Controlling juga. |
| Pengiriman async ke Ramah Anak (outbox/retry) | **Queue driver = `database`** + **Guzzle** tersedia (Laravel `Http`) | Tinggal bikin Job + tabel outbox. **Butuh `queue:work` jalan di server.** |
| Kajian → **vakasi** guru ngaji | `tugas_tambahan` + `penugasan_tambahan` + `absensi_kegiatan` | Reuse **mesin vakasi tugas tambahan** (pola sama seperti Tasmi') → tanpa ubah engine payroll. |
| Lewati kegiatan saat libur | `hari_libur` | Auto-Alpha **wajib skip** tanggal libur. |
| Periode bulanan | `periode_penggajian` + `tahun_ajaran` | Selaraskan "periode 1 bulan" Controlling dgn batas bulan payroll/kinerja agar data habit sejajar. |

### 10.2 Temuan & Solusi
1. **Tidak ada kolom `barcode` di `santri`.**
   → **Solusi:** pakai **NIP sebagai isi barcode/QR** (encode NIP). Tak perlu kolom baru. Bila NIP tak boleh
   tampil di kartu, tambah kolom opsional `kode_kartu` (random unik) yang memetakan ke NIP. **Rekomendasi:** NIP-based + validasi (santri aktif) saat scan.
2. **Device scan belum ada.**
   → **Solusi (FINAL): endpoint scan device-agnostic.** Backend ekspos `POST /controlling/scan`
   (auth via **device token**, bukan akun guru) yang menerima `{ nip, kegiatan_id?, waktu? }`. Dgn ini
   **client apa pun bisa pakai endpoint sama**: (a) **web kiosk admin** (scanner USB = input keyboard / kamera),
   (b) **ESP32-CAM** yang men-decode QR berisi NIP lalu POST. **Mulai dgn web kiosk** sebagai referensi; **ESP32
   menyusul tanpa ubah backend.** Server hitung Hadir/Telat dari window + auto-Alpha terjadwal.
3. **`absensi_santri` itu per-sesi mengajar**, beda domain dgn Controlling (per-kegiatan-jadwal harian).
   → **Solusi:** Controlling pakai **tabel sendiri** (`controlling_absensi`), hanya **reuse enum** hadir/telat/alpha. Jangan dipaksakan ke `absensi_santri`.
4. **Auto-Alpha butuh penjadwal.**
   → **Solusi:** Laravel **Scheduler (cron)** untuk menutup window & tandai Alpha. **Pastikan cron `schedule:run` aktif di server.**
5. **Idempotency pengiriman.**
   → **Solusi:** tiap kiriman bawa **`external_ref`/kunci unik** (mis. `nip|kegiatan_id|tanggal`); tabel `ramah_anak_outbox` simpan status+response; Job retry tanpa dobel kirim.
6. **Fase A — target hook konkret.**
   → Absensi Smart Education yg dimaksud = **`AbsensiSantri`** yang dibuat di `TahfidzApiController::absen`,
   `TahsinApiController::absen`, `AbsensiApiController::absenSantri`. **Tanggal** diturunkan dari `absensi_mengajar.tanggal`.
   Karena 1 sesi = banyak baris → **kirim batch** (bukan per baris) untuk efisiensi.
7. **Konektivitas Ramah Anak.**
   → Bila Ramah Anak **satu server** dgn An-Nur, integrasi bisa via endpoint internal (lebih cepat & aman).
   Cek saat SSH: reachability, firewall, auth. Kredensial di **`.env`** (+ `config/ramah_anak.php`).

### 10.3 Rekomendasi penyelarasan
- Gunakan vocabulary **hadir/telat/alpha** konsisten di Controlling = Smart Education = key Ramah Anak.
- "Periode 1 bulan" Controlling **ikut batas bulan** payroll/kinerja → habit scoring & rekap sejajar lintas modul.
- Kajian→vakasi: **reuse `TugasTambahan`/`PenugasanTambahan`** (terbukti dari Tasmi') bila memang menghasilkan vakasi.

### 10.4 Prasyarat operasional (server) — penting
- `php artisan queue:work` (atau supervisor) **berjalan** → outbox terkirim.
- `php artisan schedule:run` via **cron tiap menit** → auto-Alpha & retry terjadwal.
- `.env`: `RAMAH_ANAK_BASE_URL`, `RAMAH_ANAK_TOKEN` (diisi saat Fase A).

### 10.5 Keputusan FINAL (dijawab user 2026-06-21)
- a) **Barcode = NIP langsung** (encode NIP ke QR/barcode; tanpa kolom baru).
- b) **Device scan = endpoint device-agnostic** → mulai **web kiosk (admin)**; **ESP32-CAM** menyusul
     menembak endpoint yang sama (`POST /controlling/scan` + device token). Santri tak bawa device.
- c) **Mode kirim ke Ramah Anak = BATCH via outbox + queue** (retry otomatis, tahan gangguan jaringan).
- d) **Absensi kajian = HANYA kehadiran santri** (Hadir/Telat/Alpha). **Vakasi guru ngaji TIDAK lewat
     Controlling** — akan ditambahkan terpisah lewat **Jadwal Mengajar** (mekanisme vakasi mengajar yang sudah ada).
     → Smart Controlling jadi lebih sederhana: murni kontrol kehadiran santri.

### 10.6 Masih perlu dikonfirmasi (saat Fase A / SSH)
- Kontrak API Ramah Anak (base URL, auth, endpoint absensi & laporan, master variabel).
- Ramah Anak **satu server** dgn An-Nur atau host terpisah (menentukan endpoint internal vs publik).
- Mode kirim batch: **per akhir window** atau **rekap akhir hari** (default: dorong via queue segera setelah tercatat, dgn retry).

*(Pertanyaan teknis Ramah Anak ditutup saat eksplorasi via SSH di Fase A.)*

---

*Dokumen ini akan diperbarui seiring hasil eksplorasi Ramah Anak (Fase A).*
