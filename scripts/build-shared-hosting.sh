#!/usr/bin/env bash
# =============================================================================
# Build & rakit aplikasi untuk SHARED HOSTING (satu domain, Laravel sajikan SPA)
# -----------------------------------------------------------------------------
# Menghasilkan React build dan menyalinnya ke backend/public supaya:
#   - /assets/*     -> dilayani langsung oleh Apache (file statis)
#   - /favicon.svg  -> dilayani langsung
#   - index.html    -> disalin sebagai public/spa.html, dilayani Laravel
#                      (routes/web.php akan memakai public/spa.html)
#
# Jalankan dari root repo:  bash scripts/build-shared-hosting.sh
# =============================================================================
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
FRONTEND_DIR="$ROOT_DIR/frontend"
PUBLIC_DIR="$ROOT_DIR/backend/public"

echo "==> 1/4 Build frontend (React/Vite)"
cd "$FRONTEND_DIR"
if [ -f package-lock.json ]; then
  npm ci
else
  npm install
fi
npm run build

echo "==> 2/4 Bersihkan asset lama di public/"
rm -rf "$PUBLIC_DIR/assets"
rm -f "$PUBLIC_DIR/spa.html"

echo "==> 3/4 Salin hasil build ke backend/public"
cp -R "$FRONTEND_DIR/dist/assets" "$PUBLIC_DIR/assets"
cp "$FRONTEND_DIR/dist/index.html" "$PUBLIC_DIR/spa.html"
# Salin file statis di root dist (favicon, dll) kecuali index.html
find "$FRONTEND_DIR/dist" -maxdepth 1 -type f ! -name 'index.html' \
  -exec cp {} "$PUBLIC_DIR/" \;

echo "==> 4/4 Selesai."
echo ""
echo "Berikutnya di server (dari folder backend):"
echo "  composer install --no-dev --optimize-autoloader"
echo "  cp .env.sharedhosting.example .env   # lalu isi nilai sebenarnya"
echo "  php artisan key:generate"
echo "  php artisan migrate --force"
echo "  php artisan storage:link   # jika hosting mengizinkan symlink"
echo "  php artisan config:cache && php artisan route:cache && php artisan view:cache"
echo ""
echo "Catatan: arahkan Document Root domain ke folder backend/public."
