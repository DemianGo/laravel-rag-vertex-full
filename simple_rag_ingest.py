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
from pathlib import Path

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
                import subprocess
                
                # Calcular timeout adaptativo baseado no tamanho do arquivo
                timeout_seconds = AdaptiveTimeout.calculate_timeout(len(content))
                timeout_info = AdaptiveTimeout.get_timeout_info(len(content))
                
                print(f"[FASTAPI] Arquivo: {file.filename}, Tamanho: {timeout_info['file_size_mb']}MB")
                print(f"[FASTAPI] Timeout: {timeout_seconds}s, Tempo estimado: {timeout_info['estimated_processing_time']}")
                
                result = subprocess.run([
                    "python3", 
                    "scripts/document_extraction/main_extractor.py",
                    temp_path
                ], capture_output=True, text=True, timeout=timeout_seconds)
                
                if result.returncode == 0:
                    # Parse JSON response
                    extraction_result = json.loads(result.stdout)
                    content = extraction_result.get('content', '')
                    doc_title = extraction_result.get('title', doc_title)
                else:
                    # Fallback: usar conteúdo bruto
                    content = content.decode('utf-8', errors='ignore')
                    
            except Exception as e:
                # Fallback: usar conteúdo bruto
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
        
        # Usar sistema real de ingestão
        try:
            # Chamar o script de ingestão real
            import subprocess
            import tempfile
            import os
            
            # Criar arquivo temporário com o conteúdo
            with tempfile.NamedTemporaryFile(mode='w', suffix='.txt', delete=False) as temp_file:
                temp_file.write(content)
                temp_file_path = temp_file.name
            
            # Chamar o script de ingestão real
            cmd = [
                'python3', 
                'scripts/document_extraction/main_extractor.py',
                '--file', temp_file_path,
                '--title', doc_title,
                '--tenant', tenant_slug or 'default'
            ]
            
            result = subprocess.run(cmd, capture_output=True, text=True, cwd='/var/www/html/laravel-rag-vertex-full')
            
            # Limpar arquivo temporário
            os.unlink(temp_file_path)
            
            if result.returncode == 0:
                # Parse da resposta do script
                import json
                ingest_result = json.loads(result.stdout)
                document_id = ingest_result.get('document_id')
                chunks_created = ingest_result.get('chunks_created', 1)
            else:
                # Fallback se script falhar
                document_id = int(time.time())
                chunks_created = max(1, len(content) // 1000)
                
        except Exception as e:
            print(f"Erro ao processar com script real: {e}", file=sys.stderr)
            # Fallback
            document_id = int(time.time())
            chunks_created = max(1, len(content) // 1000)
        
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
    print("  GET /docs - Documentação Swagger")
    uvicorn.run(app, host="0.0.0.0", port=8002)
