#!/usr/bin/env bash
set -Eeuo pipefail

cd "$(dirname "$0")"

read_env() {
  local key="$1"; local def="$2"
  local v
  v="$(sed -nE "s/^${key}=(.*)/\1/p" .env 2>/dev/null | tail -n1 | tr -d '"' | tr -d "'")" || true
  [ -n "${v:-}" ] && echo "$v" || echo "$def"
}

APP_PORT="$(read_env APP_PORT 8001)"
PIDFILE=".serve.pid"

# mata pelo PID salvo
if [ -f "$PIDFILE" ]; then
  PID="$(cat "$PIDFILE" 2>/dev/null || true)"
  if [ -n "${PID:-}" ] && ps -p "$PID" >/dev/null 2>&1; then
    kill "$PID" 2>/dev/null || true
    sleep 0.5
  fi
  rm -f "$PIDFILE"
fi

# mata qualquer artisan serve remanescente e libera porta
pkill -f "artisan serve" 2>/dev/null || true
fuser -k "${APP_PORT}/tcp" 2>/dev/null || true

echo "[ok] servidor parado e porta ${APP_PORT} liberada"
