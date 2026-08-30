# PRD — Izin Sementara (Partial-Day Leave) + Sambung Guru Pengganti

**Produk:** An-Nur Smart System — Modul Perizinan
**Jenis:** Fitur baru (self-service PWA guru) yang menyambung ke mesin Guru Pengganti
**Status:** Draft v1 — 2026-08-30
**Pemilik:** Rifqi (lensahati98@gmail.com)

---

## 1. Ringkasan & Tujuan

Guru yang **sudah check-in harian** kadang harus **meninggalkan tempat kerja sebentar di tengah jam kerja** (mis. keperluan mendadak 1–2 jam) lalu kembali lagi. Kondisi ini berbeda dari izin sehari penuh yang sudah ada.

**Izin Sementara** adalah izin **berbasis rentang jam dalam satu hari** yang:

1. **TIDAK** mengubah absen harian guru → guru tetap **HADIR**.
2. Menandai satu **window waktu** (mis. 10:00–12:00) saat guru tidak di tempat.
3. **Otomatis mendeteksi** sesi mengajar guru yang beririsan dengan window tsb, lalu **menyambung ke alur Guru Pengganti** agar kelas tetap berjalan.

**Masalah yang diselesaikan:** ketika guru izin mendadak di tengah jam, kelas yang seharusnya ia ajar tidak kosong — sistem langsung memfasilitasi penunjukan pengganti.

---

## 2. Keputusan Desain (sudah disepakati)

| Aspek | Keputusan |
|---|---|
| **Persetujuan** | **Tanpa approval** — self-service, berlaku seketika (kondisi mendadak). Admin cukup dapat **notifikasi**. |
| **Penunjukan pengganti** | **Guru sendiri** yang menunjuk pengganti (reuse alur `tunjuk-pengganti` yang sudah ada). |
| **Efek kinerja guru** | **Netral** — sesi yang dialihkan **tidak dihitung** sebagai pelanggaran mengajar guru. |
| **Absen harian** | Tetap **hadir** (tidak berubah). |
| **JP / payroll** | JP sesi terdampak **mengalir ke pengganti** (via alur existing), bukan ke guru asli. |

---

## 3. Ruang Lingkup

### Termasuk (in-scope)
- Jenis izin baru **"Izin Sementara"** berbasis jam (satu hari).
- Endpoint PWA: ajukan izin sementara (auto-disetujui) + kembalikan daftar sesi mengajar terdampak.
- **Admin panel:** superadmin bisa membuatkan izin sementara atas nama guru + tunjuk pengganti.
- Sesi terdampak **tanpa pengganti** → ditandai "izin tanpa pengganti", JP hangus, izin tetap berlaku (guru netral).
- Sambungan ke `PenggantiMengajarService::tunjukPengganti` (reuse penuh).
- Pengecualian di absensi (check-in/out & absen harian tak terpengaruh).
- Netralisasi kinerja untuk sesi terdampak.
- Notifikasi ke admin (info) + guru pengganti (tugas baru).
- Tampilan admin: log izin sementara (read + batal darurat).

### Tidak termasuk (out-of-scope, tahap berikutnya)
- Approval berjenjang / kuota izin sementara per bulan.
- Izin sementara lintas hari atau berulang.
- Auto-assign pengganti (sistem memilihkan) — untuk sekarang **manual** oleh guru.
- Potongan gaji guru asli akibat izin sementara (sesuai keputusan: netral).

---

## 4. Perubahan Data

### 4.1 Tabel `pengajuan_izin` — tambah kolom (nullable, non-breaking)
| Kolom | Tipe | Keterangan |
|---|---|---|
| `jam_mulai` | `TIME` null | Awal window (mis. 10:00). Null = izin sehari penuh (perilaku lama). |
| `jam_selesai` | `TIME` null | Akhir window (mis. 12:00). |
| `is_sementara` | `BOOLEAN` default false | Penanda cepat jenis partial-day. |

> Izin lama tetap valid: `jam_*` null + `is_sementara=false`.

### 4.2 `SettingJenisPengajuan` — jenis baru
- **"Izin Sementara"**, kategori baru **`sementara`** (dibedakan dari `izin`/`sakit`/`cuti`/`dinas`).
- `getStatusAbsensi()` untuk kategori `sementara` → **tidak dipakai untuk absen harian** (guru tetap hadir). Lihat §6.

### 4.3 Model `PengajuanIzin`
- Cast `jam_mulai`/`jam_selesai`.
- Helper: `isSementara(): bool`, scope `sementaraAktif($tpId, $tgl, $jam)`.

---

## 5. Alur Utama (Happy Path)

```
Guru (tengah jam kerja, sudah check-in)
  └─ PWA → tab Perizinan → "Izin Sementara"
       ├─ pilih jam_mulai–jam_selesai + alasan
       └─ submit
            ↓  (backend: buat PengajuanIzin is_sementara, status=disetujui langsung)
      Sistem cari JadwalMengajar guru HARI INI yg beririsan window
            ↓
      ┌─ Ada sesi terdampak? ─┐
      YA                       TIDAK
      ↓                        ↓
  Tampilkan daftar sesi     Selesai (hanya catatan izin)
  "butuh pengganti"
      ↓
  Guru tunjuk pengganti per sesi  → reuse tunjukPengganti()
      ↓
  Notifikasi: admin (info) + pengganti (tugas)
      ↓
  Pengganti absen-pengganti → JP mengalir ke pengganti
```

**Deteksi irisan (overlap):** sesi terdampak = `JadwalMengajar` guru pada `hari` hari ini dengan
`jam_mulai < izin.jam_selesai AND jam_selesai > izin.jam_mulai` (pola sama dgn cek bentrok existing).

---

## 6. Titik Integrasi (WAJIB)

### 6.1 Absensi — `AbsensiWindowService::deteksiIzinAktif()` & `statusAbsen()`
- Saat ini `deteksiIzinAktif` mengembalikan izin `disetujui` apa pun di tanggal itu → memblok check-in/out & menandai izin sehari.
- **Perubahan:** izin dengan `is_sementara=true` **dikecualikan** dari deteksi izin-harian. Guru tetap bisa/harus check-in & check-out normal, absen harian = **hadir**.

### 6.2 Kinerja — `KinerjaCalculationService` (komponen mengajar)
- Sesi mengajar yang **tercakup izin sementara & sudah dialihkan ke pengganti** → **dikeluarkan dari penyebut** kinerja mengajar guru asli (netral, bukan pelanggaran).
- JP untuk pengganti tetap dihitung ke pengganti (alur existing tak berubah).

### 6.3 Guru Pengganti — `PenggantiMengajarService`
- **Reuse penuh** `tunjukPengganti(jadwalId, guruTpId, penggantiId, tanggal, keterangan)`.
- Keterangan diisi otomatis: `"Izin sementara 10:00–12:00"`.
- Cek bentrok pengganti sudah ditangani service (tidak perlu ubah).

### 6.4 Notifikasi — `NotifikasiService`
- Event baru `izin_sementara`:
  - **Admin/pimpinan:** info "Guru X izin sementara 10:00–12:00 (n sesi dialihkan)".
  - **Pengganti:** "Anda ditunjuk menggantikan kelas Y (10:00–12:00)".

### 6.5 Payroll — tidak berubah
- JP pengganti dibayar via mekanisme vakasi/pengganti yang ada. Guru asli tidak dipotong (netral).

---

## 7. Edge Cases & Aturan

| # | Kasus | Aturan |
|---|---|---|
| 1 | Guru **belum check-in** lalu ajukan izin sementara | Tolak / arahkan: izin sementara hanya untuk yang **sudah check-in** (kalau belum masuk, pakai izin harian biasa). |
| 2 | Window **tidak beririsan** sesi mengajar mana pun | Izin tetap tercatat; tidak ada langkah pengganti. |
| 3 | Window beririsan **>1 sesi** | Tampilkan semua; guru tunjuk pengganti per sesi. |
| 4 | **Pengganti bentrok** jadwal | `tunjukPengganti` sudah menolak (pesan bentrok) — guru pilih pengganti lain. |
| 5 | Guru **batalkan** izin sementara | Batalkan izin + `batalkanPengganti` sesi terkait (selama pengganti belum absen). |
| 6 | Pengganti **sudah terlanjur absen** lalu izin dibatalkan | Sesi tetap milik pengganti (JP sudah jalan); izin ditandai "selesai/berjalan". |
| 7 | Izin sementara **melebihi jam pulang** | Clamp ke jam pulang; sesi setelah jam kerja tak relevan. |
| 8 | Dua izin sementara **tumpang tindih** di hari sama | Cegah overlap ganda (validasi window vs izin sementara aktif lain). |
| 9 | Window **sudah lewat** saat diajukan (backdate) | v1: hanya untuk **sekarang/ke depan** di hari ini; backdate ditolak. |

---

## 8. Rencana Implementasi Bertahap

**Tahap 1 — Fondasi (backend data & logic)**
1. Migration: kolom `jam_mulai/jam_selesai/is_sementara` + seed jenis "Izin Sementara".
2. Model `PengajuanIzin`: cast + helper + scope.
3. Service `IzinSementaraService`: buat izin (auto-approve) + deteksi sesi terdampak (overlap).

**Tahap 2 — Sambungan pengganti & absensi**
4. Endpoint API: `POST /izin/sementara` (buat + kembalikan sesi terdampak), reuse `tunjuk-pengganti`.
5. Sesuaikan `AbsensiWindowService` (exclude sementara).
6. Sesuaikan `KinerjaCalculationService` (netral sesi terdampak).

**Tahap 3 — UI & notifikasi**
7. PWA guru: alur "Izin Sementara" di halaman Perizinan (pilih jam → daftar sesi → tunjuk pengganti).
8. Event notifikasi `izin_sementara` (admin + pengganti).
9. Admin: tampilan/log izin sementara (+ batal darurat).

**Tahap 4 — Uji & poles**
10. Uji edge cases (§7), build, verifikasi di VPS.

---

## 9. Keputusan Brainstorm (RESOLVED 2026-08-30)
1. **Durasi maksimum:** v1 **bebas sampai jam pulang** (di-clamp ke jam pulang). Tanpa batas keras; bisa ditambah cap nanti bila perlu.
2. **Kuota/frekuensi:** v1 **tanpa kuota**. Pantau lewat log admin dulu; batasi belakangan bila ada penyalahgunaan.
3. **Tanpa pengganti → ✅ Sesi kosong, JP hangus, izin tetap berlaku.** Sesi ditandai "izin tanpa pengganti" (seperti alur izin harian existing), guru asli tetap **netral**. Tidak menahan izin.
4. **Admin boleh buatkan → ✅ Ya, admin & guru bisa.** Guru self-service via PWA; admin juga bisa membuatkan dari panel superadmin atas nama guru + tunjuk pengganti. (Masuk scope v1.)
5. **Checkout parsial:** v1 **cukup catatan window** (jam_mulai–jam_selesai) di record izin; **tanpa** auto-checkout/absen keluar-masuk. Absen harian tetap satu kali seperti biasa.

---

## 10. Kriteria Sukses
- Guru bisa ajukan izin sementara <10 detik dari PWA saat mendadak.
- Sesi mengajar terdampak langsung terlihat + bisa ditunjuk pengganti tanpa admin.
- Absen harian & kinerja guru **tidak dirugikan** oleh izin sementara yang sah.
- Kelas tidak kosong: pengganti dapat notifikasi & JP mengalir benar.
