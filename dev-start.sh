#!/usr/bin/env bash
set -Eeuo pipefail

# Carregar .env primeiro
set -a
[ -f .env ] && source <(grep -v '^#' .env | sed 's/\r$//')
set +a

echo "==> Projeto: ${GOOGLE_CLOUD_PROJECT:-desconhecido} | Região: ${VERTEX_LOCATION:-us-central1} | Emb: ${VERTEX_EMBEDDING_MODEL:-text-embedding-004}"
# 1) Sanity
[ -f simple_fastapi_fixed.py ] || { echo "ERRO: rode no diretório do projeto (onde existe simple_fastapi_fixed.py)"; exit 1; }

# 2) Python dependencies
echo "==> Verificando dependências Python..."
if command -v pip3 >/dev/null 2>&1; then
  pip3 install -r requirements_enterprise.txt --quiet
else
  echo "AVISO: pip3 não encontrado. Instale as dependências Python manualmente."
fi

# 3) Verificar .env
if [ ! -f .env ]; then
  echo "AVISO: arquivo .env não encontrado. Criando a partir do exemplo..."
  cp -n .env.example .env || true
fi

# 3.5) Verificar e autenticar Google Cloud (para Cloud Vision API + Vertex AI)
if command -v gcloud >/dev/null 2>&1; then
  echo "==> Verificando autenticação Google Cloud..."
  
  # Verificação rápida: checa apenas se há credenciais configuradas (sem chamada lenta)
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
      
      # Valida token
      TOKEN="$(gcloud auth application-default print-access-token 2>/dev/null || true)"
      if [ -n "$TOKEN" ]; then
        echo "✅ Google Cloud autenticado com sucesso!"
        echo ""
      else
        echo "❌ Autenticação falhou. Execute manualmente:"
        echo "   bash gcp-auth-reset.sh"
        echo ""
      fi
    else
      echo "❌ Autenticação falhou ou foi cancelada."
      echo "   Para tentar novamente: bash gcp-auth-reset.sh"
      echo ""
    fi
  fi
fi

# 4) Start Python video server
echo "==> Subindo servidor Python para processamento de vídeos: http://127.0.0.1:8001"
python3 scripts/video_processing/fastapi_video_server.py 8001 &
PYTHON_VIDEO_PID=$!
echo "Python video server PID: $PYTHON_VIDEO_PID"

# 5) Start FastAPI server automaticamente (desative com START_SERVER=no)
if [ "${START_SERVER:-yes}" = "yes" ]; then
  PORT="${PORT:-8000}"
  echo "==> Subindo servidor FastAPI: http://127.0.0.1:${PORT}"
  python3 simple_fastapi_fixed.py
else
  cat <<'TIP'

Rotas FastAPI:
  GET  /health
  GET  /docs (documentação Swagger)
  GET  /rag-frontend (console RAG)
  GET  /admin (painel admin)
  GET  /precos (página de preços)
  POST /api/video/process (processamento de vídeos)
  POST /api/rag/search (busca RAG)

Para subir manualmente:
  python3 simple_fastapi_fixed.py

TIP
fi
