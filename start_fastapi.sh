#!/bin/bash

# FastAPI RAG System Startup Script
# Sistema completo migrado do Laravel para FastAPI

echo "🚀 Iniciando FastAPI RAG System..."

# Verificar se Python 3 está instalado
if ! command -v python3 &> /dev/null; then
    echo "❌ Python 3 não encontrado. Instale Python 3.8+ primeiro."
    exit 1
fi

# Verificar se pip está instalado
if ! command -v pip3 &> /dev/null; then
    echo "❌ pip3 não encontrado. Instale pip primeiro."
    exit 1
fi

# Instalar dependências
echo "📦 Instalando dependências..."
pip3 install -r requirements_fastapi.txt

# Verificar se PostgreSQL está rodando
if ! pg_isready -h 127.0.0.1 -p 5432 &> /dev/null; then
    echo "⚠️ PostgreSQL não está rodando. Iniciando..."
    sudo systemctl start postgresql
    sleep 5
fi

# Verificar conexão com banco
echo "🗄️ Verificando conexão com banco de dados..."
python3 -c "
import psycopg2
try:
    conn = psycopg2.connect(
        host='127.0.0.1',
        port='5432',
        database='laravel_rag',
        user='postgres',
        password='postgres'
    )
    print('✅ Conexão com banco estabelecida')
    conn.close()
except Exception as e:
    print(f'❌ Erro na conexão com banco: {e}')
    exit(1)
"

# Verificar se o script RAG está disponível
if [ ! -f "scripts/rag_search/rag_search.py" ]; then
    echo "⚠️ Script RAG não encontrado. Criando estrutura básica..."
    mkdir -p scripts/rag_search
    cat > scripts/rag_search/rag_search.py << 'EOF'
class RagSearch:
    def __init__(self, config):
        self.config = config
    
    def search(self, **kwargs):
        return {
            'chunks': [],
            'answer': 'Sistema RAG em desenvolvimento',
            'metadata': {},
            'mode_used': 'auto'
        }
    
    def ingest_document(self, **kwargs):
        return {
            'document_id': 1,
            'chunks_created': 0
        }
    
    def list_user_documents(self, user_id):
        return []
    
    def get_document(self, doc_id, user_id):
        return None
    
    def delete_document(self, doc_id, user_id):
        return True
    
    def get_user_stats(self, user_id):
        return {}

class RagConfig:
    pass
EOF
fi

# Parar servidor Laravel se estiver rodando
echo "🛑 Parando servidor Laravel..."
pkill -f "php artisan serve" 2>/dev/null || true

# Iniciar FastAPI
echo "🚀 Iniciando FastAPI RAG System..."
echo "📱 Acesse: http://localhost:8000"
echo "📚 Documentação: http://localhost:8000/docs"
echo "🔧 Admin: http://localhost:8000/admin"
echo "🎯 RAG Console: http://localhost:8000/rag-frontend"
echo ""
echo "Pressione Ctrl+C para parar o servidor"

python3 fastapi_main.py
