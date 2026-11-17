#!/usr/bin/env bash
# =============================================================================
# Paketkan aplikasi siap-upload untuk Shared Hosting Hostinger (File Manager)
# -----------------------------------------------------------------------------
# Menghasilkan: dist-hostinger/kp-if-sakti-hostinger.zip
# Isi paket:
#   - .htaccess                  (rewrite root public_html -> backend/public)
#   - backend/                   (kode Laravel + vendor --no-dev + SPA build)
#   - backend/.env.sharedhosting.example
#
# Jalankan dari root repo:  bash scripts/package-hostinger.sh
# =============================================================================
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DIST_DIR="$ROOT_DIR/dist-hostinger"
STAGE_DIR="$DIST_DIR/stage"
ZIP_FILE="$DIST_DIR/kp-if-sakti-hostinger.zip"

echo "==> 1/4 Build frontend & salin SPA ke backend/public"
bash "$ROOT_DIR/scripts/build-shared-hosting.sh"

echo "==> 2/4 Install dependensi Composer produksi (--no-dev)"
cd "$ROOT_DIR/backend"
composer install --no-dev --optimize-autoloader --no-interaction

echo "==> 3/4 Siapkan staging folder"
rm -rf "$STAGE_DIR" "$ZIP_FILE"
mkdir -p "$STAGE_DIR/backend"

cp "$ROOT_DIR/.htaccess" "$STAGE_DIR/.htaccess"

rsync -a \
  --exclude='.env' \
  --exclude='.env.backup' \
  --exclude='.env.production' \
  --exclude='.phpunit*' \
  --exclude='.git*' \
  --exclude='node_modules' \
  --exclude='tests' \
  --exclude='public/storage' \
  --exclude='public/hot' \
  --exclude='storage/logs/*' \
  --exclude='storage/framework/cache/data/*' \
  --exclude='storage/framework/sessions/*' \
  --exclude='storage/framework/views/*' \
  "$ROOT_DIR/backend/" "$STAGE_DIR/backend/"

mkdir -p \
  "$STAGE_DIR/backend/storage/app/public" \
  "$STAGE_DIR/backend/storage/framework/cache/data" \
  "$STAGE_DIR/backend/storage/framework/sessions" \
  "$STAGE_DIR/backend/storage/framework/views" \
  "$STAGE_DIR/backend/storage/logs" \
  "$STAGE_DIR/backend/bootstrap/cache"

echo "==> 4/4 Buat arsip ZIP: $ZIP_FILE"
cd "$STAGE_DIR"
zip -rq "$ZIP_FILE" .htaccess backend
rm -rf "$STAGE_DIR"

echo ""
echo "Selesai! Paket siap upload:"
echo "  $ZIP_FILE"
echo ""
echo "Langkah singkat di Hostinger hPanel:"
echo "  1. Upload & Extract $ZIP_FILE ke public_html/"
echo "  2. Salin backend/.env.sharedhosting.example -> backend/.env lalu isi DB & APP_URL"
echo "  3. Jalankan via SSH (di folder public_html/backend):"
echo "       php artisan key:generate"
echo "       php artisan migrate --force --seed"
echo "       php artisan storage:link"
echo "       php artisan optimize"
echo "  Panduan lengkap: docs/HOSTINGER_DEPLOYMENT.md"
