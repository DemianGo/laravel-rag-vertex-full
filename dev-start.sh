#!/usr/bin/env bash
set -Eeuo pipefail

# Carregar .env primeiro
set -a
[ -f .env ] && source <(grep -v '^#' .env | sed 's/\r$//')
set +a

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

# 3.5) Verificar e autenticar Google Cloud (para Cloud Vision API + Vertex AI)
if command -v gcloud >/dev/null 2>&1; then
  echo "==> Verificando autenticação Google Cloud..."
  
  # Verificação rápida: checa apenas se há credenciais configuradas (sem chamada lenta)
  if [ -n "${GOOGLE_APPLICATION_CREDENTIALS:-}" ] && [ -f "${GOOGLE_APPLICATION_CREDENTIALS:-}" ]; then
    echo "✅ Google Cloud credenciais configuradas (arquivo JSON)"
  else
    # Verifica apenas se há configuração gcloud (verificação rápida de arquivo)
    if [ -f ~/.config/gcloud/configurations/config_default ]; then
      echo "✅ Google Cloud configurado (gcloud auth)"
    else
      echo "⚠️  Google Cloud não autenticado ou token expirado."
      echo ""
      echo "🔐 Autenticação necessária para:"
      echo "   • Cloud Vision API (OCR com 99%+ precisão)"
      echo "   • Vertex AI (Embeddings e LLM)"
      echo ""
      echo "Iniciando autenticação automática..."
      echo ""
      
      # Lê projeto do .env
      read_env() {
        local key="$1"; local def="$2"; local v
        v="$(sed -nE "s/^${key}=(.*)/\1/p" .env 2>/dev/null | tail -n1 | tr -d '"' | tr -d "'")" || true
        [ -n "${v:-}" ] && echo "$v" || echo "$def"
      }
      PROJECT_ID="$(read_env GOOGLE_CLOUD_PROJECT liberai-ai)"
      
      echo "📋 Projeto: ${PROJECT_ID}"
      echo "🌐 Abrindo navegador para autenticação..."
      echo ""
      
      # Autenticação automática (abre navegador automaticamente)
      if gcloud auth application-default login --scopes=https://www.googleapis.com/auth/cloud-platform; then
        # Configura projeto
        gcloud config set project "${PROJECT_ID}" -q 2>/dev/null || true
        gcloud auth application-default set-quota-project "${PROJECT_ID}" -q 2>/dev/null || true
        
        echo "✅ Google Cloud autenticado com sucesso!"
        echo ""
      else
        echo "❌ Autenticação falhou ou foi cancelada."
        echo "   Para tentar novamente: gcloud auth application-default login"
        echo ""
      fi
    fi
  fi
fi

# 4) Migrations (cria extensão vector, tabelas e índice)
php artisan migrate --force

# 4.5) Preload de modelos para otimizar performance
echo "==> Preload de modelos de embeddings..."
cd scripts/rag_search
python3 -c "import sys; sys.path.insert(0, '.'); import config, embeddings_service, vector_search, llm_service, database; print('✅ Modelos carregados com sucesso')" 2>/dev/null || echo "⚠️  Preload de modelos falhou (não crítico)"
cd ../..

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
