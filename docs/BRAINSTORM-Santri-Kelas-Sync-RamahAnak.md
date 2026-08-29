# Brainstorming — Perpindahan Kelas Santri & Sinkronisasi Smart ↔ RamahAnak

**Status:** Analisis & rancangan alur (belum implementasi) — 2026-08-22
**Fokus utama saat ini:** database santri agar SINKRON. Smart System = sumber kebenaran (master). RamahAnak (RA) menyimpan data khusus aplikasinya saja.

---

## 1. Kondisi Saat Ini (hasil analisis)

### Smart System (annur-smart-system)
- `santri`: identitas master. **`nip` = NIS** (kunci). Ada `no_whatsapp`, `program_quran`, `tahsin_level`.
- `kelas`: **per tahun ajaran** (`kelas.tahun_ajaran_id`, `tingkat`, `jenis`, `level_tahsin`). Jadi "10A 2025/2026" ≠ "10A 2026/2027" (baris kelas berbeda).
- `kelas_santri` (pivot m-n): `{kelas_id, santri_id, tanggal_masuk, is_aktif}`.
  - ❌ Belum ada `tanggal_keluar`.
  - ❌ Belum ada `tahun_ajaran_id` di pivot (harus lewat `kelas`).
  - ❌ Belum ada workflow "naik kelas / pindah kelas" (massal maupun per-santri).
- Data akademik (AbsensiSantri, SetoranTahfidz, TahsinPenilaian, TugasTasmi/Tasnif, IzinSantri, SmartHealth, ControllingAbsensi) **semuanya ber-key `santri_id` + tanggal** — TIDAK terhapus saat pindah kelas. Konteks kelas/tahun-ajaran bisa **diturunkan dari tanggal**.
- Kirim ke RA: `App\Services\AbsensiRamahAnak` + outbox (WaOutbox pattern) memakai `nip` sebagai `nisn_pelaku`.

### RamahAnak (RA — C:\Users\Rifqi\Documents\HKI Tugas Akhir\RA)
- Laravel. Santri = `users` + `santri_profiles` `{nisn, nama, kelas_id→kelas(RA), jenis_kelamin, nama_wali, …}`.
- ✅ **`riwayat_kelas_santri`** `{user_id, kelas_id, tahun_ajaran(string), is_active, tanggal_masuk, tanggal_keluar, keterangan, unique(user_id, tahun_ajaran)}` — sudah punya histori kelas lengkap (lebih maju dari Smart).
- Data khusus RA: `SantriExpertSystemTracking`, `BimbinganBerkalaAntrian`, laporan pelanggaran/apresiasi/konselor.
- API integrasi: `POST /api/eksekusi/{pelanggaran|apresiasi|konselor}`, `GET /api/variabel/*`, `GET /api/santri/{nisn}`.

### Kesimpulan analisis
- **Kunci sinkron sudah jelas: NISN (RA) = NIP/NIS (Smart).**
- **RA sudah punya model histori kelas** (`riwayat_kelas_santri`) yang bisa jadi acuan desain Smart.
- Yang perlu dibangun: (A) model + workflow perpindahan kelas di Smart yang menjaga histori, (B) mekanisme sinkron santri Smart→RA dengan Smart sebagai master.

---

## 2. Prinsip

1. **Smart = master data santri** (identitas, kelas, tahun ajaran, wali). RA **tidak** mengedit field master; hanya cermin tipis + data fiturnya sendiri.
2. **Tidak ada data dihapus** saat pindah kelas — histori per kelas+tahun ajaran disimpan & bisa dipakai lagi kapan saja.
3. **Kunci join = NISN/NIS** (stabil, unik, tak berubah saat pindah kelas).
4. **Fleksibel & bisa override**: naik kelas massal, tapi tetap bisa dikecualikan per-santri (tinggal kelas / pindah manual / mutasi).

---

## 3. Bagian A — Alur Perpindahan Kelas (Smart)

### 3.1 Perubahan model (additive, tidak merusak data)
Lengkapi `kelas_santri` agar setara `riwayat_kelas_santri` RA:
- `+ tanggal_keluar` (date, nullable) — kapan santri keluar kelas itu.
- `+ tahun_ajaran_id` (denormalisasi dari `kelas`, untuk query histori cepat & anti-ambigu).
- (opsional) `+ keterangan` (mis. "naik kelas", "mutasi masuk", "tinggal kelas").
- `is_aktif` tetap = kelas SEKARANG.

> Alternatif: buat tabel baru `riwayat_kelas_santri` di Smart (mirror RA) dan jadikan `kelas_santri` view/turunan. Rekomendasi: **cukup lengkapi `kelas_santri`** (lebih sederhana, satu sumber).

### 3.2 Operasi pindah kelas (1 santri)
```
pindahKelas(santri, kelasBaru, tanggal):
  - baris aktif skrg → is_aktif=false, tanggal_keluar=tanggal
  - baris baru       → kelas_id=kelasBaru, tahun_ajaran_id=kelasBaru.tahun_ajaran_id,
                       is_aktif=true, tanggal_masuk=tanggal
  (baris lama TETAP tersimpan = histori)
```

### 3.3 Naik kelas massal (rollover tahun ajaran) + override
```
naikKelasMassal(kelasSumber → kelasTujuan, tanggal, kecuali[]):
  untuk tiap santri aktif di kelasSumber yang TIDAK di kecuali[]:
     pindahKelas(santri, kelasTujuan, tanggal)
  santri di kecuali[] → ditangani manual (tinggal kelas / pindah lain / lulus)
```
- "Override" = daftar pengecualian + kemampuan koreksi per santri setelah rollover.
- Bisa mundur (undo) selama belum ada data akademik baru di kelas tujuan.

### 3.4 Histori & pemakaian ulang data
- **Tidak menduplikasi** data akademik. Laporan "per kelas + tahun ajaran" diturunkan:
  `catat akademik (tanggal) → cari membership santri yang tanggal_masuk ≤ tanggal ≤ (tanggal_keluar ?? ∞) → dapat kelas & tahun ajaran saat itu`.
- Efek: rapor/rekap tahun-tahun sebelumnya tetap akurat walau santri sudah pindah/naik.
- Santri kembali/aktif lagi → cukup buat membership baru; histori lama tetap bisa dibaca.

---

## 4. Bagian B — Sinkronisasi Santri Smart → RamahAnak (FOKUS UTAMA)

### 4.1 Pembagian kepemilikan data
| Domain | Pemilik | Keterangan |
|---|---|---|
| Identitas santri (nisn, nama, jk, tgl lahir, wali, no_wa) | **Smart** | RA hanya cermin |
| Kelas & tahun ajaran + histori pindah kelas | **Smart** | Dikirim ke RA sebagai update |
| Expert system tracking, bimbingan, laporan pelanggaran/apresiasi/konselor | **RA** | Data khusus aplikasi RA |

### 4.2 Kunci & pemetaan
- **NISN (RA) ⇄ NIP/NIS (Smart)** = kunci utama upsert.
- Kelas: RA punya tabel `kelas` sendiri → pemetaan kelas Smart→RA lewat **nama kelas + tahun ajaran** (atau tabel mapping). Perlu disepakati agar `kelas_id` RA benar.

### 4.3 Mekanisme sinkron (rekomendasi: PUSH upsert dari Smart)
Reuse pola **outbox** yang sudah ada di Smart (WaOutbox / AbsensiRamahAnak):
```
Smart (event: santri dibuat/diubah/pindah kelas)
   → buat outbox jenis 'santri_sync' berisi payload upsert
   → kirim ke RA:  POST /api/santri/sync   (endpoint BARU di RA)
       body: { nisn, nama, jenis_kelamin, tanggal_lahir, nama_wali, no_wa,
               kelas: {nama, tingkat, jenis}, tahun_ajaran, is_aktif,
               riwayat_kelas?: [ {tahun_ajaran, kelas, tanggal_masuk, tanggal_keluar, is_active} ] }
RA:
   - upsert santri_profiles by nisn (buat user+profile bila belum ada)
   - map/insert kelas RA (by nama+tahun_ajaran) → set kelas_id
   - update riwayat_kelas_santri (tutup yang lama, buka yang baru) — sesuai payload
   - JANGAN sentuh data fitur RA (expert tracking, dst.)
```
- **Idempoten** (refId per versi) — aman dikirim ulang.
- **Rekonstruksi awal (backfill)**: satu kali kirim seluruh santri aktif Smart → RA agar dua DB selaras.
- **Reconciliation**: endpoint Smart `GET /api/santri/{nisn}` (atau daftar) agar RA bisa menarik ulang bila perlu.

### 4.4 Alternatif (bila push sulit)
- **PULL dari RA**: RA memanggil Smart `GET /api/santri/{nisn}` saat butuh (mis. saat terima eksekusi untuk nisn yang belum ada) → tarik & simpan cermin. Lebih lambat sinkronnya, tapi minim perubahan Smart.
- **Hybrid** (disarankan jangka panjang): PUSH saat berubah + PULL untuk rekonsiliasi.

### 4.5 Dampak ke Smart Eksekusi (nanti, sesuai catatan)
- Karena santri sudah sinkron by nisn, aliran eksekusi Smart→RA tetap seperti sekarang, tapi RA dijamin punya santri yang benar (kelas terkini) → laporan RA per kelas jadi akurat.
- Penyesuaian RA menyusul (fokus sekarang: DB santri sinkron).

---

## 5. Urutan Kerja yang Disarankan

1. **(Smart)** Lengkapi `kelas_santri`: `tanggal_keluar`, `tahun_ajaran_id`, `keterangan` (migration additive) + service `pindahKelas` & `naikKelasMassal` (+ UI admin: pindah/naik kelas + daftar pengecualian).
2. **(Smart)** Backfill `tahun_ajaran_id`/`tanggal_keluar` untuk data pivot lama (dari `kelas`).
3. **(RA)** Buat endpoint `POST /api/santri/sync` (upsert by nisn, map kelas, update riwayat_kelas_santri) — **tanpa** mengubah data fitur RA.
4. **(Smart)** Tambah outbox `santri_sync` + pengirim (event create/update/pindah kelas) + **backfill sekali** semua santri aktif ke RA.
5. **(Smart)** Endpoint `GET /api/santri` & `/api/santri/{nisn}` untuk rekonsiliasi RA.
6. **(Uji)** Pindah/naik kelas di Smart → cek RA ter-update (santri_profiles.kelas_id + riwayat) & histori Smart tetap utuh.
7. **(Nanti)** Penyesuaian Smart Eksekusi/RA setelah santri tersinkron.

---

## 6. Keputusan yang Perlu Ditetapkan (sebelum implementasi)
- [ ] Lengkapi `kelas_santri` **atau** buat tabel `riwayat_kelas_santri` terpisah di Smart? (rekomendasi: lengkapi pivot)
- [ ] Sinkron **PUSH** (Smart kirim) / **PULL** (RA tarik) / **Hybrid**? (rekomendasi: PUSH + backfill, hybrid nanti)
- [ ] Pemetaan **kelas Smart→RA**: by nama+tahun_ajaran, atau tabel mapping eksplisit?
- [ ] Auth antar-app (token statis/secret header) untuk endpoint sync.
- [ ] Field master mana yang RA boleh tampilkan tapi tak boleh edit (read-only mirror).
