#!/usr/bin/env bash
# =============================================================================
# Cyclos.ai production deploy script
#
# ALWAYS use this to deploy. It clean-syncs the Vite build so the CSS/JS
# hashes nginx serves can never drift from the hashes the app's manifest
# references (the bug that caused recurring "broken CSS / blank page").
#
# Usage:  bash deploy.sh
# =============================================================================
set -euo pipefail
cd "$(dirname "$0")"

echo "==> [1/6] Pulling latest code"
git pull

echo "==> [2/6] Building app images (no cache)"
docker compose build --no-cache app queue scheduler

echo "==> [3/6] Starting containers"
docker compose up -d

echo "==> [4/6] CLEAN-syncing built assets to nginx (rm -rf first — critical)"
rm -rf public/build
docker compose cp app:/var/www/html/public/build ./public/build

echo "==> [5/6] Restarting nginx"
docker compose restart nginx

echo "==> [6/6] Verifying manifest <-> disk match"
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
