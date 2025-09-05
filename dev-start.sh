#!/usr/bin/env bash
set -Eeuo pipefail

echo "==> Projeto: ${GOOGLE_CLOUD_PROJECT:-desconhecido} | Região: ${VERTEX_LOCATION:-us-central1} | Emb: ${VERTEX_EMBEDDING_MODEL:-text-embedding-004}"

# 1) Sanity
[ -f artisan ] || { echo "ERRO: rode no diretório do Laravel (onde existe artisan)"; exit 1; }

# 2) Composer deps
if command -v composer >/dev/null 2>&1; then
  composer install --no-interaction --prefer-dist --optimize-autoloader
else
  echo "AVISO: composer não encontrado. Instale pelo site oficial (https://getcomposer.org)."
fi

# 3) APP_KEY e .env
if ! grep -q '^APP_KEY=' .env 2>/dev/null; then
  cp -n .env.example .env || true
fi
php artisan key:generate --force || true

# 4) Migrations (cria extensão vector, tabelas e índice)
php artisan migrate --force

# 5) Start server automaticamente (desative com START_SERVER=no)
if [ "${START_SERVER:-yes}" = "yes" ]; then
  PORT="${PORT:-8000}"
  echo "==> Subindo servidor interno: http://127.0.0.1:${PORT}"
  php artisan serve --host 0.0.0.0 --port "${PORT}"
else
  cat <<'TIP'

Rotas:
  GET  /api/health
  POST /api/rag/ping
  POST /api/rag/ingest   { "title": "doc", "text": "..." }
  POST /api/rag/query    { "q": "pergunta", "top_k": 5 }
  GET  /api/vertex/generate?q=...
  POST /api/vertex/generate { "prompt":"...", "contextParts":["..."] }
  POST /api/rag/answer   { "q":"...", "top_k":5 }

Para subir manualmente:
  php artisan serve --host 0.0.0.0 --port 8000

TIP
fi
