#!/usr/bin/env bash
# Deploy An-Nur Smart System di VPS (build di server).
# Pakai: bash deploy.sh   (dijalankan dari dalam folder repo di VPS)
#
# Darurat: IZINKAN_PHP_LAMA=1 bash deploy.sh  → lewati gerbang versi PHP.
# Hanya untuk keadaan di mana rilis PHP aman belum tersedia di registry
# sementara ada perbaikan yang harus naik sekarang. Alasannya dicatat di log.
set -euo pipefail
cd "$(dirname "$0")"

# ── Versi PHP minimum per cabang ────────────────────────────────────────────
# Patch aman menurut rilis keamanan PHP 21 Okt 2026 ("php2shell",
# CVE-2026-109618 & CVE-2026-109619, CVSS 9.2). Perbarui daftar ini setiap ada
# rilis keamanan PHP baru — inilah satu-satunya tempat yang perlu diubah.
PHP_MIN_PER_CABANG="8.2:35 8.3:36 8.4:27 8.5:12"
IMAGE_APP="${IMAGE_APP:-annur-smart:latest}"   # sama dengan `image:` service app di docker-compose.yml

nilai_versi_php() {
  local ver="$1"
  local cabang min min_penuh terendah tertinggi pasangan

  cabang="${ver%.*}"                 # 8.2.35 -> 8.2

  min=""
  for pasangan in $PHP_MIN_PER_CABANG; do
    [ "${pasangan%%:*}" = "$cabang" ] && min="${pasangan##*:}"
  done

  if [ -n "$min" ]; then
    min_penuh="$cabang.$min"
    # ver < min_penuh ?  (urutan versi, bukan urutan teks: 8.2.9 < 8.2.35)
    if [ "$ver" != "$min_penuh" ] \
       && [ "$(printf '%s\n%s\n' "$ver" "$min_penuh" | sort -V | head -1)" = "$ver" ]; then
      echo "    [GAGAL] PHP $ver lebih lama dari $min_penuh (rilis keamanan 21 Okt 2026)."
      echo "            Image dasar di registry belum tertambal, atau Dockerfile menunjuk tag lama."
      return 1
    fi
    echo "    aman: PHP $ver >= $min_penuh"
    return 0
  fi

  # Cabang tak ada di daftar: bedakan "lebih tua" (berbahaya) dari "lebih baru"
  # (daftarnya yang ketinggalan).
  terendah=$(printf '%s\n' $PHP_MIN_PER_CABANG | cut -d: -f1 | sort -V | head -1)
  tertinggi=$(printf '%s\n' $PHP_MIN_PER_CABANG | cut -d: -f1 | sort -V | tail -1)
  if [ "$cabang" != "$terendah" ] \
     && [ "$(printf '%s\n%s\n' "$cabang" "$terendah" | sort -V | head -1)" = "$cabang" ]; then
    echo "    [GAGAL] PHP $cabang sudah tidak mendapat perbaikan keamanan dari proyek PHP."
    echo "            Naikkan ke $terendah atau lebih baru di Dockerfile."
    return 1
  fi
  echo "    [WARN] PHP $cabang lebih baru dari daftar (tertinggi: $tertinggi)."
  echo "           Perbarui PHP_MIN_PER_CABANG di deploy.sh agar gerbangnya tetap menjaga."
  return 0
}

periksa_php() {
  local ver build_date
  ver=$(docker run --rm --entrypoint php "$IMAGE_APP" -r 'echo PHP_VERSION;' 2>/dev/null) || {
    echo "    [GAGAL] tidak bisa menjalankan php di image $IMAGE_APP"
    return 1
  }
  # Build date hanya dicatat di log; formatnya terlalu rapuh untuk jadi gerbang.
  build_date=$(docker run --rm --entrypoint php "$IMAGE_APP" -i 2>/dev/null |
    sed -n 's/^Build Date => //p' | head -1)
  echo "    image $IMAGE_APP -> PHP $ver (build ${build_date:-tidak diketahui})"

  nilai_versi_php "$ver"
}

# Periksa satu image saja, lalu keluar. Untuk membuktikan gerbangnya memang
# menolak, dan untuk memeriksa image apa pun tanpa menjalankan deploy:
#   bash deploy.sh --periksa-image php:8.1-fpm-alpine
if [ "${1:-}" = "--periksa-image" ]; then
  IMAGE_APP="${2:-$IMAGE_APP}"
  periksa_php && { echo "  => LOLOS gerbang"; exit 0; } || { echo "  => DITOLAK gerbang"; exit 1; }
fi

# Mode uji: hanya menguji logika versi, tanpa menyentuh server sama sekali.
if [ "${1:-}" = "--uji-versi" ]; then
  gagal=0
  # "versi|harapan(ok/gagal)"
  for kasus in     "8.2.35|ok" "8.2.36|ok" "8.2.40|ok" "8.2.34|gagal" "8.2.9|gagal" "8.2.0|gagal"     "8.3.36|ok" "8.3.37|ok" "8.3.35|gagal" "8.3.6|gagal"     "8.4.27|ok" "8.4.26|gagal" "8.5.12|ok" "8.5.11|gagal"     "8.1.33|gagal" "8.0.30|gagal" "7.4.33|gagal"     "8.6.0|ok" "9.0.1|ok"
  do
    v="${kasus%%|*}"; harap="${kasus##*|}"
    if keluaran=$(nilai_versi_php "$v" 2>&1); then hasil=ok; else hasil=gagal; fi
    if [ "$hasil" = "$harap" ]; then
      printf '  OK    %-9s -> %-5s %s
' "$v" "$hasil" "$(echo "$keluaran" | head -1 | sed 's/^ *//')"
    else
      printf '  SALAH %-9s -> %-5s (harusnya %s)
' "$v" "$hasil" "$harap"
      gagal=$((gagal + 1))
    fi
  done
  echo
  [ "$gagal" -eq 0 ] && echo "Semua kasus versi lulus." || echo "$gagal kasus SALAH."
  exit "$gagal"
fi

echo "==> [1/7] git pull"
# deploy.sh MENARIK VERSI BARU DIRINYA SENDIRI di sini. Bash membaca skrip
# secara bertahap, jadi berkas yang berubah panjang di tengah eksekusi bisa
# membuat sisa perintah terbaca dari offset yang salah. Karena itu: bila
# skripnya ikut berubah, jalankan ulang versi barunya dari awal.
SKRIP_SEBELUM=$(sha256sum "$0" | cut -d' ' -f1)
git pull --ff-only
if [ "${DEPLOY_REEXEC:-0}" != "1" ] \
   && [ "$(sha256sum "$0" | cut -d' ' -f1)" != "$SKRIP_SEBELUM" ]; then
  echo "    deploy.sh ikut diperbarui → menjalankan ulang versi barunya"
  DEPLOY_REEXEC=1 exec bash "$0" "$@"
fi

# APP_KEY wajib terisi nilai asli (compose env_file tak menjalankan
# key:generate otomatis). Isi sekali bila belum ada.
if [ -f .env ] && ! grep -qE '^APP_KEY=base64:' .env; then
  echo "    generate APP_KEY baru"
  sed -i "s|^APP_KEY=.*|APP_KEY=base64:$(openssl rand -base64 32)|" .env
fi

echo "==> [2/7] build image (tarik ulang image dasar)"
# --pull WAJIB: tanpa ini `build` memakai image dasar yang tersimpan di VPS,
# sehingga perbaikan keamanan PHP di php:8.2-fpm-alpine tidak pernah masuk —
# dan tidak ada yang tahu. 22 Okt 2026 kita kebetulan tertambal hanya karena
# `docker image prune` menghapus image dasarnya tiap deploy; itu kebetulan,
# bukan jaminan.
docker compose build --pull

echo "==> [3/7] periksa versi PHP di image baru"
# Diperiksa SEBELUM container dipromosikan: bila gagal, container lama yang
# sudah tertambal tetap melayani dan tidak ada yang turun versi diam-diam.
# Keputusan murni atas SATU string versi. Dipisah dari Docker supaya bisa
# diuji sendiri: bash deploy.sh --uji-versi

if ! periksa_php; then
  if [ "${IZINKAN_PHP_LAMA:-0}" = "1" ]; then
    echo "    [WARN] gerbang versi PHP DILEWATI atas permintaan (IZINKAN_PHP_LAMA=1)."
    echo "           Deploy lanjut dengan PHP yang belum tertambal — perbaiki segera."
  else
    echo
    echo "==> DEPLOY DIBATALKAN. Container lama tetap berjalan, tidak ada yang berubah."
    echo "    Perbaiki versi PHP lalu jalankan ulang; atau, bila memang terpaksa:"
    echo "    IZINKAN_PHP_LAMA=1 bash deploy.sh"
    exit 1
  fi
fi

echo "==> [4/7] up container"
docker compose up -d

echo "==> [5/7] backup database (sebelum migrate, simpan 7 terakhir)"
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

echo "==> [6/7] migrate"
docker compose exec -T app php artisan migrate --force

echo "==> [7/7] refresh cache"
docker compose exec -T app php artisan config:cache
docker compose exec -T app php artisan route:cache || true
docker compose exec -T app php artisan view:cache || true

# Bersihkan image lama menganggur. Ini juga yang membuat image DASAR terhapus,
# sehingga build berikutnya pasti menarik ulang — tapi jangan diandalkan:
# --pull di langkah [2/7] yang menjamin, bukan prune ini.
docker image prune -f >/dev/null 2>&1 || true

echo "==> Selesai. Status:"
docker compose ps

# Versi PHP yang benar-benar melayani web, dicetak terakhir agar tercatat di log.
echo "==> PHP yang melayani:"
docker compose exec -T app php-fpm -v 2>/dev/null | head -1 | sed 's/^/    /' || true
