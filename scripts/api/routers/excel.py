"""
Excel Structured API endpoints
Integrates Excel structured processing Python scripts
"""

import sys
import os
import json
import subprocess
from pathlib import Path
from typing import Optional, Dict, Any
from fastapi import APIRouter, HTTPException, Depends, Request
from fastapi.responses import JSONResponse
import structlog

logger = structlog.get_logger(__name__)

# Add document_extraction directory to path
current_dir = Path(__file__).parent.parent
document_extraction_dir = current_dir.parent / "document_extraction"
sys.path.insert(0, str(document_extraction_dir))

from core.security import get_api_key_from_header

router = APIRouter(
    prefix="/api/excel",
    tags=["excel"],
)

# Initialize DatabaseManager
try:
    rag_search_dir = current_dir.parent / "rag_search"
    sys.path.insert(0, str(rag_search_dir))
    from database import DatabaseManager
    db_manager = DatabaseManager()
except ImportError:
    import psycopg2
    db_manager = None

async def get_user_id_from_api_key(api_key: str) -> Optional[int]:
    """Get user ID from API key"""
    try:
        if db_manager:
            with db_manager.get_connection() as conn:
                with conn.cursor() as cursor:
                    cursor.execute("SELECT id FROM users WHERE api_key = %s", (api_key,))
                    result = cursor.fetchone()
                    if result:
                        return result[0]
        else:
            # Fallback: direct connection
            db_config = {
                "host": os.getenv("DB_HOST", "localhost"),
                "database": os.getenv("DB_DATABASE", "laravel_rag"),
                "user": os.getenv("DB_USERNAME", "postgres"),
                "password": os.getenv("DB_PASSWORD", ""),
                "port": os.getenv("DB_PORT", "5432")
            }
            conn = psycopg2.connect(**db_config)
            with conn.cursor() as cursor:
                cursor.execute("SELECT id FROM users WHERE api_key = %s", (api_key,))
                result = cursor.fetchone()
                if result:
                    return result[0]
            conn.close()
        return None
    except Exception as e:
        logger.error("Error getting user ID from API key", error=str(e))
        raise HTTPException(status_code=500, detail="Database error")


@router.post("/query")
async def excel_query(
    request: Request,
    api_key: str = Depends(get_api_key_from_header)
):
    """
    Query Excel document using structured data
    POST /api/excel/query
    Body: {
        "document_id": 123,
        "query": "SUM of column X where Y > 10"
    }
    """
    try:
        data = await request.json()
        document_id = data.get('document_id')
        query = data.get('query')
        
        if not document_id or not query:
            raise HTTPException(status_code=422, detail="document_id and query are required")
        
        # Get user for validation
        user_id = await get_user_id_from_api_key(api_key)
        if not user_id:
            raise HTTPException(status_code=401, detail="Invalid API Key or User not found")
        
        # Get document metadata
        db_config = {
            "host": os.getenv("DB_HOST", "localhost"),
            "database": os.getenv("DB_DATABASE", "laravel_rag"),
            "user": os.getenv("DB_USERNAME", "postgres"),
            "password": os.getenv("DB_PASSWORD", ""),
            "port": os.getenv("DB_PORT", "5432")
        }
        
        import psycopg2
        from psycopg2.extras import RealDictCursor
        conn = psycopg2.connect(**db_config)
        cursor = conn.cursor(cursor_factory=RealDictCursor)
        
        cursor.execute("SELECT title, metadata FROM documents WHERE id = %s AND tenant_slug = %s", 
                      (document_id, f"user_{user_id}"))
        doc = cursor.fetchone()
        
        if not doc:
            conn.close()
            raise HTTPException(status_code=404, detail="Document not found or access denied")
        
        # metadata may already be a dict (from RealDictCursor) or JSON string
        metadata_raw = doc.get('metadata') if isinstance(doc, dict) else doc[1]
        
        # Handle different types of metadata
        if metadata_raw is None:
            metadata = {}
        elif isinstance(metadata_raw, dict):
            metadata = metadata_raw.copy()
        elif isinstance(metadata_raw, str):
            try:
                metadata = json.loads(metadata_raw) if metadata_raw.strip() else {}
            except (json.JSONDecodeError, TypeError, ValueError):
                metadata = {}
        else:
            # Try to convert to dict if possible
            try:
                metadata = dict(metadata_raw) if hasattr(metadata_raw, '__iter__') else {}
            except (TypeError, ValueError):
                metadata = {}
        
        # Check if document has structured data
        if 'structured_data' not in metadata:
            conn.close()
            # Get title from dict or tuple
            doc_title = doc.get('title') if isinstance(doc, dict) else doc[0]
            
            # Return 200 with error message instead of 400, so frontend can handle gracefully
            return JSONResponse(content={
                "success": False,
                "document_id": document_id,
                "document_title": doc_title,
                "error": "Document does not have structured data. Only Excel files with structured extraction are supported.",
                "has_structured_data": False
            }, status_code=200)
        
        structured_data = metadata['structured_data']
        conn.close()
        
        # Perform simple query logic (can be enhanced later)
        # For now, return a basic response
        # Get title from dict or tuple
        doc_title = doc.get('title') if isinstance(doc, dict) else doc[0]
        
        result = {
            "success": True,
            "document_id": document_id,
            "document_title": doc_title,
            "query": query,
            "result": "Structured query processing - placeholder implementation",
            "data": structured_data
        }
        
        return JSONResponse(content=result)
        
    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Excel query error: {e}")
        raise HTTPException(status_code=500, detail=f"Error: {str(e)}")


@router.get("/{document_id}/structure")
async def get_excel_structure(
    document_id: int,
    api_key: str = Depends(get_api_key_from_header)
):
    """
    Get structured metadata for a document
    GET /api/excel/{document_id}/structure
    """
    try:
        # Get user for validation
        user_id = await get_user_id_from_api_key(api_key)
        if not user_id:
            raise HTTPException(status_code=401, detail="Invalid API Key or User not found")
        
        # Get document metadata
        db_config = {
            "host": os.getenv("DB_HOST", "localhost"),
            "database": os.getenv("DB_DATABASE", "laravel_rag"),
            "user": os.getenv("DB_USERNAME", "postgres"),
            "password": os.getenv("DB_PASSWORD", ""),
            "port": os.getenv("DB_PORT", "5432")
        }
        
        import psycopg2
        from psycopg2.extras import RealDictCursor
        conn = psycopg2.connect(**db_config)
        cursor = conn.cursor(cursor_factory=RealDictCursor)
        
        cursor.execute("SELECT id, title, metadata FROM documents WHERE id = %s AND tenant_slug = %s",
                      (document_id, f"user_{user_id}"))
        doc = cursor.fetchone()
        
        if not doc:
            conn.close()
            raise HTTPException(status_code=404, detail="Document not found or access denied")
        
        # metadata may already be a dict (from RealDictCursor) or JSON string
        metadata_raw = doc.get('metadata') if isinstance(doc, dict) else doc[2]
        
        # Handle different types of metadata
        if metadata_raw is None:
            metadata = {}
        elif isinstance(metadata_raw, dict):
            metadata = metadata_raw.copy()
        elif isinstance(metadata_raw, str):
            try:
                metadata = json.loads(metadata_raw) if metadata_raw and metadata_raw.strip() else {}
            except (json.JSONDecodeError, TypeError, ValueError):
                metadata = {}
        else:
            # Try to convert to dict if possible
            try:
                metadata = dict(metadata_raw) if hasattr(metadata_raw, '__iter__') else {}
            except (TypeError, ValueError):
                metadata = {}
        conn.close()
        
        # Check if document has structured data
        if 'structured_data' not in metadata:
            # Get title from dict or tuple
            doc_title = doc.get('title') if isinstance(doc, dict) else doc[1]
            
            # Return 200 with error message instead of 400, so frontend can handle gracefully
            return JSONResponse(content={
                "success": False,
                "document_id": document_id,
                "document_title": doc_title,
                "error": "Document does not have structured data",
                "has_structured_data": False
            }, status_code=200)
        
        structured_data = metadata['structured_data']
        
        # Get title from dict or tuple
        doc_title = doc.get('title') if isinstance(doc, dict) else doc[1]
        
        return JSONResponse(content={
            "success": True,
            "document_id": document_id,
            "document_title": doc_title,
            "structured_data": structured_data
        })
        
    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Excel structure error: {e}")
        raise HTTPException(status_code=500, detail=f"Error: {str(e)}")

