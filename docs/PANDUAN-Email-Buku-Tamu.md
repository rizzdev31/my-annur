# Panduan Mengaktifkan Email Buku Tamu (Hostinger Business Email)

Dokumen ini untuk **operator server**. Kata sandi email tidak pernah dipegang
atau diminta oleh siapa pun selain Anda — isi sendiri langsung di server.

## 1. Mengapa harus diaktifkan

Tanpa pengaturan `MAIL_*` di `.env` produksi, Laravel memakai pengirim `log`:
`Mail::send()` **selalu berhasil**, tidak ada pesan kesalahan, tetapi tidak ada
satu pun email yang benar-benar keluar. Karena itu tombol kirim di halaman
Buku Tamu **dikunci** selama pengaturan ini belum ada, dan layar menampilkan
peringatan kuning. Itu memang disengaja — lebih baik tombolnya mati daripada
50 tamu ditandai "terkirim" padahal tidak menerima apa pun.

## 2. Yang perlu ditambahkan di `.env` VPS

```
MAIL_MAILER=smtp
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=465
MAIL_SCHEME=smtps
MAIL_USERNAME=info@myannur.id
MAIL_PASSWORD=<kata sandi email info@myannur.id>
MAIL_FROM_ADDRESS=info@myannur.id
MAIL_FROM_NAME="Pondok Pesantren An-Nur"
```

Catatan penting:

- `MAIL_FROM_ADDRESS` **wajib sama** dengan `MAIL_USERNAME`. Hostinger menolak
  kiriman yang mengaku berasal dari alamat lain.
- Port 465 memakai `MAIL_SCHEME=smtps` (SSL). Bila jaringan VPS memblokir 465,
  pakai `MAIL_PORT=587` dengan `MAIL_SCHEME=tls`.
- Jangan membungkus kata sandi dengan tanda kutip kecuali memang ada spasi di
  dalamnya.

## 3. Memuat ulang kontainer (bukan restart)

Variabel `.env` dibaca saat kontainer dibuat. **Restart tidak cukup** —
ini jebakan yang sudah pernah terjadi saat memasang VAPID push:

```bash
cd /opt/annur-smart-system
docker compose up -d --force-recreate app
docker compose exec -T app php artisan config:clear
```

Pekerja antrean (`queue:work` di supervisor) ikut memakai kontainer `app`, jadi
sekali recreate sudah mencakup pengirim email.

## 4. SPF / DKIM / DMARC

Tanpa ini, email notulensi besar kemungkinan masuk folder Spam Gmail. Periksa di
panel Hostinger → Email → `myannur.id` → DNS/Records, pastikan ketiganya ada:

| Jenis | Nama | Isi (contoh Hostinger) |
|-------|------|------------------------|
| TXT (SPF)   | `@`            | `v=spf1 include:_spf.mail.hostinger.com ~all` |
| TXT (DKIM)  | `hostingermail._domainkey` | diberikan otomatis oleh Hostinger |
| TXT (DMARC) | `_dmarc`       | `v=DMARC1; p=none; rua=mailto:info@myannur.id` |

Nilai persisnya ikuti yang ditampilkan panel Hostinger, jangan disalin dari
dokumen ini bila berbeda.

## 5. Uji sebelum menyasar tamu

Di halaman **Buku Tamu → (pilih kegiatan)**:

1. Tulis notulensi, tekan **Simpan Naskah**.
2. Isi kolom **Uji kirim** dengan email pribadi Anda, tekan **Kirim Uji**.
   Pengiriman uji dilakukan **langsung tanpa antrean**, jadi kesalahan
   pengaturan (kata sandi salah, port diblokir) langsung terlihat di layar.
3. Periksa kotak masuk **dan folder Spam**.
4. Baru setelah itu tekan **Kirim ke N Alamat Tamu**.

## 6. Perilaku pengiriman yang perlu diketahui

- **Isi email berupa HTML, bukan lampiran PDF.** Pilihan ini menghemat: tidak
  ada PDF yang dibuat per penerima, data keluar jauh lebih kecil (50 tamu ×
  ratusan KB bisa belasan MB sekali kirim), skor spam lebih rendah, dan tamu
  langsung membacanya di ponsel. Yang ingin mengarsipkan membuka tautan
  **versi web** di akhir email lalu mencetaknya sendiri menjadi PDF.
- **Dikirim bertahap ±6 detik per email** (`BukuTamuService::JEDA_KIRIM_DETIK`)
  karena Hostinger membatasi jumlah kiriman per jam, dan kiriman yang ditolak
  tetap memakan kuota. 50 tamu tuntas dalam ±5 menit.
- **Satu alamat hanya menerima sekali** walau orangnya mengisi dua kali di acara
  yang sama. Baris keduanya ditandai **Alamat ganda**, bukan "terkirim".
- **Email konfirmasi** dikirim otomatis begitu tamu selesai mengisi. Fungsinya
  bukan sekadar sopan: itu yang membuktikan alamatnya hidup. Bila konfirmasi
  gagal, kolom email tamu itu diberi catatan **"Konfirmasi gagal — alamat
  mungkin keliru"**, jadi bisa dibetulkan sebelum notulensi dikirim.
- **Tautan versi web 404 selama notulensi belum dikirim.** Tautan kegiatan
  beredar di grup WA peserta, jadi naskah yang masih digarap tidak ikut terbaca.
- Naskah notulensi **selalu di-escape** sebelum menjadi HTML, termasuk yang
  ditulis superadmin — isinya terbang ke puluhan alamat luar, jadi tidak ada
  jalan menyisipkan HTML/skrip walau lewat salah tempel.
