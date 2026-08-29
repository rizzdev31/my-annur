# 🧪 Panduan Testing — An-Nur Smart System

> Panduan uji **berurutan & konsisten**. Ikuti dari atas ke bawah. Tandai tiap langkah
> `☑ OK` atau `☒ Bug`, isi **Catatan** bila ada masalah, lalu laporkan pakai
> **Template Bug** di bagian akhir. **Jangan lompat fase** — mulai dari Fase 0.

**Legenda status:** `☐` belum diuji · `☑` OK · `☒` Bug (isi catatan)

---

## FASE 0 · Persiapan "Lampu Hijau" (WAJIB dulu)

> 80% "bug palsu" muncul karena salah satu proses ini mati. Pastikan semua ON.

- [ ] **XAMPP MySQL** — Start
- [ ] **Web** jalan — `php artisan serve` + aset (`npm run dev` atau sudah `npm run build`)
- [ ] **Queue worker** — jalankan `start-queue.bat`  *(mati → WA & RamahAnak nyangkut "pending")*
- [ ] **Scheduler** — jalankan `start-scheduler.bat`  *(mati → auto-alfa harian & controlling tak jalan)*
- [ ] **Flutter** menunjuk API benar — emulator `http://10.0.2.2:8000/api/v1`, HP fisik → IP LAN
- [ ] **Fonnte** device tersambung + `FONNTE_ENABLED=true` (kalau menguji WhatsApp)

**Pantau error real-time** (buka jendela PowerShell terpisah, biarkan terbuka):
```
Get-Content storage/logs/laravel.log -Wait -Tail 30
```

- [ ] Log terpantau · Console browser (F12) terbuka saat uji web

---

## FASE 1 · Smoke Test (cepat, ±5 menit)

Tujuan: temukan halaman rusak sebelum uji dalam.

**T-01 · Login web + splash**
Langkah: buka `/login` → isi kredensial → **Masuk**.
Harusnya: muncul **splash "Menyiapkan dashboard…"** lalu masuk Dashboard.
`☐ OK  ☐ Bug` — Catatan: __________

**T-02 · Semua menu sidebar kebuka**
Langkah: klik satu per satu menu sidebar (semua grup).
Harusnya: tiap halaman terbuka, tanpa blank / error merah di Console.
`☐ OK  ☐ Bug` — Catatan: __________

**T-03 · Smoke Flutter**
Langkah: login app guru → buka tiap menu drawer.
Harusnya: tak ada crash / layar merah.
`☐ OK  ☐ Bug` — Catatan: __________

---

## FASE 2 · WEB ADMIN — uji ikut ALIRAN DATA

### 2A · Login, Dashboard & Topbar

**T-10 · Dashboard tampil**
Harusnya: KPI, grafik kehadiran, timeline kegiatan tampil; angka masuk akal.
`☐ OK  ☐ Bug` — Catatan: __________

**T-11 · Lonceng notifikasi**
Langkah: klik ikon lonceng di topbar.
Harusnya: dropdown muncul (daftar notifikasi / "Belum ada notifikasi"); klik notifikasi → tertandai dibaca.
`☐ OK  ☐ Bug` — Catatan: __________

**T-12 · Shortcut ⌘K / Ctrl+K**
Harusnya: sidebar terbuka & fokus ke kotak "Cari menu…"; ketik nama menu → hasil muncul.
`☐ OK  ☐ Bug` — Catatan: __________

**T-13 · Logo & responsif**
Harusnya: logo An-Nur tampil di sidebar; kecilkan lebar browser → layout rapi (tak berantakan).
`☐ OK  ☐ Bug` — Catatan: __________

### 2B · Master Data  *(uji CRUD + konfirmasi + toast)*

**T-20 · Tenaga Pendidik — Tambah**
Langkah: Master → Tenaga Pendidik → Tambah → isi → Simpan.
Harusnya: data tersimpan + **toast hijau** "berhasil" (pojok kanan-atas).
`☐ OK  ☐ Bug` — Catatan: __________

**T-21 · Tenaga Pendidik — Hapus (dialog konfirmasi)**
Langkah: klik ikon Hapus pada 1 guru.
Harusnya: muncul **dialog konfirmasi brand** (bukan popup browser) berisi nama + peringatan "gunakan Resign". Klik **Batal** → tak ada yang terhapus. Ulangi → **Ya, Hapus** → terhapus + toast.
`☐ OK  ☐ Bug` — Catatan: __________

**T-22 · Jabatan — Hapus terpakai**
Langkah: hapus jabatan yang masih dipakai guru.
Harusnya: dialog **"Tidak bisa dihapus"** (diblok). Hapus jabatan kosong → konfirmasi → terhapus.
`☐ OK  ☐ Bug` — Catatan: __________

**T-23 · Jadwal Mengajar / Mata Pelajaran / Tahun Ajaran**
Langkah: tambah + hapus masing-masing 1 data.
Harusnya: CRUD jalan; hapus selalu lewat dialog konfirmasi + toast.
`☐ OK  ☐ Bug` — Catatan: __________

### 2C · Smart Education

**T-30 · Santri & Kelas**
Langkah: tambah santri & kelas, kaitkan santri ke kelas.
Harusnya: tersimpan; santri muncul di kelas.
`☐ OK  ☐ Bug` — Catatan: __________

**T-31 · Jurnal Mengajar (monitoring)**
Harusnya: jurnal absen santri yang diinput guru (Flutter) muncul di sini.
`☐ OK  ☐ Bug` — Catatan: __________

**T-32 · Monitoring & Laporan Tahfidz/Tahsin**
Harusnya: progres per santri (grid juz / level), laporan printable tampil benar.
`☐ OK  ☐ Bug` — Catatan: __________

### 2D · Smart Payroll (aktivitas → kinerja → gaji)

**T-40 · Rekap Absensi**
Harusnya: check-in guru dari Flutter muncul; guru tak hadir (lewat jam) muncul **alfa** *(butuh scheduler)*.
`☐ OK  ☐ Bug` — Catatan: __________

**T-41 · Koreksi Absensi**
Langkah: koreksi 1 status absensi.
Harusnya: tersimpan + toast; rekap ikut berubah.
`☐ OK  ☐ Bug` — Catatan: __________

**T-42 · Pengajuan Izin**
Langkah: setujui / tolak 1 pengajuan.
Harusnya: status berubah; badge "pending" di sidebar berkurang; guru dapat notifikasi.
`☐ OK  ☐ Bug` — Catatan: __________

**T-43 · Kinerja**
Harusnya: skor 3 komponen (keaktifan/penugasan/administrasi) + piket terhitung; grade tampil.
`☐ OK  ☐ Bug` — Catatan: __________

**T-44 · Penggajian**
Langkah: buka periode → lihat detail gaji 1 guru.
Harusnya: gaji pokok + vakasi (hadir/mengajar/tugas/lembur) − potongan (telat/alfa) benar.
`☐ OK  ☐ Bug` — Catatan: __________

**T-45 · Libur Individu (guru mukim)**
Langkah: generate libur rolling 1 bulan untuk guru mukim.
Harusnya: tanggal libur muncul; guru itu tak dialfa saat liburnya & gaji tak terpotong.
`☐ OK  ☐ Bug` — Catatan: __________

### 2E · Smart Habbit

**T-50 · Setting Controlling**
Langkah: buat periode + kegiatan + jadwal.
Harusnya: tersimpan; hapus periode → dialog "tak bisa dibatalkan".
`☐ OK  ☐ Bug` — Catatan: __________

**T-51 · Scan Kiosk**
Langkah: buka Scan → scan/ketik NIP santri saat jam kegiatan.
Harusnya: tampil **HADIR** (atau **TELAT** bila lewat jam) besar; masuk ke rekap.
`☐ OK  ☐ Bug` — Catatan: __________

**T-52 · Rekap & Detail Controlling**
Harusnya: kolom Hadir/Telat/Alfa/**Izin** benar; santri berizin tampil **Izin** (bukan alfa).
`☐ OK  ☐ Bug` — Catatan: __________

**T-53 · Monitor Outbox / WhatsApp**
Harusnya: pesan tak stuck "pending" (kalau stuck → worker mati). Coba **retry** 1 pesan gagal.
`☐ OK  ☐ Bug` — Catatan: __________

### 2F · Kesiswaan

**T-60 · Perizinan Santri**
Langkah: tunjuk 1 guru sebagai petugas perizinan; lihat monitor izin.
Harusnya: tersimpan; daftar izin tampil per status.
`☐ OK  ☐ Bug` — Catatan: __________

**T-61 · Smart Health (admin)**
Langkah: tunjuk petugas kesehatan; lihat monitor kasus.
Harusnya: kasus dari guru pelapor tampil dengan status & hari pemantauan.
`☐ OK  ☐ Bug` — Catatan: __________

### 2G · Sarana & Pengaturan

**T-70 · Inventaris**
Langkah: tambah barang; setujui/tolak 1 pengajuan pinjam.
Harusnya: stok/kapasitas benar; aksi lewat konfirmasi + toast.
`☐ OK  ☐ Bug` — Catatan: __________

**T-71 · Template WhatsApp + Uji Kirim**
Langkah: edit template → **Uji Kirim** ke 1 nomor.
Harusnya: terkirim; kalau "token invalid" → token bukan token device (ganti).
`☐ OK  ☐ Bug` — Catatan: __________

---

## FASE 3 · FLUTTER — Aplikasi Guru

### 3A · Login & Profil

**T-80 · Login guru**
Harusnya: masuk ke Dashboard; sapaan & foto/nama benar.
`☐ OK  ☐ Bug` — Catatan: __________

**T-81 · Edit Profil**  *(fitur baru)*
Langkah: Profil → Edit Profil → ubah no HP / alamat / tanggal lahir / rekening → Simpan.
Harusnya: tersimpan + snackbar; data langsung berubah (nama/foto ikut di drawer & dashboard).
`☐ OK  ☐ Bug` — Catatan: __________

**T-82 · Ganti Foto & Password**
Langkah: ketuk avatar → pilih foto; lalu Ganti Password.
Harusnya: foto ter-update; ganti password sukses (password lama salah → ditolak).
`☐ OK  ☐ Bug` — Catatan: __________

### 3B · Absensi & Mengajar

**T-83 · Absensi harian (check-in/out)**
Langkah: check-in (izinkan GPS) → nanti check-out.
Harusnya: tercatat; muncul di Rekap web (T-40).
`☐ OK  ☐ Bug` — Catatan: __________

**T-84 · Absen mengajar + jurnal santri**
Langkah: buka jadwal hari ini → Absen Santri → simpan & kunci.
Harusnya: terkunci (tak bisa diedit ulang); wali dapat WA; vakasi masuk.
`☐ OK  ☐ Bug` — Catatan: __________

**T-85 · Kelas Tahfidz / Tahsin**
Langkah: absen sesi (dalam jam) → input setoran/nilai (kapan saja) + catatan wajib.
Harusnya: gerbang murojaah/tasmi jalan; nilai tersimpan; laporan ikut ter-update.
`☐ OK  ☐ Bug` — Catatan: __________

### 3C · Kesiswaan & Lainnya

**T-86 · Smart Health (semua guru)**  *(fitur baru)*
Langkah (guru biasa): Lapor Sakit (pilih santri + keluhan + foto) → lalu **pantau timeline**.
Langkah (petugas): setujui → pengecekan H1/H2/H3 / darurat.
Harusnya: pelapor lihat timeline berjalan; H3/darurat → izin pulang otomatis + WA wali.
`☐ OK  ☐ Bug` — Catatan: __________

**T-87 · Perizinan Santri (petugas)**
Harusnya: ajukan + setujui izin → WA wali; santri jadi "Izin" di controlling.
`☐ OK  ☐ Bug` — Catatan: __________

**T-88 · Pengajuan Izin (guru) / Lembur / Slip Gaji / Kinerja / Inventaris**
Harusnya: masing-masing tampil data benar & aksinya jalan.
`☐ OK  ☐ Bug` — Catatan: __________

---

## FASE 4 · Integrasi & Proses Latar

**T-90 · WhatsApp end-to-end**
Langkah: picu 1 kejadian (mis. scan telat / absen santri).
Harusnya: WA masuk ke wali; di Monitor WhatsApp status **sent**.
`☐ OK  ☐ Bug` — Catatan: __________

**T-91 · Auto-alfa terjadwal**
Langkah: biarkan lewat jam kegiatan/shift (scheduler hidup).
Harusnya: status berubah **alfa** otomatis (harian & controlling).
`☐ OK  ☐ Bug` — Catatan: __________

**T-92 · RamahAnak (bila aktif)**
Harusnya: telat/eksekusi terkirim (status di Monitor Outbox), tidak dobel.
`☐ OK  ☐ Bug` — Catatan: __________

---

## FASE 5 · Uji Tepi (paling sering ketemu bug)

**T-95 · Lintas hari (overnight)** — guru shift 15:00→07:00: alfa baru muncul setelah shift benar-benar berakhir. `☐ OK ☐ Bug` — __________
**T-96 · Double-booking inventaris** — pinjam slot waktu bentrok → **ditolak**. `☐ OK ☐ Bug` — __________
**T-97 · Guru pengganti** — guru izin → tunjuk pengganti (pengganti dapat vakasi, guru asli tidak); izin tanpa pengganti → JP hangus. `☐ OK ☐ Bug` — __________
**T-98 · Santri izin ⇄ controlling** — santri berizin **tidak** dialfa. `☐ OK ☐ Bug` — __________
**T-99 · Batal konfirmasi** — tiap dialog, klik **Batal** = benar-benar tak terjadi apa-apa. `☐ OK ☐ Bug` — __________

---

## 📍 Di mana lihat error (per lapisan)

| Gejala | Lihat di |
|---|---|
| Web: halaman error / aksi gagal | `storage/logs/laravel.log` (baris terakhir) + Console browser (F12) |
| WA / RamahAnak tak terkirim | Jendela `start-queue.bat` + tabel Monitor WhatsApp / Outbox |
| Auto-alfa tak jalan | Jendela `start-scheduler.bat` |
| Flutter: gagal load / API error | Console `flutter run` / logcat (error Dio / HTTP) |

---

## 🐞 Template Laporan Bug (pakai ini biar cepat dibenahi)

```
[BUG-01] Judul singkat
Kode uji : T-XX (bila ada)
Platform : Web admin / Flutter
Lokasi   : menu / halaman + role
Langkah  : 1) ...  2) ...  3) ...
Harusnya : ...
Terjadi  : ...
Error    : (tempel dari laravel.log / console)
Bukti    : (screenshot)
Waktu    : jam kejadian + id data terkait
```

**Tips:** uji **satu alur → catat → lanjut**; pakai data uji bernama **"TEST"**; kumpulkan bug **per-modul** lalu kirim sekaligus. Selalu sebut **layer** (web / Flutter / queue) — itu yang paling mempercepat diagnosis.
