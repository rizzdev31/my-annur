# PRD — Guru Piket & Penilaian Kinerja Keliling

> Status: **DRAFT (revisi-1)** — sudah memuat keputusan dari diskusi. Belum dieksekusi.
> Migrasi nanti **ADITIF**. Eksekusi bertahap & sinkron setelah disetujui.

---

## 1. Ringkasan & Tujuan
**Guru Piket** = guru yang **ditunjuk per hari** untuk **menilai kinerja proses mengajar guru lain**
secara **keliling**. Fungsinya **hanya memberi penilaian kinerja** (bukan mengajar).

Tujuan:
- Kinerja guru dinilai dari **proses mengajar nyata**, bukan hanya data otomatis.
- Penilaian **dua arah** (apresiasi & catatan) → adil, tidak hanya menghukum.
- **Sinkron** dengan Setting Kinerja (komponen berbobot).
- **Vakasi piket khusus** (bukan tugas tambahan).
- Menutup celah: guru tak konfirmasi mengajar → **piket** mengisi absensi santri.

---

## 2. Penugasan Piket (mengikuti jam kerja guru — TANPA split lingkungan)
- **Tidak ada** pemisahan pesantren/sekolah. Piket **ditunjuk per orang per tanggal**.
- **Window aktif piket = jam absen masuk s/d pulang piket itu sendiri** pada hari tsb
  (mengikuti jam kerja masing-masing — ada guru shift sore, dst).
- Piket hanya bisa menilai/mengisi **selama ia sudah check-in & belum check-out** (sinkron absensi harian).
- Di luar window → aksi piket ditolak.
- Boleh ada >1 piket pada hari yang sama (mis. shift pagi & sore).

---

## 3. Aktor & Peran
| Aktor | Peran |
|---|---|
| **Superadmin** | Menunjuk piket per hari; atur Setting Kinerja (+komponen piket), Setting Vakasi Piket, rubrik kategori. |
| **Guru Piket (ditunjuk harian)** | Lewat **fitur Guru Piket**: pilih guru yang dinilai → isi penilaian (kategori + poin) + catatan; mengisi **laporan harian piket** (wajib, default "semua aman"); mengisi absensi santri untuk sesi yang gurunya tak konfirmasi. |
| **Guru (dinilai)** | Objek penilaian; dapat **melihat** catatan kinerjanya & **mengajukan sanggah**. |

---

## 4. Fitur Guru Piket (input penilaian)
- Diisi **oleh guru yang sedang ditugaskan piket** lewat **fitur tersendiri** (aplikasi guru / Flutter).
- Alur: pilih **guru yang dinilai** → pilih **kategori** (dari rubrik) → **poin** otomatis dari kategori →
  isi **catatan** → simpan.
- **Laporan harian piket**: karena tidak setiap hari ada temuan, piket cukup mengisi **catatan harian**
  (mis. "semua aman"). Catatan harian ini **wajib** untuk klaim vakasi piket hari itu.

---

## 5. Model Penilaian
### 5.1 Bentuk
- **Dua jenis**: `apresiasi` (poin +) & `catatan` (poin −). Wajib ada keduanya supaya adil.
- Setiap penilaian: `guru_dinilai`, `kategori`, `poin` (snapshot dari rubrik), `catatan`,
  `dimensi` (ikut kategori), `bukti?` opsional, `jadwal/sesi terkait?` opsional, `piket`, `waktu`.

### 5.2 Rubrik kategori (master, diatur Superadmin)
- `nama`, `jenis` (`apresiasi|catatan`), **poin baku** (atau rentang), **dimensi**
  (`disiplin | tugas | administrasi`), `is_aktif`.
- **Dimensi** = penanda agar penilaian selaras dengan dimensi kinerja & **dikelompokkan di laporan**
  (catatan telat → dimensi *disiplin*; apresiasi metode/penguasaan → *tugas*; kerapihan jurnal → *administrasi*).
- Contoh: "Tepat waktu & siap di kelas" (+, disiplin), "Metode interaktif" (+, tugas),
  "Jurnal rapi & lengkap" (+, administrasi), "Telat masuk kelas" (−, disiplin), "Kelas tidak kondusif" (−, tugas).

---

## 6. Reset Kinerja Awal = 100 (sebelum deploy)
- Aksi admin **"Reset Kinerja"**: arsip/kosongkan rekap lama (`rekap_kinerja_bulanan`) + tetapkan
  **sub-skor piket awal = 100** untuk periode berjalan.
- "100" = **titik awal komponen Piket** (lihat §7), bukan mengganti seluruh rumus kinerja.

---

## 7. Integrasi dengan Setting Kinerja (SINKRON — model final)
Rumus sekarang: `SKOR_TOTAL = Σ (skor_komponen × bobot_komponen%)`, tiap komponen 0–100.

**Bobot final (disepakati):**
| Komponen | Bobot |
|---|---|
| Absensi | **35%** |
| Tugas | **30%** |
| Administrasi | **20%** |
| **Piket (baru)** | **15%** |

**Sub-skor Piket** (per guru per periode), mulai dari 100:
```
skor_piket = clamp( 100 + Σ poin_apresiasi − Σ poin_catatan , 0 , 100 )
```
- Ditambahkan ke `KinerjaCalculationService` sebagai `komponenPiket()` lalu masuk `skorTotal` dgn `bobot_piket`.
- `setting_kinerja.bobot_piket` (kolom baru). Saat rilis awal **boleh 0** (netral) lalu dinaikkan ke 15%.

**Penting — keputusan poin 4 (anti dobel-hitung):**
- **Semua poin piket masuk ke bucket Piket 15%.** Tag `dimensi` (disiplin/tugas/administrasi) **hanya untuk
  pelaporan/pengelompokan**, BUKAN merutekan poin ke komponen Absensi/Tugas/Administrasi.
- Alasan: (a) menjaga total bobot tetap 100%; (b) **telat objektif sudah** menurunkan **Absensi 35%**
  lewat cutoff jam_selesai yang sudah berjalan → piket **tidak menilai ulang telat yang sama**
  (hindari dobel-hukuman). Kategori "disiplin" piket untuk hal kualitatif yang tak terdeteksi sistem.
- Laporan kinerja menampilkan **rincian piket per dimensi** (apresiasi/catatan disiplin–tugas–administrasi)
  sehingga maksud "telat→absensi, apresiasi→tugas/admin" tetap terlihat jelas.

> Alternatif (TIDAK disarankan): merutekan poin piket langsung ke komponen Absensi/Tugas/Admin →
> membuat 15% tak terpakai + risiko dobel-hitung telat. Bila benar-benar diinginkan, perlu redesain bobot.

---

## 8. Vakasi Piket (khusus — per penugasan/hari)
- **Basis: per hari penugasan piket** (flat), **bukan** per jumlah penilaian, **bukan** tugas tambahan.
- Syarat bayar: piket **hadir kerja** hari itu (window §2) **dan** mengisi **laporan harian piket**
  (minimal "semua aman").
- Diatur di **Setting Vakasi Piket** tersendiri (`nominal_per_hari`, `is_aktif`).
- Masuk slip gaji sebagai komponen terpisah **"Vakasi Piket"**.

---

## 9. Integrasi dengan Absen Mengajar (handoff)
Menyambung cutoff yang **sudah berjalan** (jam_selesai = patokan; lewat → `tidak_terlaksana`, vakasi hangus).
Saat fitur Piket aktif:
- **Guru tak konfirmasi mengajar** → **absensi santri jadi tugas piket**.
- **Tahfidz & Tahsin:** guru telat **tidak lagi mengisi absensi santri** (jadi hak piket), **tetapi guru
  tetap bisa mengisi jurnal/setoran** sesuai logika berjalan. → alur tahfidz/tahsin **dipisah**:
  *pembuatan sesi (tidak_terlaksana) + jurnal/setoran* oleh guru; *absensi santri* oleh piket.
- **KBM Sekolah:** sesi tanpa konfirmasi guru → piket mengisi absensi santri (sesi `tidak_terlaksana`).
- **Guru pengganti** tetap tunduk cutoff yang sama (sudah berjalan) — exception handling lanjutan bila perlu.

> Sampai fitur piket dibangun, perilaku sekarang dipertahankan (guru telat masih bisa mengisi absensi santri sebagai fallback).

---

## 10. Sketsa Model Data (USULAN — aditif)
- `piket_jadwal` — `tanggal`, `tenaga_pendidik_id` (piket), `ditunjuk_oleh`, `catatan_harian`
  (laporan harian, default "semua aman"), `vakasi_dibayar` (bool). (penunjukan harian; window ikut absensi harian piket)
- `piket_kategori` — `nama`, `jenis` (`apresiasi|catatan`), `poin` (atau `poin_min/maks`),
  `dimensi` (`disiplin|tugas|administrasi`), `is_aktif`. (rubrik)
- `piket_penilaian` — `piket_jadwal_id`, `guru_dinilai_id`, `kategori_id`, `poin` (snapshot), `dimensi` (snapshot),
  `catatan`, `bukti_foto?`, `jadwal_mengajar_id?`/`absensi_mengajar_id?`, `waktu`,
  `status_sanggah` (`-|diajukan|diterima|ditolak`).
- `setting_kinerja.bobot_piket` (kolom baru, default 0 → set 15 saat aktif; sesuaikan 35/30/20/15).
- `setting_vakasi_piket` — `nominal_per_hari`, `is_aktif`.
- Absensi santri oleh piket: **reuse `absensi_santri`** yang ada (tak perlu tabel baru).

---

## 11. Tahapan Implementasi (usulan)
- **Fase 0** — Reset kinerja (arsip + baseline 100) + kolom `bobot_piket` (default 0, netral) + sesuaikan bobot 35/30/20/0→15.
- **Fase 1** — Penunjukan piket harian (`piket_jadwal`) + window jam kerja (sinkron absensi harian).
- **Fase 2** — Rubrik kategori (`piket_kategori`, ber-dimensi) + **fitur Guru Piket** (input penilaian + laporan harian).
- **Fase 3** — `komponenPiket()` di KinerjaCalculationService + laporan kinerja per dimensi (rasio +/−).
- **Fase 4** — Handoff absensi santri ke piket (KBM + pemisahan jurnal tahfidz/tahsin) + exception pengganti.
- **Fase 5** — Vakasi piket (Setting Vakasi Piket + masuk payroll).
- **Fase 6** — Hak sanggah/verifikasi + audit trail.

---

## 12. Masukan agar FAIR & Profesional
1. **Dua arah wajib** (apresiasi + catatan); laporan tampilkan **rasio positif:negatif** per dimensi.
2. **Pisahkan objektif vs subjektif** — telat/kehadiran tetap otomatis di Absensi; piket menilai **kualitatif** → tanpa dobel-hukuman.
3. **Rubrik & poin baku** (master kategori, bukan ketik bebas) → konsisten antar-penilai.
4. **Batas harian (cap)** poin +/− per guru per piket → cegah dominasi/abuse.
5. **Multi-observer & rotasi** penunjukan → kurangi bias individu.
6. **Anti konflik kepentingan** — piket tidak menilai diri sendiri.
7. **Transparansi** — guru melihat siapa/kapan/kategori/poin/catatan atas dirinya.
8. **Hak sanggah + verifikasi atasan** untuk penilaian signifikan.
9. **Bukti pendukung** opsional untuk poin besar.
10. **Normalisasi & clamp** sub-skor piket [0,100], mulai 100 tiap periode.
11. **Audit trail / immutability** — koreksi tercatat (siapa & kapan).
12. **Rilis netral dulu** (`bobot_piket = 0`), naikkan ke 15% setelah rubrik matang & guru terbiasa.

---

## 13. Status Keputusan
| # | Topik | Keputusan |
|---|---|---|
| 1 | Split lingkungan pesantren/sekolah | **Tidak** — piket ikut jam kerja (absen masuk–pulang) tiap guru. |
| 2 | Basis vakasi piket | **Per hari penugasan** (flat) + wajib laporan harian (min. "semua aman"). |
| 3 | Bobot kinerja | Absensi 35 / Tugas 30 / Administrasi 20 / **Piket 15**. |
| 4 | Cara poin piket masuk skor | **Bucket Piket 15%** + tag **dimensi** untuk laporan (bukan rute ke komponen lain; hindari dobel-hitung). |
| 5 | Pengisian penilaian | Lewat **fitur Guru Piket** (aplikasi guru) oleh guru yang ditunjuk. |

### Masih perlu konfirmasi (sebelum Fase 2–3)
- **Rentang poin per kategori** & **cap harian** (rubrik default).
- Apakah perlu **bukti foto** wajib untuk catatan (−) signifikan?
- Alur **hak sanggah** & siapa verifikator final.

---

> Setelah disetujui, eksekusi mulai **Fase 0** (paling aman: netral, belum mengubah skor sampai bobot piket dinaikkan).
