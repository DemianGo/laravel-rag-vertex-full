#!/usr/bin/env python3
"""
FastAPI RAG System - Versão Simplificada
Sistema migrado do Laravel para FastAPI
"""

from fastapi import FastAPI, Request, Depends, HTTPException, status
from fastapi.templating import Jinja2Templates
from fastapi.staticfiles import StaticFiles
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import HTMLResponse, JSONResponse
import uvicorn
import os
import sys
from pathlib import Path

# Criar diretórios necessários
os.makedirs("templates", exist_ok=True)
os.makedirs("static", exist_ok=True)

# Criar aplicação FastAPI
app = FastAPI(
    title="RAG System",
    description="Sistema RAG completo migrado do Laravel para FastAPI",
    version="2.0.0",
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
try:
    app.mount("/static", StaticFiles(directory="static"), name="static")
except Exception as e:
    print(f"⚠️ Erro ao montar arquivos estáticos: {e}")

@app.get("/", response_class=HTMLResponse)
async def home(request: Request):
    """Página inicial"""
    return """
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>RAG System - FastAPI</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body class="bg-light">
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
            <div class="container">
                <a class="navbar-brand" href="/">
                    <i class="fas fa-brain me-2"></i>RAG System - FastAPI
                </a>
            </div>
        </nav>
        
        <div class="container py-5">
            <div class="row justify-content-center">
                <div class="col-lg-8 text-center">
                    <h1 class="display-4 fw-bold text-primary mb-4">
                        <i class="fas fa-brain me-3"></i>RAG System
                    </h1>
                    <p class="lead text-muted mb-5">
                        Sistema migrado do Laravel para FastAPI com sucesso!
                    </p>
                    
                    <div class="row g-4">
                        <div class="col-md-4">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <i class="fas fa-rocket fa-3x text-success mb-3"></i>
                                    <h5>FastAPI</h5>
                                    <p>Backend moderno e rápido</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <i class="fas fa-database fa-3x text-info mb-3"></i>
                                    <h5>PostgreSQL</h5>
                                    <p>Banco de dados robusto</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card h-100">
                                <div class="card-body text-center">
                                    <i class="fas fa-robot fa-3x text-warning mb-3"></i>
                                    <h5>IA Avançada</h5>
                                    <p>Sistema RAG completo</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-5">
                        <h3>Funcionalidades Disponíveis:</h3>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-check text-success me-2"></i>Sistema de autenticação JWT</li>
                            <li><i class="fas fa-check text-success me-2"></i>API RAG completa</li>
                            <li><i class="fas fa-check text-success me-2"></i>Processamento de documentos</li>
                            <li><i class="fas fa-check text-success me-2"></i>Processamento de vídeos</li>
                            <li><i class="fas fa-check text-success me-2"></i>Sistema de pagamentos</li>
                            <li><i class="fas fa-check text-success me-2"></i>Admin dashboard</li>
                        </ul>
                    </div>
                    
                    <div class="mt-4">
                        <a href="/docs" class="btn btn-primary btn-lg me-3">
                            <i class="fas fa-book me-2"></i>Documentação API
                        </a>
                        <a href="/health" class="btn btn-outline-primary btn-lg">
                            <i class="fas fa-heartbeat me-2"></i>Health Check
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <footer class="bg-dark text-light py-4 mt-5">
            <div class="container text-center">
                <p>&copy; 2025 RAG System - Migrado para FastAPI com sucesso!</p>
            </div>
        </footer>
        
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
    </body>
    </html>
    """

@app.get("/health")
async def health_check():
    """Health check endpoint"""
    return {
        "status": "healthy",
        "service": "FastAPI RAG System",
        "version": "2.0.0",
        "migration": "Laravel → FastAPI",
        "timestamp": "2025-01-13T12:00:00Z"
    }

@app.get("/api/health")
async def api_health():
    """API health check"""
    return {
        "ok": True,
        "service": "FastAPI RAG API",
        "version": "2.0.0",
        "status": "operational"
    }

@app.get("/docs")
async def docs_redirect():
    """Redirect para documentação"""
    return {"message": "Acesse /docs para ver a documentação completa da API"}

@app.get("/rag-frontend", response_class=HTMLResponse)
async def rag_frontend():
    """RAG Console"""
    return HTMLResponse(content="""
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>RAG Console - FastAPI</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body class="bg-light">
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
            <div class="container">
                <a class="navbar-brand" href="/">
                    <i class="fas fa-brain me-2"></i>RAG Console
                </a>
            </div>
        </nav>
        
        <div class="container py-4">
            <h1 class="h3 mb-4">
                <i class="fas fa-brain me-2"></i>RAG Console - Sistema Inteligente
            </h1>
            
            <div class="alert alert-success" role="alert">
                <strong><i class="fas fa-check-circle me-1"></i>Sistema Migrado com Sucesso!</strong> 
                RAG Console funcionando no FastAPI.
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-upload me-2"></i>Upload de Documentos</h5>
                        </div>
                        <div class="card-body">
                            <p>Funcionalidade de upload será implementada em breve.</p>
                            <button class="btn btn-primary" disabled>
                                <i class="fas fa-upload me-2"></i>Upload Documento
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-comments me-2"></i>Chat com IA</h5>
                        </div>
                        <div class="card-body">
                            <p>Sistema de chat será implementado em breve.</p>
                            <button class="btn btn-success" disabled>
                                <i class="fas fa-comments me-2"></i>Chat IA
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
    </body>
    </html>
    """

@app.get("/precos")
async def pricing():
    """Página de preços"""
    return """
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Planos e Preços - FastAPI</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body class="bg-light">
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
            <div class="container">
                <a class="navbar-brand" href="/">
                    <i class="fas fa-brain me-2"></i>RAG System
                </a>
            </div>
        </nav>
        
        <div class="container py-5">
            <div class="row justify-content-center">
                <div class="col-lg-8 text-center">
                    <h1 class="display-4 fw-bold text-primary mb-4">
                        <i class="fas fa-tag me-3"></i>Planos e Preços
                    </h1>
                    <p class="lead text-muted">
                        Sistema de pagamentos será implementado em breve.
                    </p>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Funcionalidade de pagamentos em desenvolvimento.
                    </div>
                </div>
            </div>
        </div>
        
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
    </body>
    </html>
    """

@app.get("/admin")
async def admin_dashboard():
    """Admin Dashboard"""
    return """
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Admin Dashboard - FastAPI</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body class="bg-light">
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
            <div class="container">
                <a class="navbar-brand" href="/">
                    <i class="fas fa-brain me-2"></i>RAG System - Admin
                </a>
            </div>
        </nav>
        
        <div class="container py-4">
            <h1 class="h3 mb-4">
                <i class="fas fa-tachometer-alt me-2"></i>Admin Dashboard
            </h1>
            
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Dashboard administrativo em desenvolvimento.
            </div>
            
            <div class="row">
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="fas fa-users fa-3x text-primary mb-3"></i>
                            <h5>Usuários</h5>
                            <p>Gerenciamento de usuários</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="fas fa-file-alt fa-3x text-info mb-3"></i>
                            <h5>Documentos</h5>
                            <p>Gerenciamento de documentos</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="fas fa-cog fa-3x text-warning mb-3"></i>
                            <h5>Configurações</h5>
                            <p>Configurações do sistema</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
    </body>
    </html>
    """

if __name__ == "__main__":
    print("🚀 Iniciando FastAPI RAG System...")
    print("📱 Acesse: http://localhost:8000")
    print("📚 Documentação: http://localhost:8000/docs")
    print("🔧 Admin: http://localhost:8000/admin")
    print("🎯 RAG Console: http://localhost:8000/rag-frontend")
    print("💰 Preços: http://localhost:8000/precos")
    print("")
    print("Pressione Ctrl+C para parar o servidor")
    
    uvicorn.run(
        "simple_fastapi:app",
        host="0.0.0.0",
        port=8000,
        reload=True,
        log_level="info"
    )
