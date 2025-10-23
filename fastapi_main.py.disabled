#!/usr/bin/env python3
"""
FastAPI RAG System - Sistema Principal
Migração completa do Laravel para FastAPI
"""

from fastapi import FastAPI, Request, Depends, HTTPException, status
from fastapi.security import HTTPBearer, HTTPAuthorizationCredentials
from fastapi.templating import Jinja2Templates
from fastapi.staticfiles import StaticFiles
from fastapi.middleware.cors import CORSMiddleware
from fastapi.middleware.trustedhost import TrustedHostMiddleware
from fastapi.responses import HTMLResponse, JSONResponse
from contextlib import asynccontextmanager
import uvicorn
import os
import sys
import asyncio
from pathlib import Path

# Adicionar scripts ao path
sys.path.append(str(Path(__file__).parent / "scripts"))

# Importar routers
try:
    from routers import auth, rag, admin, payments, video, documents
except ImportError as e:
    print(f"⚠️ Erro ao importar routers: {e}")
    print("Criando routers básicos...")
    
    # Criar routers básicos se não existirem
    import os
    os.makedirs("routers", exist_ok=True)
    
    with open("routers/__init__.py", "w") as f:
        f.write("# Routers package")
    
    # Importar novamente
    from routers import auth, rag, admin, payments, video, documents

# Configuração do FastAPI
@asynccontextmanager
async def lifespan(app: FastAPI):
    """Startup e shutdown events"""
    # Startup
    print("🚀 Iniciando FastAPI RAG System...")
    
    # Verificar dependências
    try:
        import psycopg2
        import sqlalchemy
        print("✅ Dependências verificadas")
    except ImportError as e:
        print(f"❌ Dependência faltando: {e}")
        raise
    
    yield
    
    # Shutdown
    print("🛑 Parando FastAPI RAG System...")

# Criar aplicação FastAPI
app = FastAPI(
    title="RAG System",
    description="Sistema RAG completo com IA, processamento de documentos e vídeos",
    version="2.0.0",
    lifespan=lifespan,
    docs_url="/docs",
    redoc_url="/redoc"
)

# Configurar CORS
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Configurar templates
templates = Jinja2Templates(directory="templates")

# Configurar arquivos estáticos
app.mount("/static", StaticFiles(directory="static"), name="static")

# Configurar security
security = HTTPBearer()

# Incluir routers (com tratamento de erro)
try:
    app.include_router(auth.router, prefix="/auth", tags=["Authentication"])
    app.include_router(rag.router, prefix="/api/rag", tags=["RAG"])
    app.include_router(admin.router, prefix="/admin", tags=["Admin"])
    app.include_router(payments.router, prefix="/api/payments", tags=["Payments"])
    app.include_router(video.router, prefix="/api/video", tags=["Video"])
    app.include_router(documents.router, prefix="/api/documents", tags=["Documents"])
    print("✅ Todos os routers carregados com sucesso")
except Exception as e:
    print(f"⚠️ Erro ao carregar routers: {e}")
    print("Sistema funcionará com funcionalidades básicas")

@app.get("/", response_class=HTMLResponse)
async def home(request: Request):
    """Página inicial"""
    return templates.TemplateResponse("home.html", {
        "request": request,
        "title": "RAG System - Sistema Inteligente"
    })

@app.get("/rag-frontend", response_class=HTMLResponse)
async def rag_frontend(request: Request):
    """RAG Console"""
    return templates.TemplateResponse("rag_frontend.html", {
        "request": request,
        "title": "RAG Console"
    })

@app.get("/precos", response_class=HTMLResponse)
async def pricing(request: Request):
    """Página de preços"""
    return templates.TemplateResponse("pricing.html", {
        "request": request,
        "title": "Planos e Preços"
    })

@app.get("/admin", response_class=HTMLResponse)
async def admin_dashboard(request: Request):
    """Admin Dashboard"""
    return templates.TemplateResponse("admin/dashboard.html", {
        "request": request,
        "title": "Admin Dashboard"
    })

@app.get("/health")
async def health_check():
    """Health check endpoint"""
    return {
        "status": "healthy",
        "service": "FastAPI RAG System",
        "version": "2.0.0",
        "timestamp": "2025-01-13T12:00:00Z"
    }

@app.get("/api/health")
async def api_health():
    """API health check"""
    return {
        "ok": True,
        "service": "FastAPI RAG API",
        "version": "2.0.0",
        "timestamp": "2025-01-13T12:00:00Z"
    }

if __name__ == "__main__":
    print("🚀 Iniciando FastAPI RAG System...")
    uvicorn.run(
        "fastapi_main:app",
        host="0.0.0.0",
        port=8000,
        reload=True,
        log_level="info"
    )
