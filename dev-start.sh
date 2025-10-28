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

# 4) Migrations (cria extensão vector, tabelas e índice)
php artisan migrate --force

# 4.5) Install Python dependencies for FastAPI
echo "==> Instalando dependências Python para FastAPI..."
pip3 install PyJWT==2.8.0 || echo "⚠️  Erro ao instalar PyJWT (pode estar já instalado)"

# 5) Start Python servers
echo "==> Verificando e limpando processos conflitantes..."
# Matar processos que possam estar usando as portas
echo "   Matando processos nas portas 8000 e 8002..."
pkill -f "video_server.py" 2>/dev/null || true
pkill -f "fastapi_main.py" 2>/dev/null || true
pkill -f "simple_rag_ingest.py" 2>/dev/null || true
pkill -f "php artisan serve" 2>/dev/null || true

# Matar processos específicos das portas
lsof -ti:8000 | xargs kill -9 2>/dev/null || true
lsof -ti:8002 | xargs kill -9 2>/dev/null || true

# Aguardar um momento para liberar as portas
sleep 2

echo "==> Subindo servidor Python para processamento de vídeos: http://127.0.0.1:8001"
python3 scripts/video_processing/video_server.py 8001 &
PYTHON_VIDEO_PID=$!
echo "Python video server PID: $PYTHON_VIDEO_PID"

# Verificar se o servidor de vídeos iniciou corretamente
sleep 2
if curl -s http://localhost:8001/health >/dev/null 2>&1; then
  echo "✅ Servidor de vídeos iniciado com sucesso"
else
  echo "⚠️  Servidor de vídeos pode não ter iniciado corretamente"
fi

echo "==> Subindo servidor FastAPI para RAG: http://127.0.0.1:8002"
cd scripts/api && PYTHONPATH="/var/www/html/laravel-rag-vertex-full/scripts/document_extraction:/var/www/html/laravel-rag-vertex-full/scripts/api" python -m uvicorn main:app --host 0.0.0.0 --port 8002 &
FASTAPI_PID=$!
echo "FastAPI server PID: $FASTAPI_PID"
cd ../..

# Verificar se o FastAPI iniciou corretamente
sleep 3
if curl -s http://localhost:8002/health >/dev/null 2>&1; then
  echo "✅ Servidor FastAPI iniciado com sucesso"
  
  # WARM-UP: Aquecer o modelo Gemini para evitar cold start
  echo "==> Aquecendo modelo Gemini (warm-up)..."
  # Apenas verificar se o endpoint está respondendo (sem API key)
  curl -s http://localhost:8002/health >/dev/null 2>&1 || true
  
  echo "✅ Warm-up concluído (cold start evitado)"
else
  echo "⚠️  Servidor FastAPI pode não ter iniciado corretamente"
fi

# 6) Start server automaticamente (desative com START_SERVER=no)
if [ "${START_SERVER:-yes}" = "yes" ]; then
  PORT="${PORT:-8000}"
  echo "==> Subindo servidor Laravel: http://127.0.0.1:${PORT}"
  # Garantir que estamos no diretório correto
  cd /var/www/html/laravel-rag-vertex-full
  php artisan serve --host 0.0.0.0 --port "${PORT}"
else
  cat <<'TIP'

Servidores:
  Laravel (Frontend): http://127.0.0.1:8000
  FastAPI (Backend):  http://127.0.0.1:8002
  Video Server:       http://127.0.0.1:8001

Rotas Laravel (Frontend):
  GET  /rag-frontend (console RAG original)
  GET  /admin/login (painel admin original)
  GET  /precos (página de preços original)

Rotas FastAPI (Backend):
  POST /api/rag/ingest   { "title": "doc", "content": "..." }
  POST /api/rag/query    { "query": "pergunta", "top_k": 5 }
  GET  /api/rag/health
  GET  /api/rag/documents
  GET  /docs (documentação FastAPI)

Para subir manualmente:
  php artisan serve --host 0.0.0.0 --port 8000
  python3 fastapi_main.py --port 8002

TIP
fi
