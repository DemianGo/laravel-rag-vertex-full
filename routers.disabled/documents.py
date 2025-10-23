"""
Documents Router
Sistema de gerenciamento de documentos para FastAPI
"""

from fastapi import APIRouter, HTTPException, Depends, status, UploadFile, File
from pydantic import BaseModel
from typing import Optional, List, Dict, Any
import psycopg2
from psycopg2.extras import RealDictCursor
from datetime import datetime
import json

from routers.auth import get_current_user

router = APIRouter()

# Configuração do banco
DB_CONFIG = {
    "host": "127.0.0.1",
    "port": "5432",
    "database": "laravel_rag",
    "user": "postgres",
    "password": "postgres"
}

def get_db_connection():
    """Obter conexão com o banco"""
    return psycopg2.connect(**DB_CONFIG)

class DocumentUploadResponse(BaseModel):
    success: bool
    document_id: int
    message: str

@router.get("/list")
async def list_documents(current_user: dict = Depends(get_current_user)):
    """Listar documentos do usuário"""
    conn = get_db_connection()
    try:
        with conn.cursor(cursor_factory=RealDictCursor) as cursor:
            cursor.execute(
                """
                SELECT id, title, source, created_at, metadata 
                FROM documents 
                WHERE tenant_slug = %s 
                ORDER BY created_at DESC
                """,
                (f"user_{current_user['id']}",)
            )
            documents = [dict(row) for row in cursor.fetchall()]
            
            return {
                "success": True,
                "documents": documents
            }
    finally:
        conn.close()

@router.get("/{document_id}")
async def get_document(document_id: int, current_user: dict = Depends(get_current_user)):
    """Obter documento específico"""
    conn = get_db_connection()
    try:
        with conn.cursor(cursor_factory=RealDictCursor) as cursor:
            cursor.execute(
                """
                SELECT * FROM documents 
                WHERE id = %s AND tenant_slug = %s
                """,
                (document_id, f"user_{current_user['id']}")
            )
            document = cursor.fetchone()
            
            if not document:
                raise HTTPException(
                    status_code=status.HTTP_404_NOT_FOUND,
                    detail="Documento não encontrado"
                )
            
            return {
                "success": True,
                "document": dict(document)
            }
    finally:
        conn.close()

@router.get("/{document_id}/chunks")
async def get_document_chunks(document_id: int, current_user: dict = Depends(get_current_user)):
    """Obter chunks do documento"""
    conn = get_db_connection()
    try:
        with conn.cursor(cursor_factory=RealDictCursor) as cursor:
            # Verificar se o documento pertence ao usuário
            cursor.execute(
                "SELECT id FROM documents WHERE id = %s AND tenant_slug = %s",
                (document_id, f"user_{current_user['id']}")
            )
            if not cursor.fetchone():
                raise HTTPException(
                    status_code=status.HTTP_404_NOT_FOUND,
                    detail="Documento não encontrado"
                )
            
            # Buscar chunks
            cursor.execute(
                """
                SELECT id, content, chunk_index, metadata 
                FROM chunks 
                WHERE document_id = %s 
                ORDER BY chunk_index
                """,
                (document_id,)
            )
            chunks = [dict(row) for row in cursor.fetchall()]
            
            return {
                "success": True,
                "chunks": chunks
            }
    finally:
        conn.close()

@router.post("/upload", response_model=DocumentUploadResponse)
async def upload_document(
    file: UploadFile = File(...),
    title: Optional[str] = None,
    current_user: dict = Depends(get_current_user)
):
    """Upload de documento"""
    try:
        # Ler conteúdo do arquivo
        content = await file.read()
        content_str = content.decode('utf-8')
        
        # Usar título do arquivo se não fornecido
        if not title:
            title = file.filename or "Documento sem nome"
        
        # Salvar documento no banco
        conn = get_db_connection()
        try:
            with conn.cursor(cursor_factory=RealDictCursor) as cursor:
                cursor.execute(
                    """
                    INSERT INTO documents (title, source, uri, tenant_slug, metadata, created_at, updated_at)
                    VALUES (%s, %s, %s, %s, %s, %s, %s)
                    RETURNING id
                    """,
                    (
                        title,
                        "upload",
                        file.filename,
                        f"user_{current_user['id']}",
                        json.dumps({"file_size": len(content), "content_type": file.content_type}),
                        datetime.utcnow(),
                        datetime.utcnow()
                    )
                )
                document_id = cursor.fetchone()['id']
                
                # Criar chunks simples (dividir por linhas)
                lines = content_str.split('\n')
                for i, line in enumerate(lines):
                    if line.strip():
                        cursor.execute(
                            """
                            INSERT INTO chunks (document_id, content, chunk_index, metadata, created_at, updated_at)
                            VALUES (%s, %s, %s, %s, %s, %s)
                            """,
                            (
                                document_id,
                                line.strip(),
                                i,
                                json.dumps({"line_number": i + 1}),
                                datetime.utcnow(),
                                datetime.utcnow()
                            )
                        )
                
                conn.commit()
                
                return DocumentUploadResponse(
                    success=True,
                    document_id=document_id,
                    message="Documento enviado e processado com sucesso"
                )
        finally:
            conn.close()
            
    except Exception as e:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Erro no upload: {str(e)}"
        )

@router.delete("/{document_id}")
async def delete_document(document_id: int, current_user: dict = Depends(get_current_user)):
    """Deletar documento"""
    conn = get_db_connection()
    try:
        with conn.cursor() as cursor:
            # Verificar se o documento pertence ao usuário
            cursor.execute(
                "SELECT id FROM documents WHERE id = %s AND tenant_slug = %s",
                (document_id, f"user_{current_user['id']}")
            )
            if not cursor.fetchone():
                raise HTTPException(
                    status_code=status.HTTP_404_NOT_FOUND,
                    detail="Documento não encontrado"
                )
            
            # Deletar chunks
            cursor.execute("DELETE FROM chunks WHERE document_id = %s", (document_id,))
            
            # Deletar feedback
            cursor.execute("DELETE FROM rag_feedbacks WHERE document_id = %s", (document_id,))
            
            # Deletar documento
            cursor.execute("DELETE FROM documents WHERE id = %s", (document_id,))
            
            conn.commit()
            
            return {
                "success": True,
                "message": "Documento deletado com sucesso"
            }
    finally:
        conn.close()

@router.get("/stats")
async def document_stats(current_user: dict = Depends(get_current_user)):
    """Estatísticas de documentos"""
    conn = get_db_connection()
    try:
        with conn.cursor(cursor_factory=RealDictCursor) as cursor:
            # Total de documentos
            cursor.execute(
                "SELECT COUNT(*) as total FROM documents WHERE tenant_slug = %s",
                (f"user_{current_user['id']}",)
            )
            total_documents = cursor.fetchone()['total']
            
            # Total de chunks
            cursor.execute(
                """
                SELECT COUNT(*) as total 
                FROM chunks c 
                JOIN documents d ON c.document_id = d.id 
                WHERE d.tenant_slug = %s
                """,
                (f"user_{current_user['id']}",)
            )
            total_chunks = cursor.fetchone()['total']
            
            # Documentos por tipo
            cursor.execute(
                """
                SELECT source, COUNT(*) as count 
                FROM documents 
                WHERE tenant_slug = %s 
                GROUP BY source
                """,
                (f"user_{current_user['id']}",)
            )
            by_source = [dict(row) for row in cursor.fetchall()]
            
            return {
                "success": True,
                "stats": {
                    "total_documents": total_documents,
                    "total_chunks": total_chunks,
                    "by_source": by_source
                }
            }
    finally:
        conn.close()
