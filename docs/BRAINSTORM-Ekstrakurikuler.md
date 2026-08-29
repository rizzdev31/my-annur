# Brainstorming — Fitur Ekstrakurikuler

**Status:** Analisis & rancangan alur — 2026-08-24. Fitur BARU, terpisah dari penjadwalan mengajar (KBM), terhubung ke gaji via **vakasi** (bukan JP).

---

## 1. Ringkasan

Ekstrakurikuler (ekskul) = kegiatan di luar KBM. Pembina (guru) ditugaskan mengampu satu/lebih ekskul, tiap ekskul punya **kelompok santri** & **jadwal sendiri**. Guru mengisi **absensi per kelompok** dan **jurnal penilaian** (keaktifan & perkembangan, skala **A/B/C**). Superadmin mengatur semua master + aturan; guru hanya mengisi sesuai jadwal & aturan itu. Vakasi pembina mengalir ke payroll (flat per pertemuan, seperti piket).

---

## 2. Data Model (usulan)

| Tabel | Isi | Catatan |
|---|---|---|
| `ekstrakurikuler` | id, nama, deskripsi, pembina_id (tenaga_pendidik), hari, jam_mulai, jam_selesai, lokasi, tahun_ajaran_id, kuota?, nominal_vakasi (override), is_aktif | Master ekskul + jadwalnya (di luar jadwal_mengajar) |
| `ekstrakurikuler_santri` | id, ekstrakurikuler_id, santri_id, tanggal_masuk, is_aktif | Kelompok/anggota (m-n santri↔ekskul, independen kelas) |
| `ekstrakurikuler_pertemuan` | id, ekstrakurikuler_id, tanggal, jam_mulai_aktual, materi, status(berlangsung/selesai), diisi_oleh (pembina), vakasi_diberikan | 1 pertemuan = 1 sesi absensi (sumber vakasi) |
| `ekstrakurikuler_absensi` | id, pertemuan_id, santri_id, status(hadir/izin/sakit/alpha) | Absensi per santri per pertemuan |
| `ekstrakurikuler_penilaian` | id, ekstrakurikuler_id, santri_id, periode(semester/bulan), keaktifan(A/B/C), perkembangan(A/B/C), catatan, dinilai_oleh, tanggal | Jurnal penilaian (rekap per periode) |

- **Vakasi**: `SettingVakasi.tipe_aktivitas = 'ekstrakurikuler'` (nominal global) + opsi `nominal_vakasi` override per ekskul. Flat **per pertemuan** yang absensinya diisi pembina.
- **Aturan** (diatur superadmin, memengaruhi akses guru): hari/jam pertemuan, toleransi pengisian (mis. absensi hanya boleh diisi pada hari-H / dalam N hari), wajib materi, kuota anggota.

> Nilai A/B/C: **A = Sangat Baik, B = Baik, C = Cukup** (hanya keaktifan & perkembangan; tanpa angka).

---

## 3. Alur

### 3.1 Superadmin (master + aturan)
1. **Buat Ekskul**: nama, pembina, jadwal (hari/jam/lokasi), tahun ajaran, nominal vakasi (atau pakai default SettingVakasi).
2. **Kelola Kelompok**: tambah/keluarkan santri anggota (cari santri, centang) — tabel anggota rapi.
3. **Aturan**: toleransi pengisian absensi, wajib materi, periode penilaian (semester/bulan).
4. **Monitoring**: tabel rekap kehadiran & penilaian per ekskul/santri; ekspor.

### 3.2 Guru/Pembina (PWA)
1. **Ekskul Saya**: daftar ekskul yang diampu + jadwalnya.
2. **Absensi (per kelompok)**: buka ekskul → "Mulai Pertemuan" (tanggal, materi) → daftar anggota → tandai hadir/izin/sakit/alpha → simpan (kunci per pertemuan). → memicu **vakasi** pembina.
3. **Jurnal Penilaian**: per santri, pilih **keaktifan (A/B/C)** & **perkembangan (A/B/C)** + catatan, per periode (mis. tiap bulan/semester). Bisa diperbarui dalam periode berjalan.
4. **Akses dibatasi aturan**: hanya ekskul yang diampu; absensi hanya pada jadwal/toleransi yang diatur superadmin.

### 3.3 Gaji (payroll)
- Tambah `PayrollCalculationService::hitungVakasiEkstrakurikuler($guru, periode)` = jumlah pertemuan (yang absensinya diisi & diselesaikan pembina dalam periode) × nominal vakasi ekskul. Masuk breakdown slip `vakasi_ekstrakurikuler` (pola sama seperti `vakasi_piket`).

---

## 4. Desain UI (disinkronkan Admin ↔ PWA)

### Superadmin (Inertia/Vue, gaya admin existing — indigo)
- **Halaman Ekstrakurikuler**: header + tombol Tambah; **tabel** (Nama, Pembina, Jadwal, Anggota, Vakasi, Status) + aksi (Edit, Kelola Anggota, Monitoring, Nonaktif).
- **Modal Tambah/Edit**: form rapi (nama, pembina dropdown, hari, jam mulai–selesai, lokasi, tahun ajaran, nominal vakasi, aturan).
- **Modal Kelola Anggota**: cari santri + tabel anggota (centang keluar/masuk).
- **Monitoring**: tabel rekap kehadiran (%) + penilaian A/B/C per santri.

### PWA Guru (Vue, biru #0C78FF, floating nav — konsisten app guru)
- **Menu "Ekstrakurikuler"** (drawer): daftar ekskul diampu (kartu: nama, jadwal, jumlah anggota).
- **Detail ekskul**: tab **Absensi** (mulai pertemuan → toggle status per santri, sekali-kunci) & **Penilaian** (kartu santri → segmented A/B/C keaktifan + perkembangan + catatan).
- Toast + BottomSheet (konsisten pola PWA guru). Responsif, badge warna A(hijau)/B(biru)/C(amber).

---

## 5. Integrasi & Prinsip
- **Terpisah dari KBM**: tabel & jadwal sendiri; tidak menyentuh `jadwal_mengajar`/JP.
- **Vakasi ≠ JP**: flat per pertemuan via SettingVakasi/override, masuk payroll seperti piket.
- **Superadmin = pengatur**, guru = pelaksana sesuai aturan.
- **Histori aman**: absensi & penilaian per tahun ajaran/periode tersimpan.

---

## 6. Keputusan yang perlu ditetapkan (sebelum build)
1. **Vakasi dihitung per?** (a) per **pertemuan** (flat) ✅rekomendasi, (b) per **bulan** flat, (c) per santri hadir.
2. **Periode penilaian?** (a) per **semester** ✅rekomendasi, (b) per **bulan**, (c) per **pertemuan**.
3. **Anggota ekskul**: bebas lintas kelas (m-n santri↔ekskul) ✅rekomendasi — konfirmasi.
4. **Status absensi**: hadir/izin/sakit/alpha ✅ atau cukup hadir/alpha?
