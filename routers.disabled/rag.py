"""
RAG Router
Sistema RAG completo para FastAPI
"""

from fastapi import APIRouter, HTTPException, Depends, status
from pydantic import BaseModel
from typing import Optional, List, Dict, Any
import json
import sys
import os
from pathlib import Path

# Adicionar scripts ao path
sys.path.append(str(Path(__file__).parent.parent / "scripts"))

# Importar módulos RAG
try:
    from rag_search.rag_search import RagSearch
    from rag_search.config import RagConfig
    RAG_AVAILABLE = True
except ImportError:
    RAG_AVAILABLE = False
    print("⚠️ Módulos RAG não disponíveis")

from routers.auth import get_current_user

router = APIRouter()

class RagQueryRequest(BaseModel):
    query: str
    document_id: Optional[int] = None
    top_k: int = 5
    threshold: float = 0.3
    mode: str = "auto"
    format: str = "plain"
    strictness: int = 2
    include_answer: bool = True
    length: str = "auto"
    citations: int = 0

class RagQueryResponse(BaseModel):
    success: bool
    query: str
    chunks: List[Dict[str, Any]]
    answer: Optional[str]
    metadata: Dict[str, Any]
    mode_used: str
    processing_time: float

class DocumentIngestRequest(BaseModel):
    title: str
    content: str
    source: str = "upload"
    metadata: Optional[Dict[str, Any]] = None

class DocumentIngestResponse(BaseModel):
    success: bool
    document_id: int
    chunks_created: int
    message: str

# Inicializar RAG
rag_search = None
if RAG_AVAILABLE:
    try:
        config = RagConfig()
        rag_search = RagSearch(config)
        print("✅ RAG Search inicializado com sucesso")
    except Exception as e:
        print(f"❌ Erro ao inicializar RAG: {e}")

@router.get("/health")
async def rag_health():
    """Health check do RAG"""
    return {
        "status": "healthy" if RAG_AVAILABLE else "unavailable",
        "rag_available": RAG_AVAILABLE,
        "service": "FastAPI RAG"
    }

@router.post("/query", response_model=RagQueryResponse)
async def rag_query(request: RagQueryRequest, current_user: dict = Depends(get_current_user)):
    """Query RAG"""
    if not RAG_AVAILABLE or not rag_search:
        raise HTTPException(
            status_code=status.HTTP_503_SERVICE_UNAVAILABLE,
            detail="Serviço RAG não disponível"
        )
    
    try:
        import time
        start_time = time.time()
        
        # Executar busca RAG
        result = rag_search.search(
            query=request.query,
            document_id=request.document_id,
            top_k=request.top_k,
            threshold=request.threshold,
            mode=request.mode,
            format=request.format,
            strictness=request.strictness,
            include_answer=request.include_answer,
            length=request.length,
            citations=request.citations
        )
        
        processing_time = time.time() - start_time
        
        return RagQueryResponse(
            success=True,
            query=request.query,
            chunks=result.get('chunks', []),
            answer=result.get('answer'),
            metadata=result.get('metadata', {}),
            mode_used=result.get('mode_used', request.mode),
            processing_time=processing_time
        )
        
    except Exception as e:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Erro na busca RAG: {str(e)}"
        )

@router.post("/ingest", response_model=DocumentIngestResponse)
async def ingest_document(request: DocumentIngestRequest, current_user: dict = Depends(get_current_user)):
    """Ingerir documento"""
    if not RAG_AVAILABLE or not rag_search:
        raise HTTPException(
            status_code=status.HTTP_503_SERVICE_UNAVAILABLE,
            detail="Serviço RAG não disponível"
        )
    
    try:
        # Ingerir documento
        result = rag_search.ingest_document(
            title=request.title,
            content=request.content,
            source=request.source,
            metadata=request.metadata or {},
            user_id=current_user['id']
        )
        
        return DocumentIngestResponse(
            success=True,
            document_id=result['document_id'],
            chunks_created=result['chunks_created'],
            message="Documento ingerido com sucesso"
        )
        
    except Exception as e:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Erro ao ingerir documento: {str(e)}"
        )

@router.get("/documents")
async def list_documents(current_user: dict = Depends(get_current_user)):
    """Listar documentos do usuário"""
    if not RAG_AVAILABLE or not rag_search:
        raise HTTPException(
            status_code=status.HTTP_503_SERVICE_UNAVAILABLE,
            detail="Serviço RAG não disponível"
        )
    
    try:
        documents = rag_search.list_user_documents(current_user['id'])
        return {
            "success": True,
            "documents": documents
        }
    except Exception as e:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Erro ao listar documentos: {str(e)}"
        )

@router.get("/documents/{document_id}")
async def get_document(document_id: int, current_user: dict = Depends(get_current_user)):
    """Obter documento específico"""
    if not RAG_AVAILABLE or not rag_search:
        raise HTTPException(
            status_code=status.HTTP_503_SERVICE_UNAVAILABLE,
            detail="Serviço RAG não disponível"
        )
    
    try:
        document = rag_search.get_document(document_id, current_user['id'])
        if not document:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail="Documento não encontrado"
            )
        
        return {
            "success": True,
            "document": document
        }
    except Exception as e:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Erro ao obter documento: {str(e)}"
        )

@router.delete("/documents/{document_id}")
async def delete_document(document_id: int, current_user: dict = Depends(get_current_user)):
    """Deletar documento"""
    if not RAG_AVAILABLE or not rag_search:
        raise HTTPException(
            status_code=status.HTTP_503_SERVICE_UNAVAILABLE,
            detail="Serviço RAG não disponível"
        )
    
    try:
        result = rag_search.delete_document(document_id, current_user['id'])
        return {
            "success": True,
            "message": "Documento deletado com sucesso",
            "document_id": document_id
        }
    except Exception as e:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Erro ao deletar documento: {str(e)}"
        )

@router.get("/stats")
async def rag_stats(current_user: dict = Depends(get_current_user)):
    """Estatísticas RAG"""
    if not RAG_AVAILABLE or not rag_search:
        raise HTTPException(
            status_code=status.HTTP_503_SERVICE_UNAVAILABLE,
            detail="Serviço RAG não disponível"
        )
    
    try:
        stats = rag_search.get_user_stats(current_user['id'])
        return {
            "success": True,
            "stats": stats
        }
    except Exception as e:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Erro ao obter estatísticas: {str(e)}"
        )
