"""
Admin Router
Sistema de administração para FastAPI
"""

from fastapi import APIRouter, HTTPException, Depends, status
from pydantic import BaseModel
from typing import Optional, List, Dict, Any
import psycopg2
from psycopg2.extras import RealDictCursor
from datetime import datetime

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

async def require_admin(current_user: dict = Depends(get_current_user)):
    """Verificar se usuário é admin"""
    if not current_user.get('is_admin', False):
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Acesso negado. Apenas administradores."
        )
    return current_user

@router.get("/dashboard")
async def admin_dashboard(current_user: dict = Depends(require_admin)):
    """Dashboard administrativo"""
    conn = get_db_connection()
    try:
        with conn.cursor(cursor_factory=RealDictCursor) as cursor:
            # Estatísticas gerais
            cursor.execute("SELECT COUNT(*) as total_users FROM users")
            total_users = cursor.fetchone()['total_users']
            
            cursor.execute("SELECT COUNT(*) as active_users FROM users WHERE email_verified_at IS NOT NULL")
            active_users = cursor.fetchone()['active_users']
            
            cursor.execute("SELECT COUNT(*) as total_documents FROM documents")
            total_documents = cursor.fetchone()['total_documents']
            
            cursor.execute("SELECT COUNT(*) as total_chunks FROM chunks")
            total_chunks = cursor.fetchone()['total_chunks']
            
            # Usuários recentes
            cursor.execute("""
                SELECT id, name, email, created_at, plan 
                FROM users 
                ORDER BY created_at DESC 
                LIMIT 10
            """)
            recent_users = [dict(row) for row in cursor.fetchall()]
            
            # Documentos recentes
            cursor.execute("""
                SELECT id, title, source, created_at, tenant_slug 
                FROM documents 
                ORDER BY created_at DESC 
                LIMIT 10
            """)
            recent_documents = [dict(row) for row in cursor.fetchall()]
            
            return {
                "success": True,
                "stats": {
                    "total_users": total_users,
                    "active_users": active_users,
                    "total_documents": total_documents,
                    "total_chunks": total_chunks
                },
                "recent_users": recent_users,
                "recent_documents": recent_documents
            }
    finally:
        conn.close()

@router.get("/users")
async def list_users(
    page: int = 1, 
    per_page: int = 20, 
    search: Optional[str] = None,
    current_user: dict = Depends(require_admin)
):
    """Listar usuários"""
    conn = get_db_connection()
    try:
        with conn.cursor(cursor_factory=RealDictCursor) as cursor:
            # Query base
            query = "SELECT * FROM users"
            params = []
            
            # Filtro de busca
            if search:
                query += " WHERE name ILIKE %s OR email ILIKE %s"
                params.extend([f"%{search}%", f"%{search}%"])
            
            # Paginação
            offset = (page - 1) * per_page
            query += " ORDER BY created_at DESC LIMIT %s OFFSET %s"
            params.extend([per_page, offset])
            
            cursor.execute(query, params)
            users = [dict(row) for row in cursor.fetchall()]
            
            # Total de usuários
            count_query = "SELECT COUNT(*) as total FROM users"
            if search:
                count_query += " WHERE name ILIKE %s OR email ILIKE %s"
                cursor.execute(count_query, [f"%{search}%", f"%{search}%"])
            else:
                cursor.execute(count_query)
            
            total = cursor.fetchone()['total']
            
            return {
                "success": True,
                "users": users,
                "pagination": {
                    "page": page,
                    "per_page": per_page,
                    "total": total,
                    "pages": (total + per_page - 1) // per_page
                }
            }
    finally:
        conn.close()

@router.get("/users/{user_id}")
async def get_user(user_id: int, current_user: dict = Depends(require_admin)):
    """Obter usuário específico"""
    conn = get_db_connection()
    try:
        with conn.cursor(cursor_factory=RealDictCursor) as cursor:
            cursor.execute("SELECT * FROM users WHERE id = %s", (user_id,))
            user = cursor.fetchone()
            
            if not user:
                raise HTTPException(
                    status_code=status.HTTP_404_NOT_FOUND,
                    detail="Usuário não encontrado"
                )
            
            # Documentos do usuário
            cursor.execute("""
                SELECT id, title, source, created_at 
                FROM documents 
                WHERE tenant_slug = %s 
                ORDER BY created_at DESC
            """, (f"user_{user_id}",))
            documents = [dict(row) for row in cursor.fetchall()]
            
            return {
                "success": True,
                "user": dict(user),
                "documents": documents
            }
    finally:
        conn.close()

@router.patch("/users/{user_id}/toggle-admin")
async def toggle_admin(user_id: int, current_user: dict = Depends(require_admin)):
    """Toggle admin status"""
    conn = get_db_connection()
    try:
        with conn.cursor() as cursor:
            cursor.execute("UPDATE users SET is_admin = NOT is_admin WHERE id = %s", (user_id,))
            conn.commit()
            
            cursor.execute("SELECT is_admin FROM users WHERE id = %s", (user_id,))
            is_admin = cursor.fetchone()[0]
            
            return {
                "success": True,
                "message": f"Status de admin {'ativado' if is_admin else 'desativado'} com sucesso",
                "is_admin": is_admin
            }
    finally:
        conn.close()

@router.get("/documents")
async def list_documents(
    page: int = 1,
    per_page: int = 20,
    search: Optional[str] = None,
    current_user: dict = Depends(require_admin)
):
    """Listar todos os documentos"""
    conn = get_db_connection()
    try:
        with conn.cursor(cursor_factory=RealDictCursor) as cursor:
            # Query base
            query = """
                SELECT d.*, u.name as user_name, u.email as user_email 
                FROM documents d 
                LEFT JOIN users u ON d.tenant_slug = CONCAT('user_', u.id)
            """
            params = []
            
            # Filtro de busca
            if search:
                query += " WHERE d.title ILIKE %s OR d.source ILIKE %s"
                params.extend([f"%{search}%", f"%{search}%"])
            
            # Paginação
            offset = (page - 1) * per_page
            query += " ORDER BY d.created_at DESC LIMIT %s OFFSET %s"
            params.extend([per_page, offset])
            
            cursor.execute(query, params)
            documents = [dict(row) for row in cursor.fetchall()]
            
            # Total de documentos
            count_query = "SELECT COUNT(*) as total FROM documents d"
            if search:
                count_query += " WHERE d.title ILIKE %s OR d.source ILIKE %s"
                cursor.execute(count_query, [f"%{search}%", f"%{search}%"])
            else:
                cursor.execute(count_query)
            
            total = cursor.fetchone()['total']
            
            return {
                "success": True,
                "documents": documents,
                "pagination": {
                    "page": page,
                    "per_page": per_page,
                    "total": total,
                    "pages": (total + per_page - 1) // per_page
                }
            }
    finally:
        conn.close()

@router.get("/documents/{document_id}")
async def get_document(document_id: int, current_user: dict = Depends(require_admin)):
    """Obter documento específico"""
    conn = get_db_connection()
    try:
        with conn.cursor(cursor_factory=RealDictCursor) as cursor:
            cursor.execute("""
                SELECT d.*, u.name as user_name, u.email as user_email 
                FROM documents d 
                LEFT JOIN users u ON d.tenant_slug = CONCAT('user_', u.id)
                WHERE d.id = %s
            """, (document_id,))
            document = cursor.fetchone()
            
            if not document:
                raise HTTPException(
                    status_code=status.HTTP_404_NOT_FOUND,
                    detail="Documento não encontrado"
                )
            
            # Chunks do documento
            cursor.execute("""
                SELECT id, content, chunk_index, metadata 
                FROM chunks 
                WHERE document_id = %s 
                ORDER BY chunk_index
            """, (document_id,))
            chunks = [dict(row) for row in cursor.fetchall()]
            
            return {
                "success": True,
                "document": dict(document),
                "chunks": chunks
            }
    finally:
        conn.close()

@router.delete("/documents/{document_id}")
async def delete_document(document_id: int, current_user: dict = Depends(require_admin)):
    """Deletar documento"""
    conn = get_db_connection()
    try:
        with conn.cursor() as cursor:
            # Deletar chunks
            cursor.execute("DELETE FROM chunks WHERE document_id = %s", (document_id,))
            
            # Deletar feedback
            cursor.execute("DELETE FROM rag_feedbacks WHERE document_id = %s", (document_id,))
            
            # Deletar documento
            cursor.execute("DELETE FROM documents WHERE id = %s", (document_id,))
            
            conn.commit()
            
            return {
                "success": True,
                "message": "Documento deletado com sucesso",
                "document_id": document_id
            }
    finally:
        conn.close()

@router.get("/stats")
async def admin_stats(current_user: dict = Depends(require_admin)):
    """Estatísticas administrativas"""
    conn = get_db_connection()
    try:
        with conn.cursor(cursor_factory=RealDictCursor) as cursor:
            # Estatísticas gerais
            stats = {}
            
            # Usuários
            cursor.execute("SELECT COUNT(*) as total FROM users")
            stats['total_users'] = cursor.fetchone()['total']
            
            cursor.execute("SELECT COUNT(*) as total FROM users WHERE created_at >= CURRENT_DATE - INTERVAL '30 days'")
            stats['users_last_30_days'] = cursor.fetchone()['total']
            
            # Documentos
            cursor.execute("SELECT COUNT(*) as total FROM documents")
            stats['total_documents'] = cursor.fetchone()['total']
            
            cursor.execute("SELECT COUNT(*) as total FROM documents WHERE created_at >= CURRENT_DATE - INTERVAL '30 days'")
            stats['documents_last_30_days'] = cursor.fetchone()['total']
            
            # Chunks
            cursor.execute("SELECT COUNT(*) as total FROM chunks")
            stats['total_chunks'] = cursor.fetchone()['total']
            
            # Planos
            cursor.execute("SELECT plan, COUNT(*) as count FROM users GROUP BY plan")
            stats['users_by_plan'] = [dict(row) for row in cursor.fetchall()]
            
            return {
                "success": True,
                "stats": stats
            }
    finally:
        conn.close()
