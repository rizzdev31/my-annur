# Deploy An-Nur Smart System + RamahAnak di VPS (Docker)

Dua aplikasi = dua **stack Docker mandiri** (masing-masing punya container app + MySQL sendiri),
di depannya **satu Caddy** sebagai reverse-proxy + HTTPS otomatis. Build dilakukan di VPS.

```
Internet 443 ─► Caddy ─┬─► smart_app  ─► smart_mysql   (DB: systemdb)
                       └─► ra_app     ─► ra_mysql      (DB: db_ra)
Smart ─► RA  lewat jaringan 'edge':  RAMAHANAK_API_URL=http://ra_app/api/v1
```

---

## 1. Prasyarat VPS (Ubuntu)

```bash
# Install Docker Engine + plugin compose
curl -fsSL https://get.docker.com | sh
sudo usermod -aG docker $USER      # logout/login setelah ini

# Verifikasi
docker --version && docker compose version
```

Arahkan **DNS A-record** kedua domain ke IP VPS:
- `smart.ppmannursidoarjo.com  → <IP-VPS>`
- `ramahanak.ppmannursidoarjo.com → <IP-VPS>`

Buka firewall port 80 & 443 (`ufw allow 80,443/tcp`).

---

## 2. Jaringan bersama (sekali saja)

```bash
docker network create edge
```

---

## 3. Ambil kode + konfigurasi

```bash
sudo mkdir -p /opt && cd /opt
git clone <repo-smart-url> annur-smart-system
git clone <repo-ra-url>    ramahanak

# Smart
cd /opt/annur-smart-system
cp .env.docker .env
nano .env          # isi APP_URL, DB_PASSWORD, DB_ROOT_PASSWORD, RAMAHANAK_API_TOKEN

# RamahAnak
cd /opt/ramahanak
cp .env.docker .env
nano .env          # isi APP_URL, DB_PASSWORD, DB_ROOT_PASSWORD, INTEGRASI_API_TOKEN
```

> **PENTING:** `RAMAHANAK_API_TOKEN` (Smart) **harus sama persis** dengan `INTEGRASI_API_TOKEN` (RA).
> `APP_KEY` boleh dikosongkan — otomatis digenerate saat container pertama start.

---

## 4. Jalankan

```bash
# 1) Reverse-proxy (sekali)
cd /opt/annur-smart-system/deploy/reverse-proxy
nano Caddyfile        # sesuaikan domain
docker compose up -d

# 2) RamahAnak dulu (agar 'ra_app' siap saat Smart sync)
cd /opt/ramahanak && docker compose up -d --build

# 3) Smart
cd /opt/annur-smart-system && docker compose up -d --build
```

Cek: `docker compose ps` dan `docker compose logs -f app`.
Migrasi berjalan otomatis via entrypoint (`RUN_MIGRATIONS=true`).

---

## 5. Isi data awal (sekali)

**Opsi A — mulai bersih (seeder):**
```bash
cd /opt/annur-smart-system
docker compose exec app php artisan db:seed --class=FreshMinimalSeeder --force
docker compose exec app php artisan db:seed --class=GuruSeeder --force
docker compose exec app php artisan db:seed --class=SantriSeeder --force
# lalu sinkron ke RA:
docker compose exec app php artisan ramahanak:sync-guru
docker compose exec app php artisan ramahanak:sync-santri
# queue worker sudah jalan di dalam container → outbox terkirim otomatis

cd /opt/ramahanak
docker compose exec app php artisan db:seed --class=GuruBkSeeder --force
```

**Opsi B — impor backup SQL yang sudah ada:**
```bash
docker compose exec -T mysql mysql -uroot -p"$DB_ROOT_PASSWORD" systemdb < backup_smart.sql
```

---

## 6. Update / CI-CD (yang Anda minta)

**Manual di VPS:**
```bash
cd /opt/annur-smart-system && bash deploy.sh
cd /opt/ramahanak           && bash deploy.sh
```
`deploy.sh` = `git pull` → `docker compose up -d --build` → `migrate --force` → refresh cache.

**Otomatis via GitHub Actions** (`.github/workflows/deploy.yml`): tiap push ke `main`
akan SSH ke VPS dan menjalankan `deploy.sh`. Set **Secrets** di tiap repo GitHub:
`VPS_HOST`, `VPS_USER`, `VPS_PORT`, `VPS_SSH_KEY` (private key). Reverse-proxy tak perlu disentuh saat update.

---

## 7. Backup database (disarankan cron)

```bash
# contoh: backup harian Smart + RA jam 02:00
crontab -e
0 2 * * * docker exec smart_mysql sh -c 'mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" systemdb' > /opt/backups/smart_$(date +\%F).sql
0 2 * * * docker exec ra_mysql    sh -c 'mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" db_ra'   > /opt/backups/ra_$(date +\%F).sql
```

---

## 8. Perintah harian

```bash
docker compose logs -f app             # lihat log app (nginx+fpm+queue+scheduler)
docker compose exec app php artisan tinker
docker compose exec app php artisan queue:work --once   # tes worker manual
docker compose restart app             # reload (mis. setelah ubah .env)
docker compose down                    # stop (data DB & storage aman di volume)
```

## Catatan
- **Data persist** di named volume `db_data` (MySQL) & `storage_data` (upload/log). `docker compose down` tidak menghapusnya; hindari `down -v`.
- Log aplikasi keluar ke `docker logs` (LOG_CHANNEL=stderr).
- Queue worker & scheduler sudah otomatis jalan di dalam container (supervisor) — tak perlu container terpisah.
- Ganti driver ke Redis (opsional) untuk performa: tambah service `redis`, set `CACHE_STORE=redis`, `SESSION_DRIVER=redis`, `QUEUE_CONNECTION=redis`.
