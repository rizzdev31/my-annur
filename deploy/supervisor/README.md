# Supervisor — Worker Antrean An‑Nur (pengiriman ke RamahAnak)

Worker (`php artisan queue:work`) harus **selalu hidup** agar laporan di `outbox_laporan`
terkirim otomatis ke RamahAnak. Supervisor menjaga worker tetap jalan & menghidupkannya
ulang bila mati/crash/server restart.

> Hanya untuk server **Linux** (produksi/VPS). Di Windows/lokal cukup jalankan
> `php artisan queue:work` manual di satu terminal selama dipakai.

## 1. Prasyarat
- Supervisor terpasang:
  ```bash
  sudo apt-get update && sudo apt-get install -y supervisor
  ```
- Pastikan `.env` sudah benar: `QUEUE_CONNECTION=database`, `RAMAHANAK_ENABLED=true`,
  `RAMAHANAK_API_TOKEN=…`, dan tabel `jobs` sudah ada (`php artisan migrate`).

## 2. Pasang konfigurasi
1. Salin `annur-queue-worker.conf` ke:
   ```
   /etc/supervisor/conf.d/annur-queue-worker.conf
   ```
2. **Edit** nilai berikut sesuai server:
   - `command=php /var/www/annur-smart-system/artisan …` → path absolut `artisan` (dan path PHP bila perlu, mis. `/usr/bin/php8.2`).
   - `directory=/var/www/annur-smart-system` → root project.
   - `user=www-data` → user pemilik aplikasi (mis. `www-data`, `forge`, atau akun hosting).
3. Muat & jalankan:
   ```bash
   sudo supervisorctl reread
   sudo supervisorctl update
   sudo supervisorctl start annur-queue-worker:*
   ```

## 3. Cek status & log
```bash
sudo supervisorctl status annur-queue-worker:*
tail -f /var/www/annur-smart-system/storage/logs/queue-worker.log
```

## 4. WAJIB setiap selesai deploy kode baru
Worker memuat kode ke memori saat start. Setelah `git pull`/deploy, beri sinyal worker
agar memuat ulang kode terbaru (graceful restart):
```bash
php artisan queue:restart
```
(atau `sudo supervisorctl restart annur-queue-worker:*`)

## 5. Uji cepat
```bash
# kirim ulang backlog (jika ada) — worker yang akan benar-benar mengirim
php artisan ramahanak:flush
sudo supervisorctl status annur-queue-worker:*
```
Lalu lakukan 1 event nyata (Smart Eksekusi / absensi telat) → cek baris `outbox_laporan`
berubah `pending → sent`, dan muncul di halaman Outbox admin (`/admin/smart-habbit/outbox`).

## Catatan
- `numprocs` di conf = 1 (cukup untuk volume saat ini). Naikkan bila laporan sangat ramai.
- `--max-time=3600` membuat worker auto-restart tiap jam (mencegah kebocoran memori) — Supervisor langsung menghidupkannya kembali, tanpa kehilangan job.
