# PRD — Sistem Notifikasi Terkonfigurasi (An-Nur Smart System)

**Status:** Draft • **Tanggal:** 2026-08-25 • **Pemilik:** Superadmin/Yayasan
**Tujuan dokumen:** dasar untuk brainstorm & implementasi bertahap.

---

## 1. Ringkasan

Menjadikan notifikasi **dapat dikonfigurasi per-fitur (override)** oleh admin: hal-hal
**wajib** (absensi, tugas mengajar, tugas jabatan, izin, penggajian, dll) bisa
diaktifkan/dinonaktifkan, diarahkan ke penerima yang tepat, lewat kanal yang tepat
(in-app / WhatsApp / push), dengan pengingat & eskalasi bila belum ditindak.

## 2. Kondisi saat ini

- **Satu gerbang**: `NotifikasiService::kirim()` → tabel `notifikasi` (in-app saja).
- **Konsumen**: lonceng admin (Inertia) + lonceng PWA guru (Vue).
- **Trigger yang sudah ada**: reminder absensi (masuk/pulang), izin→admin,
  kegiatan/tugas selesai→admin, tasmi/tasnif→penguji, tahfidz/tahsin. **Masih sedikit & hardcoded.**
- **Sudah diperbaiki**: kolom `notifikasi.tipe` diubah ENUM→VARCHAR agar jenis baru bebas.
- **Kanal lain tersedia tapi belum terhubung ke notif**: WhatsApp (`WaService`),
  push (`users.fcm_token`).
- **Kekurangan**: tidak ada katalog event, tidak ada pengaturan on/off per event,
  tidak ada pilihan kanal/penerima, tidak ada eskalasi, tidak ada preferensi per user.

## 3. Tujuan & Non-Goals

**Tujuan**
1. **Katalog event** notifikasi seluruh fitur (satu sumber kebenaran).
2. **Override per event**: aktif/nonaktif, penerima, kanal, waktu (real-time/reminder), eskalasi.
3. Pastikan **hal wajib** selalu terkirim & bisa mengingatkan berulang sampai ditindak.
4. Multi-kanal: **in-app** (utama), **WhatsApp**, **push** (opsional per event).
5. UI admin sederhana untuk mengatur semuanya.

**Non-Goals (fase awal)**
- Editor template pesan yang rumit (cukup template default + variabel).
- Kanal email.
- Analitik notifikasi mendalam.

## 4. Konsep Inti

### 4.1 Katalog Event (registry)
Setiap kejadian yang bisa memicu notifikasi didaftarkan dengan **kode unik**,
kategori, penerima default, wajib/opsional, dan kanal default. Contoh:

| Kode event | Kategori | Pemicu | Penerima default | Wajib |
|---|---|---|---|---|
| `absensi.reminder_masuk` | Absensi | Menjelang jam masuk, belum check-in | Guru ybs | ✅ |
| `absensi.reminder_pulang` | Absensi | Lewat jam pulang, belum check-out | Guru ybs | ✅ |
| `absensi.alfa` | Absensi | Auto-alfa terpicu | Guru + admin | ✅ |
| `mengajar.reminder` | Mengajar | Menjelang jadwal, jurnal belum dibuka | Guru ybs | ✅ |
| `mengajar.tidak_terlaksana` | Mengajar | Sesi tidak terlaksana | Guru + admin | ✅ |
| `pengganti.ditunjuk` | Mengajar | Guru ditunjuk jadi pengganti | Guru pengganti | ✅ |
| `izin.diajukan` | Pengajuan | Guru mengajukan izin | Admin/atasan | ✅ |
| `izin.diputuskan` | Pengajuan | Izin disetujui/ditolak | Guru ybs | ✅ |
| `tugas.baru` / `tugas.jatuh_tempo` | Tugas | Tugas ditugaskan / mendekati deadline | Guru ybs | ✅ |
| `tahfidz.jadwal` / `tahsin.jadwal` | Smart Edu | Ditunjuk penguji/tasmi | Guru penguji | ⬜ |
| `penggajian.terbit` | Penggajian | Slip gaji periode terbit | Guru ybs | ⬜ |
| `kinerja.rendah` | Kinerja | Skor di bawah ambang | Guru + pimpinan | ⬜ |
| `pengumuman.umum` | Umum | Broadcast manual | Sesuai target | ⬜ |

> Daftar final disepakati saat brainstorm; katalog mudah ditambah.

### 4.2 Dimensi konfigurasi per event (override)
Untuk tiap event, admin bisa atur:
- **Aktif/Nonaktif**.
- **Penerima**: peran (guru ybs, admin, pimpinan, penguji) dan/atau individu.
- **Kanal**: in-app ✅ (default), WhatsApp, push — bisa lebih dari satu.
- **Waktu**: real-time (saat kejadian) dan/atau **reminder** (mis. 15 menit sebelum,
  ulang tiap X menit) — untuk event berbasis jadwal.
- **Eskalasi (opsional)**: bila belum ditindak dalam N menit/jam → naikkan ke atasan/admin.
- **Kuota anti-spam**: maksimal berapa kali per hari/kejadian.

### 4.3 Wajib vs opsional
Event **wajib** boleh dinonaktifkan hanya oleh superadmin (default selalu ON),
untuk mencegah hal krusial (absen/mengajar) terlewat.

## 5. Model Data (usulan)

- **`notifikasi_event`** (katalog, seed-based): `kode`, `nama`, `kategori`,
  `deskripsi`, `wajib`(bool), `penerima_default`(json), `kanal_default`(json),
  `mendukung_reminder`(bool).
- **`setting_notifikasi`** (override per event): `event_kode`, `aktif`(bool),
  `penerima`(json: roles/ids), `kanal`(json: in_app/wa/push), `reminder`(json:
  {sebelum_menit, ulang_menit, batas}), `eskalasi`(json: {setelah_menit, ke}),
  `maks_per_hari`(int), `dibuat_oleh`.
- **`notifikasi`** (sudah ada): tambah kolom opsional `event_kode`, `kanal_terkirim`(json),
  `prioritas`(enum rendah/normal/tinggi). `tipe` sudah VARCHAR.
- **(Opsional) `preferensi_notifikasi_user`**: user mematikan kanal tertentu untuk event
  non-wajib (mis. matikan WA untuk pengumuman).

## 6. Arsitektur

`NotifikasiService::event($kode, $konteks, $penerimaOverride?)` menjadi **gerbang tunggal**:
1. Ambil konfigurasi event (registry + override).
2. Jika nonaktif → berhenti (kecuali wajib).
3. Resolusi penerima (peran→user, +individu, −preferensi user).
4. Cek kuota anti-spam & dedup.
5. Render pesan dari template + variabel konteks.
6. Dispatch ke tiap kanal aktif:
   - **in_app** → tulis tabel `notifikasi` (seperti sekarang).
   - **wa** → `WaService` (antre/queue).
   - **push** → FCM via `fcm_token`.
7. Catat `kanal_terkirim`.

Trigger event dipanggil dari titik domain (controller/service/observer/command).
Reminder & eskalasi dijalankan **scheduler** (`absensi:reminder` sudah jadi pola;
digeneralisasi jadi `notifikasi:reminder`).

## 7. Kanal

| Kanal | Status | Catatan |
|---|---|---|
| In-app | ✅ ada | Lonceng admin + PWA guru. Utama. |
| WhatsApp | ⚙️ infra ada (`WaService`) | Untuk hal penting/di luar app; hemat kuota → hanya event tertentu. |
| Push (FCM) | ⚙️ `fcm_token` ada | Untuk PWA/mobile; butuh registrasi token & server key. |

## 8. UI Admin (Setting Notifikasi)

- Halaman **matrix**: baris = event (dikelompokkan per kategori), kolom = In-app / WA / Push + toggle Aktif.
- Klik event → panel detail: penerima, reminder (sebelum/ulang/batas), eskalasi, kuota.
- Badge **"Wajib"** untuk event krusial (tidak bisa dimatikan non-superadmin).
- Aksi cepat: "Aktifkan semua In-app", "Reset ke default".

## 9. Fase Rollout

- **Fase 0 (selesai/sedang)**: gerbang in-app, reminder absensi, fix tipe VARCHAR.
- **Fase 1 (MVP)**: registry event + `setting_notifikasi` + `NotifikasiService::event()` +
  UI matrix (in-app only) + generalisasi reminder → `notifikasi:reminder` untuk event wajib
  (absensi + mengajar). Semua event wajib tersambung ke gerbang.
- **Fase 2**: kanal WhatsApp untuk event terpilih + eskalasi + kuota anti-spam.
- **Fase 3**: push FCM + preferensi per user + template editor ringan.

## 10. Metrik Sukses
- % hal wajib yang menghasilkan notifikasi (target 100%).
- Penurunan "lupa absen"/"sesi tidak terlaksana tanpa tindak lanjut".
- Waktu rata-rata tindak lanjut izin/pengganti setelah notifikasi.
- Rasio notifikasi dibaca vs terkirim (indikasi relevansi, hindari spam).

## 11. Risiko & Mitigasi
- **Kelelahan notifikasi (spam)** → kuota per hari, dedup, prioritas, opsi kanal per event.
- **Kuota WA/biaya** → WA hanya untuk event penting; sisanya in-app/push.
- **Event wajib dimatikan tak sengaja** → kunci untuk superadmin + default ON.
- **Scheduler mati** → notifikasi wajib tak jalan; pantau cron/health-check.

## 12. Pertanyaan untuk Brainstorm
1. Event mana yang **wajib WA** (bukan cukup in-app)? (biaya vs urgensi)
2. Reminder mengajar: pemicunya jurnal belum dibuka, atau absen mengajar belum diisi?
3. Eskalasi: perlu di fase awal atau nanti?
4. Preferensi per user (guru bisa matikan sebagian) — boleh, atau admin penuh yang menentukan?
5. Push FCM: apakah PWA guru sudah siap register token, atau tunda ke fase akhir?
