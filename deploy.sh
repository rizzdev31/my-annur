#!/usr/bin/env bash
# Deploy An-Nur Smart System di VPS (build di server).
# Pakai: bash deploy.sh   (dijalankan dari dalam folder repo di VPS)
set -euo pipefail
cd "$(dirname "$0")"

echo "==> [1/5] git pull"
git pull --ff-only

# APP_KEY wajib terisi nilai asli (compose env_file tak menjalankan
# key:generate otomatis). Isi sekali bila belum ada.
if [ -f .env ] && ! grep -qE '^APP_KEY=base64:' .env; then
  echo "    generate APP_KEY baru"
  sed -i "s|^APP_KEY=.*|APP_KEY=base64:$(openssl rand -base64 32)|" .env
fi

echo "==> [2/5] build & up container"
docker compose up -d --build

echo "==> [3/5] backup database (sebelum migrate, simpan 7 terakhir)"
BACKUP_DIR="./backups"; mkdir -p "$BACKUP_DIR"
DB_NAME=$(grep -E '^DB_DATABASE=' .env | cut -d= -f2- | tr -d '"\r' || true)
DB_ROOT=$(grep -E '^DB_ROOT_PASSWORD=' .env | cut -d= -f2- | tr -d '"\r' || true)
# tunggu MySQL siap (maks ~30 detik)
for i in $(seq 1 15); do
  if docker compose exec -T mysql mysqladmin ping -uroot -p"$DB_ROOT" --silent >/dev/null 2>&1; then break; fi
  sleep 2
done
TS=$(date +%Y%m%d_%H%M%S)
if docker compose exec -T mysql mysqldump -uroot -p"$DB_ROOT" "$DB_NAME" > "$BACKUP_DIR/db_${TS}.sql" 2>/dev/null && [ -s "$BACKUP_DIR/db_${TS}.sql" ]; then
  echo "    backup OK → $BACKUP_DIR/db_${TS}.sql"
  ls -1t "$BACKUP_DIR"/db_*.sql 2>/dev/null | tail -n +8 | xargs -r rm -f
else
  echo "    [WARN] backup gagal/kosong — deploy tetap lanjut, cek manual."
  rm -f "$BACKUP_DIR/db_${TS}.sql"
fi

echo "==> [4/5] migrate"
docker compose exec -T app php artisan migrate --force

echo "==> [5/5] refresh cache"
docker compose exec -T app php artisan config:cache
docker compose exec -T app php artisan route:cache || true
docker compose exec -T app php artisan view:cache || true

# Bersihkan image lama menganggur
docker image prune -f >/dev/null 2>&1 || true

echo "==> Selesai. Status:"
docker compose ps
