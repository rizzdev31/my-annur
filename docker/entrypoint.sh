#!/usr/bin/env bash
set -e
cd /var/www/html

echo "[entrypoint] menyiapkan aplikasi..."

# 1) Pastikan struktur storage ada (named volume bisa kosong saat pertama kali)
mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views \
         storage/logs storage/app/public bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache || true

# 2) Tunggu database siap
if [ -n "${DB_HOST:-}" ]; then
  echo "[entrypoint] menunggu database ${DB_HOST}:${DB_PORT:-3306}..."
  for i in $(seq 1 30); do
    if php -r '$h=getenv("DB_HOST");$p=(int)(getenv("DB_PORT")?:3306);exit(@fsockopen($h,$p)?0:1);'; then
      echo "[entrypoint] database siap."; break
    fi
    sleep 2
  done
fi

# 3) APP_KEY — generate HANYA bila belum di-set (jangan timpa yang sudah ada)
if [ -z "${APP_KEY:-}" ]; then
  echo "[entrypoint] APP_KEY kosong → generate baru"
  php artisan key:generate --force --no-interaction || true
fi

# 4) Migrasi (matikan dengan RUN_MIGRATIONS=false bila ingin manual)
if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
  echo "[entrypoint] menjalankan migrate..."
  php artisan migrate --force --no-interaction || echo "[entrypoint] migrate gagal (lanjut)"
fi

# 5) Storage link + cache konfigurasi (produksi)
php artisan storage:link --no-interaction 2>/dev/null || true
php artisan package:discover --no-interaction || true
php artisan config:cache
php artisan route:cache || true
php artisan view:cache || true

echo "[entrypoint] siap. menjalankan: $*"
exec "$@"
