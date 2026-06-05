#!/usr/bin/env bash
# =============================================================================
# Cyclos.ai production deploy script
#
# ALWAYS use this to deploy. One command does everything:
#   - pulls latest code and rebuilds the PHP images
#   - fixes storage permissions on the app-storage volume (the recurring
#     "storage/logs ... Permission denied" -> HTTP 500 root cause)
#   - runs central + tenant migrations
#   - clears stale caches so new routes/views/config load
#   - clean-syncs the Vite build so the CSS/JS hashes nginx serves can never
#     drift from the hashes the app manifest references (the bug that caused
#     recurring "broken CSS / blank page")
#
# Usage:  git pull && bash deploy.sh      (pull first so you get THIS script)
# =============================================================================
set -euo pipefail
cd "$(dirname "$0")"

APP_DIR=/var/www/html

echo "==> [1/8] Pulling latest code"
git pull

echo "==> [2/8] Building app images (no cache)"
docker compose build --no-cache app queue scheduler

echo "==> [3/8] Starting containers"
docker compose up -d

echo "==> [4/8] Fixing storage permissions (app-storage volume is root-owned)"
docker compose exec -u root -T app chown -R www-data:www-data "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"
docker compose exec -u root -T app chmod -R 775 "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"

echo "==> [5/8] Running migrations (central + all tenants)"
docker compose exec -T app php artisan migrate --force
docker compose exec -T app php artisan tenants:migrate --force

echo "==> [6/8] Clearing stale caches (routes/views/config/cache)"
docker compose exec -T app php artisan optimize:clear

echo "==> [7/8] CLEAN-syncing built assets to nginx (rm -rf first — critical)"
rm -rf public/build
docker compose cp app:"$APP_DIR/public/build" ./public/build

echo "==> [8/8] Restarting nginx + verifying manifest <-> disk match"
docker compose restart nginx
sleep 3
MANIFEST=""
for p in public/build/.vite/manifest.json public/build/manifest.json; do
  if [ -f "$p" ]; then MANIFEST="$p"; break; fi
done
if [ -n "$MANIFEST" ]; then
  JS=$(grep -oE '"assets/app-[A-Za-z0-9_-]+\.js"' "$MANIFEST" | head -1 | tr -d '"' || true)
  CSS=$(grep -oE '"assets/app-[A-Za-z0-9_-]+\.css"' "$MANIFEST" | head -1 | tr -d '"' || true)
  for a in "$JS" "$CSS"; do
    if [ -n "$a" ] && [ -f "public/build/$a" ]; then
      echo "    OK  $a present"
    elif [ -n "$a" ]; then
      echo "    !!! $a MISSING from disk — assets out of sync"; exit 1
    fi
  done
else
  echo "    WARNING: no manifest found under public/build"
fi

echo ""
echo "==> Deploy complete. App is live at https://cyclos.ai"
echo "    New: Scheduled Drops -> shipper compiles a drop list and sends it to the carrier."
