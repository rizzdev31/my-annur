# PRD — PWA Santri / Wali (Portal Monitoring)

**Produk:** An-Nur Smart System — Portal Santri (untuk Orang Tua/Wali)
**Jenis:** PWA (Progressive Web App), satu origin dengan Laravel, **read-only monitoring**
**Status:** Draft v1 — 2026-08-22
**Pemilik:** Rifqi (lensahati98@gmail.com)

---

## 1. Ringkasan & Tujuan

Membuat **portal khusus santri** berbasis web (PWA) agar **orang tua/wali** dapat **memantau** perkembangan anaknya di pesantren secara mandiri, kapan saja, tanpa perlu bertanya ke guru satu per satu.

Portal ini **hanya menampilkan (read-only)** data yang sudah dihasilkan oleh Smart System dari sisi guru/admin. Wali **tidak** menginput, menyetujui, atau mengubah apa pun. Semua data mengalir **satu arah**: Guru/Admin → Smart System → Portal Wali.

**Prinsip desain:**
1. **Monitoring saja** — tanpa aksi tulis (kecuali autentikasi).
2. **Terbatas 1 santri per sesi** — wali hanya melihat anaknya sendiri (isolasi data ketat).
3. **Reuse data & backend** yang sudah ada — tidak menduplikasi logika, hanya menyajikan.
4. **Mobile-first & installable** (Add to Home Screen), ringan, jelas untuk orang tua awam.
5. **Bahasa Indonesia**, istilah sederhana.

**Non-tujuan (v1):** pembayaran/SPP, chat guru-wali, pengajuan izin online oleh wali, notifikasi push mandiri (WA existing tetap jalan), integrasi mendalam ke RamahAnak.

---

## 2. Persona & Akses

| Persona | Kebutuhan | Akses |
|---|---|---|
| **Wali/Orang tua** (pengguna utama) | Tahu kehadiran, hafalan, nilai, kesehatan, izin anak | Login via nomor WA yang terdaftar pada santri; lihat 1 anak (atau pilih anak bila 1 nomor punya beberapa) |
| **Santri** (opsional) | Melihat progres sendiri | Sama seperti wali (akun = akun santri) |
| Admin/Guru | (di luar portal ini) | Tetap pakai panel admin + PWA guru |

> Satu **akun = satu santri** (identik dengan NIS). Bila satu nomor WA terdaftar pada beberapa santri (kakak-adik), portal menampilkan **pemilih anak** setelah login.

---

## 3. Arsitektur

Mengikuti pola PWA Guru yang sudah berjalan (satu origin, Vue SPA, API Sanctum), namun **terpisah total** dari data guru.

- **Frontend:** Vue 3 + Vue Router (`createWebHistory('/santri')`) + Vite + Tailwind. Lokasi: `resources/js/santri/`.
- **Dilayani Laravel:** `Route::get('/santri/{any?}')` → `resources/views/santri.blade.php`.
- **API:** namespace baru **`/api/santri/*`** (read-only), **guard/token terpisah** dari guru (abilities dibatasi). Token disimpan di `localStorage` (`santri_token`).
- **PWA manual:** `public/santri-manifest.json` + `public/santri-sw.js` (network-first navigasi, TIDAK cache `/api` & `/storage`). Ikon & tema warna berbeda dari app guru (mis. hijau/teal) agar tidak tertukar.
- **Isolasi:** setiap endpoint memaksa `santri_id` dari token, **mengabaikan** `santri_id` dari input → mustahil melihat anak orang lain.

```
Wali (HP) ──HTTPS──> /santri (Vue SPA) ──Bearer token──> /api/santri/* (read-only, scoped 1 santri)
                                                              └─> data existing (AbsensiSantri, Setoran, dst.)
```

---

## 4. Autentikasi & Keamanan

Santri **belum punya akun** (tabel `santri` tidak punya password). Portal memerlukan mekanisme login baru yang **aman tapi mudah untuk orang tua**.

### 4.1 Rekomendasi utama — Login OTP via WhatsApp (Fonnte)
Memanfaatkan `santri.no_whatsapp` + integrasi Fonnte yang sudah ada.

**Flow:**
1. Wali buka `/santri` → masukkan **NIS** (atau nomor WA).
2. Sistem cek santri aktif → kirim **kode OTP 6 digit** ke `no_whatsapp` santri (berlaku 5 menit, rate-limit 1 per 60 detik, maks 5 percobaan).
3. Wali memasukkan OTP → sistem membuat **token sesi** (Sanctum, ability `santri:read`, tokenable = santri) → simpan di `localStorage`.
4. Token berlaku mis. 30 hari (remember), auto-logout saat 401.

**Kelebihan:** tanpa password untuk dikelola, memverifikasi kepemilikan nomor, reuse WA.

### 4.2 Alternatif sederhana — NIS + Tanggal Lahir
Login dengan **NIS + tanggal lahir** santri. Lebih mudah tapi keamanan lebih rendah (data bisa ditebak). Cocok sebagai **fallback** bila WA santri kosong/tidak valid.

### 4.3 Keputusan
- **v1: OTP WA sebagai metode utama**, **NIS + tanggal lahir sebagai fallback** (untuk santri tanpa `no_whatsapp`).
- Perlu tabel/kolom baru: `santri_otp` (santri_id, kode_hash, expired_at, attempts) **atau** kolom OTP sementara. **Tidak** menambah kolom password ke `santri`.
- Token via Sanctum dengan `tokenable_type = Santri` dan **ability terbatas** `santri:read`; seluruh endpoint `/api/santri/*` menolak token guru dan sebaliknya.

**Aturan keamanan:**
- Semua endpoint **scoped** ke `santri_id` token (server-side), abaikan input klien.
- **Read-only** — tidak ada endpoint tulis selain auth (OTP).
- Foto/berkas via URL request-host (`url()`), bukan `asset()` localhost.
- HTTPS wajib di produksi. Rate-limit login/OTP. Log aktivitas login.

---

## 5. Fitur & Flow (Modul)

Urutan menu = urutan prioritas monitoring wali. Semua **read-only**.

### 5.1 Beranda & Profil Santri
- **Isi:** foto, nama, NIS, kelas, program Qur'an (tahfidz/tahsin), level tahsin, status aktif; ringkasan cepat (kehadiran bulan ini, hafalan terkini, notifikasi terbaru).
- **Sumber:** `Santri`, `kelas`, ringkasan agregat.
- **Flow:** login → beranda (kartu ringkas + grid menu ke modul lain).

### 5.2 Absen Pembelajaran Sekolah (KBM)
- **Isi:** rekap kehadiran per mata pelajaran & harian: Hadir/Telat/Alpha, per tanggal & per sesi; grafik ringkas per bulan; daftar keterlambatan/alpha.
- **Sumber:** `AbsensiSantri` (status per sesi) ↔ `AbsensiMengajar` (tanggal, mapel, guru).
- **Flow:** pilih rentang/bulan → lihat rekap + rincian per sesi.

### 5.3 Tahfidz — Progres, Nilai & Sertifikat
- **Isi:** total ayat & persentase hafal, juz selesai, setoran terakhir + nilai, riwayat setoran (ziyadah/murojaah), **daftar Tasmi' lulus per juz + breakdown rubrik + sertifikat** (cetak/PDF).
- **Sumber:** `HafalanSantri`, `HafalanJuz`, `SetoranTahfidz`, `TugasTasmi` (nilai + sertifikat `.../tasmi/{id}/sertifikat`).
- **Flow:** ringkasan progres → riwayat setoran → Tasmi' lulus → Lihat/Unduh Sertifikat.

### 5.4 Tahsin — Level, Nilai & Sertifikat
- **Isi:** level saat ini + label ("Level N"/"Persiapan Tahfidz"), progres materi per level, riwayat penilaian materi, **daftar Tasnif (ujian kenaikan) lulus + breakdown 4 rubrik + sertifikat**.
- **Sumber:** `TahsinPenilaian`, `TahsinPenilaianRiwayat`, `SettingTahsinMateri`, `TugasTasnif` (sertifikat `.../tasnif/{id}/sertifikat`).
- **Flow:** ringkasan level → progres materi → Tasnif lulus → Lihat/Unduh Sertifikat.

### 5.5 Smart Controlling (Kehadiran Kegiatan Harian)
- **Isi:** kehadiran kegiatan pesantren (sholat berjamaah, kajian, dll.) per hari: Hadir/Telat/Alpha; rekap per periode; daftar alpha/telat.
- **Sumber:** `ControllingAbsensi` ↔ `ControllingJadwal`/`ControllingKegiatan`/`ControllingPeriode`.
- **Flow:** pilih periode/bulan → rekap + rincian per kegiatan/tanggal.

### 5.6 Izin
- **Isi:** daftar izin santri (jenis syar'i/non-syar'i, alasan, tanggal mulai–selesai, status: diajukan/disetujui/ditolak, catatan petugas).
- **Sumber:** `IzinSantri`.
- **Flow:** daftar izin (read-only). *Pengajuan izin oleh wali = out of scope v1 (lihat §9).*

### 5.7 Kesehatan (Smart Health)
- **Isi:** laporan kesehatan anak: penyakit/keluhan, status (menunggu/dalam pengecekan/selesai/ditolak), timeline pemantauan petugas, tanggal.
- **Sumber:** `SmartHealthLaporan`, `SmartHealthPengecekan`.
- **Flow:** daftar laporan → detail + timeline pemantauan.

### 5.8 Smart Eksekusi (Pelanggaran/Apresiasi) — *terbatas v1*
- **Catatan:** modul ini berhubungan dengan **aplikasi RamahAnak** (integrasi via outbox). Untuk v1, **fokus data yang ada di Smart System dulu**; sinkronisasi/rincian penuh ke RamahAnak akan disesuaikan kemudian oleh pemilik produk.
- **Isi v1 (jika tersedia lokal):** ringkasan catatan pelanggaran/apresiasi santri yang tercatat di Smart System (tanpa menampilkan detail sensitif RamahAnak).
- **Sumber:** data eksekusi/outbox Smart Habbit (didefinisikan menyusul saat penyesuaian RamahAnak).
- **Flow:** ringkasan read-only; detail penuh menunggu keputusan integrasi RamahAnak.

---

## 6. Rancangan API (read-only, prefix `/api/santri`)

Semua endpoint: **Bearer token santri**, otomatis scoped `santri_id` token.

| Endpoint | Fungsi |
|---|---|
| `POST /api/santri/auth/minta-otp` | {nis atau no_wa} → kirim OTP WA |
| `POST /api/santri/auth/verifikasi-otp` | {nis, kode} → token sesi |
| `POST /api/santri/auth/login-sederhana` | {nis, tanggal_lahir} → token (fallback) |
| `GET /api/santri/me` | profil + ringkasan beranda |
| `GET /api/santri/absensi` | rekap KBM (params: bulan/rentang) |
| `GET /api/santri/tahfidz` | progres + riwayat setoran + tasmi lulus |
| `GET /api/santri/tahfidz/tasmi/{id}/sertifikat` | data sertifikat tasmi' |
| `GET /api/santri/tahsin` | level + progres materi + tasnif lulus |
| `GET /api/santri/tahsin/tasnif/{id}/sertifikat` | data sertifikat tasnif |
| `GET /api/santri/controlling` | rekap kehadiran kegiatan (params: periode) |
| `GET /api/santri/izin` | daftar izin |
| `GET /api/santri/kesehatan` | daftar laporan + detail pengecekan |
| `GET /api/santri/eksekusi` | ringkasan (terbatas, menyusul) |

> Endpoint sertifikat mengembalikan data yang sama seperti versi guru namun **divalidasi milik santri token**. Halaman sertifikat memakai desain yang sudah ada (A4, cetak/PDF).

---

## 7. Sentuhan Data & Perubahan Backend

- **Tanpa mengubah** data existing (read-only). Tambahan minimal:
  - Mekanisme **OTP** (tabel `santri_otp` atau setara) — additive.
  - **Sanctum token** untuk `Santri` (tokenable morph sudah didukung Sanctum) + ability `santri:read`.
  - Controller baru `App\Http\Controllers\Api\Santri\*` yang **membungkus** query existing dengan scope santri.
- **Foto/berkas**: pastikan URL pakai `url()` (request-host) agar tampil di HP wali.

---

## 8. Non-Fungsional

- **Privasi:** isolasi ketat per santri; tidak ada enumerasi santri lain; PII minimal.
- **Keamanan:** OTP rate-limit, token kadaluarsa, HTTPS, tanpa endpoint tulis.
- **Performa:** query agregat ringan, pagination pada riwayat panjang, skeleton loading.
- **PWA:** installable, offline shell (bukan data), ikon/tema khusus santri.
- **Aksesibilitas:** teks besar, kontras cukup, bahasa awam (untuk orang tua).

---

## 9. Fase / Roadmap

**Fase 0 — Fondasi & Auth**
- SPA `/santri`, layout, tema; auth OTP WA + fallback NIS+tgl lahir; `me`/beranda.

**Fase 1 — MVP Monitoring inti**
- Absen KBM, Tahfidz (progres+nilai+sertifikat), Tahsin (level+nilai+sertifikat).

**Fase 2 — Kepesantrenan**
- Smart Controlling, Izin, Kesehatan.

**Fase 3 — Eksekusi & penyesuaian RamahAnak**
- Smart Eksekusi ringkas (fokus Smart System), lalu sinkron RamahAnak sesuai keputusan pemilik.

**Fase 4 — Peningkatan (future)**
- Pilih anak (multi-santri per WA), notifikasi ringkasan mingguan, unduh rapor gabungan PDF.

---

## 10. Out of Scope (v1)

- Pembayaran/SPP, chat guru-wali, pengajuan izin online oleh wali, edit data santri.
- Integrasi penuh & aksi ke RamahAnak (menyusul).
- Notifikasi push web (WA existing tetap menjadi kanal utama).

---

## 11. Alur Utama (Ringkas)

```
[Login]  buka /santri → input NIS → OTP ke WA → verifikasi → token
   └─(fallback)→ NIS + tanggal lahir → token

[Pakai]  Beranda (ringkasan) → pilih modul:
   ├─ Absen KBM ───────── rekap H/T/A per mapel & bulan
   ├─ Tahfidz ─────────── progres → setoran → Tasmi' lulus → Sertifikat (PDF)
   ├─ Tahsin ──────────── level → materi → Tasnif lulus → Sertifikat (PDF)
   ├─ Smart Controlling ─ kehadiran kegiatan per periode
   ├─ Izin ────────────── daftar izin & status
   ├─ Kesehatan ───────── laporan + timeline pemantauan
   └─ Eksekusi ────────── ringkasan (terbatas, menyusul RamahAnak)
```

---

## 12. Kriteria Sukses

- Wali dapat login & melihat data anaknya < 1 menit tanpa bantuan.
- 100% data yang ditampilkan **milik santri yang benar** (nol kebocoran lintas santri).
- Sertifikat Tahfidz (Tasmi') & Tahsin (Tasnif) dapat dilihat & dicetak/PDF oleh wali.
- Menurunkan pertanyaan manual wali ke guru soal kehadiran/hafalan/kesehatan.
