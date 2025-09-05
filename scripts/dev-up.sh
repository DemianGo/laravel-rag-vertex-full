#!/usr/bin/env bash
set -euo pipefail
APP_DIR="/var/www/html/laravel-rag-vertex-full"
PORT="${PORT:-8001}"

# para qualquer serve antigo e libera porta
pkill -f "artisan serve" 2>/dev/null || true
fuser -k ${PORT}/tcp 2>/dev/null || true

cd "$APP_DIR"
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
nohup php artisan serve --host=127.0.0.1 --port=${PORT} > /tmp/laravel-serve.log 2>&1 &

sleep 1
echo "[ok] dev server em http://127.0.0.1:${PORT}"
echo "[log] tail -f /tmp/laravel-serve.log"
