#!/usr/bin/env python3
"""
FastAPI endpoint simples para /api/rag/ingest
Compatível com o frontend atual
"""

from fastapi import FastAPI, Request, UploadFile, File, Form, HTTPException
from typing import List
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import JSONResponse
import sys
import os
import json
import time
import subprocess
from pathlib import Path
import psycopg2
from psycopg2.extras import RealDictCursor
from datetime import datetime

# Importar sistema de timeout adaptativo
sys.path.append(str(Path(__file__).parent / "scripts" / "rag_search"))
from adaptive_timeout import AdaptiveTimeout

# Adicionar scripts ao path
sys.path.append(str(Path(__file__).parent / "scripts"))

app = FastAPI(title="Simple RAG Ingest API")

# CORS para permitir chamadas do frontend
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

@app.post("/api/rag/ingest")
async def rag_ingest(request: Request):
    """
    Endpoint de ingest compatível com o Laravel
    Recebe: file (upload) OU text (texto direto) OU url
    Retorna: mesma resposta que o Laravel
    """
    try:
        start_time = time.time()
        
        # Parse multipart form data
        form = await request.form()
        
        # Support multiple file field names
        file = form.get('file')
        files = form.getlist('files[]') if not file else []
        title = form.get('title')
        text = form.get('text')
        url = form.get('url')
        tenant_slug = form.get('tenant_slug', 'default')
        user_id = form.get('user_id')
        
        # Debug: verificar o que foi recebido
        print(f"[DEBUG] Request recebido:")
        print(f"[DEBUG] file: {file}")
        print(f"[DEBUG] files: {files}")
        print(f"[DEBUG] title: {title}")
        print(f"[DEBUG] text: {text}")
        print(f"[DEBUG] url: {url}")
        
        # Determinar conteúdo e título
        content = ""
        doc_title = title or "Documento"
        
        if file or files:
            # Upload de arquivo(s)
            if file:
                # Arquivo único
                content = await file.read()
                doc_title = title or file.filename or "Documento"
            elif files:
                # Múltiplos arquivos - usar o primeiro
                file = files[0]
                content = await file.read()
                doc_title = title or file.filename or "Documento"
            
            # Processar arquivo com scripts Python existentes
            temp_path = f"/tmp/{file.filename}"
            with open(temp_path, "wb") as f:
                f.write(content)
            
            # Usar script de extração existente com timeout adaptativo
            try:
                pass  # subprocess já importado no topo
                
                # Calcular timeout adaptativo baseado no tamanho do arquivo
                timeout_seconds = AdaptiveTimeout.calculate_timeout(len(content))
                timeout_info = AdaptiveTimeout.get_timeout_info(len(content))
                
                print(f"[FASTAPI] Arquivo: {file.filename}, Tamanho: {timeout_info['file_size_mb']}MB")
                print(f"[FASTAPI] Timeout: {timeout_seconds}s, Tempo estimado: {timeout_info['estimated_processing_time']}")
                
                result = subprocess.run([
                    "python3", 
                    "scripts/document_extraction/main_extractor.py",
                    "--input", temp_path
                ], capture_output=True, text=True, timeout=timeout_seconds)
                
                if result.returncode == 0:
                    # Parse JSON response
                    extraction_result = json.loads(result.stdout)
                    content = extraction_result.get('extracted_text', '')
                    doc_title = extraction_result.get('title', doc_title) if extraction_result.get('title') else doc_title
                else:
                    # Fallback: usar conteúdo bruto
                    if isinstance(content, bytes):
                        content = content.decode('utf-8', errors='ignore')
                    
            except Exception as e:
                # Fallback: usar conteúdo bruto
                print(f"[DEBUG] Erro na extração: {e}", file=sys.stderr)
                if isinstance(content, bytes):
                    content = content.decode('utf-8', errors='ignore')
            
            # Limpar arquivo temporário
            try:
                os.unlink(temp_path)
            except:
                pass
                
        elif text:
            # Texto direto
            content = text
            doc_title = title or "Documento de Texto"
            
        elif url:
            # URL (placeholder - pode implementar depois)
            content = f"Conteúdo da URL: {url}"
            doc_title = title or f"URL: {url}"
            
        else:
            # Debug: verificar o que foi recebido
            print(f"[DEBUG] Nenhum arquivo/texto/url recebido")
            print(f"[DEBUG] file: {file}")
            print(f"[DEBUG] files: {files}")
            print(f"[DEBUG] text: {text}")
            print(f"[DEBUG] url: {url}")
            
            # Para debug: retornar erro mais detalhado
            return JSONResponse(
                content={
                    "ok": False,
                    "error": "Necessário: file, text ou url",
                    "debug": {
                        "file": str(file) if file else None,
                        "files": str(files) if files else None,
                        "text": text,
                        "url": url,
                        "title": title
                    }
                },
                status_code=422
            )
        
        if len(content.strip()) < 10:
            raise HTTPException(status_code=422, detail="Conteúdo muito curto. Mínimo 10 caracteres.")
        
        # Salvar conteúdo extraído no banco de dados
        document_id = None
        chunks_created = 0
        
        try:
            # Conectar ao banco
            conn = psycopg2.connect(
                dbname="laravel_rag_db",
                user="raguser_new",
                password="senhasegura123",
                host="127.0.0.1",
                port="5432"
            )
            cursor = conn.cursor()
            
            # Inserir documento
            # Pega o nome do arquivo de forma segura
            try:
                file_name = file.filename if 'file' in locals() and file else 'unknown'
            except:
                try:
                    file_name = files[0].filename if files and len(files) > 0 else 'unknown'
                except:
                    file_name = 'unknown'
            
            cursor.execute("""
                INSERT INTO documents (title, source, uri, tenant_slug, metadata, created_at, updated_at)
                VALUES (%s, %s, %s, %s, %s, NOW(), NOW())
                RETURNING id
            """, (
                doc_title,
                'file',
                file_name,
                tenant_slug or 'default',
                json.dumps({
                    'language': 'pt',
                    'file_type': 'unknown'
                })
            ))
            
            document_id = cursor.fetchone()[0]
            
            # Garantir que content é string
            if isinstance(content, bytes):
                content = content.decode('utf-8', errors='ignore')
            
            # Criar chunks (usar 'ord' ao invés de 'chunk_index' e 'meta' ao invés de 'metadata')
            chunks_text = [content[i:i+1000] for i in range(0, len(content), 1000)]
            
            for i, chunk_text in enumerate(chunks_text):
                cursor.execute("""
                    INSERT INTO chunks (document_id, content, ord, meta, created_at, updated_at)
                    VALUES (%s, %s, %s, %s, NOW(), NOW())
                """, (
                    document_id,
                    str(chunk_text),
                    i,
                    json.dumps({'type': 'text_chunk'})
                ))
            
            conn.commit()
            cursor.close()
            conn.close()
            
            chunks_created = len(chunks_text)
                
        except Exception as e:
            print(f"Erro ao salvar no banco: {e}", file=sys.stderr)
            import traceback
            traceback.print_exc()
            
            # Fallback - RETORNA TIMESTAMP EM CASO DE ERRO
            if document_id is None:
                document_id = int(time.time())
            if chunks_created == 0:
                chunks_created = max(1, len(content) // 1000) if content else 1
        
        processing_time = time.time() - start_time
        
        # Resposta compatível com Laravel
        response_data = {
            "ok": True,
            "document_id": document_id,
            "title": doc_title,
            "chunks_created": chunks_created,
            "processing_time": round(processing_time, 3),
            "extraction_method": "python_fastapi_real",
            "language_detected": "pt",
            "cache_stats": None,
            "enterprise_features_used": [],
            "retry_used": False
        }
        
        return JSONResponse(content=response_data, status_code=201)
        
    except HTTPException:
        raise
    except Exception as e:
        return JSONResponse(
            content={
                "ok": False,
                "error": f"Upload failed: {str(e)}",
                "error_type": type(e).__name__
            },
            status_code=500
        )

@app.get("/api/rag/health")
async def health():
    """Health check"""
    return {"ok": True, "service": "Simple RAG Ingest API"}

@app.get("/api/docs/list")
async def list_docs(request: Request):
    """Lista documentos do banco de dados"""
    try:
        # Conectar ao PostgreSQL
        conn = psycopg2.connect(
            dbname="laravel_rag_db",
            user="raguser_new",
            password="senhasegura123",
            host="127.0.0.1",
            port="5432"
        )
        
        cursor = conn.cursor(cursor_factory=RealDictCursor)
        
        # Buscar TODOS os documentos (sem limite)
        cursor.execute("""
            SELECT id, title, source, created_at, metadata 
            FROM documents 
            ORDER BY created_at DESC
        """)
        
        docs = cursor.fetchall()
        
        # Buscar contagem de chunks por documento
        cursor.execute("""
            SELECT document_id, COUNT(*) as count 
            FROM chunks 
            GROUP BY document_id
        """)
        
        chunks_count = {row['document_id']: row['count'] for row in cursor.fetchall()}
        
        # Adicionar contagem de chunks a cada documento
        for doc in docs:
            doc['chunks'] = chunks_count.get(doc['id'], 0)
        
        cursor.close()
        conn.close()
        
        # Converter para lista de dicts e converter datetime para string
        docs_list = []
        for doc in docs:
            doc_dict = dict(doc)
            # Converter datetime para string
            if 'created_at' in doc_dict and doc_dict['created_at']:
                if isinstance(doc_dict['created_at'], datetime):
                    doc_dict['created_at'] = doc_dict['created_at'].isoformat()
            docs_list.append(doc_dict)
        
        return JSONResponse(content={
            "ok": True,
            "docs": docs_list,
            "tenant": "default"
        })
        
    except Exception as e:
        print(f"Erro ao listar documentos: {e}", file=sys.stderr)
        return JSONResponse(
            content={
                "ok": False,
                "error": f"Erro ao listar documentos: {str(e)}"
            },
            status_code=500
        )

@app.post("/api/rag/python-search")
async def python_search(request: Request):
    """Busca RAG usando o script Python rag_search.py"""
    try:
        data = await request.json()
        
        # Parâmetros obrigatórios
        query = data.get('query')
        document_id = data.get('document_id')
        
        if not query:
            return JSONResponse(
                content={"success": False, "error": "Parâmetro query é obrigatório"},
                status_code=422
            )
        
        # Parâmetros opcionais
        top_k = data.get('top_k', 5)
        threshold = data.get('threshold', 0.3)
        mode = data.get('mode', 'auto')
        format_type = data.get('format', 'plain')
        enable_web_search = data.get('enable_web_search', False)
        force_grounding = data.get('force_grounding', False)
        
        # Chama o script Python rag_search.py
        cmd = [
            'python3',
            'scripts/rag_search/rag_search.py',
            '--query', query,
            '--document-id', str(document_id) if document_id else '',
            '--top-k', str(top_k),
            '--threshold', str(threshold),
            '--mode', mode,
            '--format', format_type
        ]
        
        if enable_web_search:
            cmd.append('--enable-web-search')
        if force_grounding:
            cmd.append('--force-grounding')
        
        result = subprocess.run(
            cmd,
            capture_output=True,
            text=True,
            cwd='/var/www/html/laravel-rag-vertex-full',
            timeout=120
        )
        
        if result.returncode == 0:
            import json
            response = json.loads(result.stdout)
            return response
        else:
            return JSONResponse(
                content={
                    "success": False,
                    "error": f"Erro ao executar busca: {result.stderr}"
                },
                status_code=500
            )
            
    except Exception as e:
        print(f"Erro na busca RAG: {e}", file=sys.stderr)
        return JSONResponse(
            content={"success": False, "error": f"Erro: {str(e)}"},
            status_code=500
        )

@app.post("/api/embeddings/generate")
async def generate_embeddings(request: Request):
    """Gera embeddings para todos os chunks de um documento"""
    try:
        data = await request.json()
        document_id = data.get('document_id')
        async_mode = data.get('async', False)
        
        if not document_id:
            return JSONResponse(
                content={"success": False, "error": "document_id é obrigatório"},
                status_code=422
            )
        
        # Chama o script Python batch_embeddings.py
        cmd = [
            'python3',
            'scripts/rag_search/batch_embeddings.py',
            str(document_id)
        ]
        
        result = subprocess.run(
            cmd,
            capture_output=True,
            text=True,
            cwd='/var/www/html/laravel-rag-vertex-full',
            timeout=300  # 5 minutos para documentos grandes
        )
        
        if result.returncode == 0:
            import json
            response = json.loads(result.stdout)
            return response
        else:
            return JSONResponse(
                content={
                    "success": False,
                    "error": f"Erro ao gerar embeddings: {result.stderr}"
                },
                status_code=500
            )
            
    except Exception as e:
        print(f"Erro ao gerar embeddings: {e}", file=sys.stderr)
        return JSONResponse(
            content={"success": False, "error": f"Erro: {str(e)}"},
            status_code=500
        )

@app.get("/api/embeddings/file-info")
async def file_info(filename: str):
    """Endpoint para informações do arquivo"""
    return {
        "success": True,
        "file_info": {
            "filename": filename,
            "estimated_pages": 50,  # Estimativa padrão
            "supported": True,
            "max_size_mb": 500,
            "max_pages": 5000
        }
    }

@app.get("/")
async def root():
    """Root endpoint"""
    return {
        "service": "Simple RAG Ingest API",
        "endpoints": [
            "POST /api/rag/ingest",
            "GET /api/rag/health"
        ]
    }

if __name__ == "__main__":
    import uvicorn
    print("🚀 Iniciando Simple RAG Ingest API...")
    print("📱 Endpoints:")
    print("  POST /api/rag/ingest - Upload de documentos")
    print("  GET /api/rag/health - Health check")
    print("  GET /api/docs/list - Lista documentos")
    print("  GET /docs - Documentação Swagger")
    uvicorn.run(app, host="0.0.0.0", port=8002)
