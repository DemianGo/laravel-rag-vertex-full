import sys
import os
from pathlib import Path
from typing import Dict, Any, Optional
from fastapi import APIRouter, HTTPException, Depends, Request
from fastapi.responses import JSONResponse
import structlog

logger = structlog.get_logger(__name__)

# Add the rag_search directory to the path to import database module
current_dir = Path(__file__).parent
rag_search_dir = current_dir.parent.parent / "rag_search"
sys.path.insert(0, str(rag_search_dir))

from database import DatabaseManager
from config import Config
from core.security import get_api_key_from_header
import psycopg2.extras

# Set the cursor factory
Config.DB_CURSOR_FACTORY = psycopg2.extras.RealDictCursor

router = APIRouter(
    prefix="/user",
    tags=["user"],
)

# Initialize DatabaseManager
db_manager = DatabaseManager()

async def get_user_id_from_api_key(api_key: str) -> Optional[int]:
    """Fetches user ID from the database based on the API key."""
    try:
        with db_manager.get_connection() as conn:
            with conn.cursor() as cursor:
                cursor.execute("SELECT id FROM users WHERE api_key = %s", (api_key,))
                result = cursor.fetchone()
                if result:
                    return result[0]
        return None
    except Exception as e:
        logger.error("Error getting user ID from API key", error=str(e))
        raise HTTPException(status_code=500, detail="Database error")

@router.get("/info")
async def get_user_info(request: Request, api_key: str = Depends(get_api_key_from_header)):
    """
    Get user information including plan, tokens, and documents usage.
    Requires valid API key in headers.
    """
    user_id = await get_user_id_from_api_key(api_key)
    if not user_id:
        raise HTTPException(status_code=401, detail="Invalid API Key or User not found")

    try:
        with db_manager.get_connection() as conn:
            with conn.cursor() as cursor:
                cursor.execute(
                    """
                    SELECT id, name, email, plan, tokens_used, tokens_limit, 
                           documents_used, documents_limit, api_key_created_at, api_key_last_used_at,
                           created_at, updated_at
                    FROM users 
                    WHERE id = %s
                    """,
                    (user_id,)
                )
                user_data = cursor.fetchone()

                if not user_data:
                    raise HTTPException(status_code=404, detail="User not found")

                # Convert tuple to dict for JSON serialization
                user_dict = {
                    "id": user_data[0],
                    "name": user_data[1],
                    "email": user_data[2],
                    "plan": user_data[3],
                    "tokens_used": user_data[4],
                    "tokens_limit": user_data[5],
                    "documents_used": user_data[6],
                    "documents_limit": user_data[7],
                    "api_key_created_at": user_data[8].isoformat() if user_data[8] else None,
                    "api_key_last_used_at": user_data[9].isoformat() if user_data[9] else None,
                    "created_at": user_data[10].isoformat() if user_data[10] else None,
                    "updated_at": user_data[11].isoformat() if user_data[11] else None
                }

                # Generate a mock CSRF token for compatibility with frontend
                # In a real Laravel app, this would come from Laravel's session
                csrf_token = f"csrf_{user_id}_{api_key[:8]}"

                return JSONResponse(content={
                    "user": user_dict,
                    "csrf_token": csrf_token
                })
    except HTTPException:
        raise # Re-raise HTTPExceptions
    except Exception as e:
        logger.error("Error fetching user info from database", user_id=user_id, error=str(e))
        raise HTTPException(status_code=500, detail="Internal server error")

@router.get("/docs/list")
async def get_documents_list(request: Request, api_key: str = Depends(get_api_key_from_header)):
    """
    Get list of documents for the authenticated user.
    Requires valid API key in headers.
    """
    user_id = await get_user_id_from_api_key(api_key)
    if not user_id:
        raise HTTPException(status_code=401, detail="Invalid API Key or User not found")

    try:
        with db_manager.get_connection() as conn:
            with conn.cursor() as cursor:
                cursor.execute(
                    """
                    SELECT id, title, source, uri, tenant_slug, metadata, created_at, updated_at
                    FROM documents 
                    WHERE tenant_slug = %s
                    ORDER BY created_at DESC
                    """,
                    (f"user_{user_id}",)
                )
                documents = cursor.fetchall()

                # Convert to list of dicts
                documents_list = []
                for doc in documents:
                    documents_list.append({
                        "id": doc[0],
                        "title": doc[1],
                        "source": doc[2],
                        "uri": doc[3],
                        "tenant_slug": doc[4],
                        "metadata": doc[5] if doc[5] else {},
                        "created_at": doc[6].isoformat() if doc[6] else None,
                        "updated_at": doc[7].isoformat() if doc[7] else None
                    })

                return JSONResponse(content={
                    "documents": documents_list,
                    "total": len(documents_list)
                })
    except HTTPException:
        raise # Re-raise HTTPExceptions
    except Exception as e:
        logger.error("Error fetching documents from database", user_id=user_id, error=str(e))
        raise HTTPException(status_code=500, detail="Internal server error")

@router.get("/test")
async def test_endpoint(request: Request):
    """Test endpoint to verify CORS and basic functionality."""
    api_key = request.headers.get("X-API-Key")
    return {
        "status": "ok", 
        "message": "User router is healthy",
        "api_key_received": bool(api_key),
        "api_key_prefix": api_key[:10] if api_key else None
    }

@router.get("/health")
async def user_health_check():
    """Health check for the user router."""
    return {"status": "ok", "message": "User router is healthy"}
