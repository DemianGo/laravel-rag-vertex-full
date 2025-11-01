"""
Authentication router for user registration, login, and API key management
"""

import sys
import os
import secrets
from pathlib import Path
from typing import Dict, Any, Optional
from datetime import datetime
from fastapi import APIRouter, HTTPException, Depends, Request, Body
from fastapi.responses import JSONResponse
from pydantic import BaseModel, EmailStr
import structlog

logger = structlog.get_logger(__name__)

# Add rag_search directory to path
current_dir = Path(__file__).parent
rag_search_dir = current_dir.parent.parent / "rag_search"
sys.path.insert(0, str(rag_search_dir))

from database import DatabaseManager
from config import Config
import psycopg2.extras
import hashlib

# Set the cursor factory
Config.DB_CURSOR_FACTORY = psycopg2.extras.RealDictCursor

router = APIRouter(
    prefix="/auth",
    tags=["authentication"],
)

# Initialize DatabaseManager
db_manager = DatabaseManager()

# Helper function to verify password (compatible with Laravel hashing)
def verify_password(plain_password: str, hashed_password: str) -> bool:
    """Verify password against Laravel's bcrypt hash"""
    # For Laravel compatibility, try bcrypt first
    try:
        import bcrypt
        return bcrypt.checkpw(plain_password.encode('utf-8'), hashed_password.encode('utf-8'))
    except:
        # Fallback: try simple hash (for migration period)
        password_hash = hashlib.sha256(plain_password.encode()).hexdigest()
        return password_hash == hashed_password

def hash_password(password: str) -> str:
    """Hash password (compatible with Laravel bcrypt)"""
    try:
        import bcrypt
        return bcrypt.hashpw(password.encode('utf-8'), bcrypt.gensalt()).decode('utf-8')
    except:
        # Fallback: simple hash (NOT recommended for production)
        return hashlib.sha256(password.encode()).hexdigest()

def generate_api_key() -> str:
    """
    Generate API key in format: rag_ + 56 hex characters
    Same format as Laravel User::generateApiKey()
    """
    random_part = secrets.token_hex(28)  # 28 bytes = 56 hex characters
    return f"rag_{random_part}"

class UserRegister(BaseModel):
    name: str
    email: EmailStr
    password: str

class UserLogin(BaseModel):
    email: EmailStr
    password: str

class APIKeyResponse(BaseModel):
    api_key: str
    created_at: datetime
    message: str

@router.post("/register")
async def register(
    request: Request,
    user_data: UserRegister = Body(...)
):
    """
    Register a new user.
    Creates user account and generates API key automatically.
    """
    try:
        with db_manager.get_connection() as conn:
            with conn.cursor() as cursor:
                # Check if user already exists
                cursor.execute("SELECT id FROM users WHERE email = %s", (user_data.email,))
                existing = cursor.fetchone()
                if existing:
                    raise HTTPException(
                        status_code=400,
                        detail="Email already registered"
                    )
                
                # Hash password
                password_hash = hash_password(user_data.password)
                
                # Generate API key
                api_key = generate_api_key()
                
                # Create user (tenant_slug will be added later if needed)
                cursor.execute(
                    """
                    INSERT INTO users (name, email, password, plan, tokens_limit, documents_limit, 
                                      api_key, api_key_created_at, created_at, updated_at)
                    VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                    RETURNING id
                    """,
                    (
                        user_data.name,
                        user_data.email,
                        password_hash,
                        "free",  # Default plan
                        100,  # tokens_limit for free plan
                        1,  # documents_limit for free plan
                        api_key,
                        datetime.now(),
                        datetime.now(),
                        datetime.now()
                    )
                )
                user_id = cursor.fetchone()[0]
                
                conn.commit()
                
                logger.info("User registered", user_id=user_id, email=user_data.email)
                
                return JSONResponse(content={
                    "success": True,
                    "message": "User registered successfully",
                    "user": {
                        "id": user_id,
                        "name": user_data.name,
                        "email": user_data.email,
                        "plan": "free"
                    },
                    "api_key": api_key,
                    "api_key_created_at": datetime.now().isoformat()
                })
                
    except HTTPException:
        raise
    except Exception as e:
        logger.error("Registration error", error=str(e))
        raise HTTPException(status_code=500, detail=f"Registration failed: {str(e)}")

@router.post("/login")
async def login(
    request: Request,
    credentials: UserLogin = Body(...)
):
    """
    Login user and return API key.
    If user doesn't have API key, generates one automatically.
    """
    try:
        with db_manager.get_connection() as conn:
            with conn.cursor() as cursor:
                # Get user by email
                cursor.execute(
                    "SELECT id, name, email, password, plan, api_key FROM users WHERE email = %s",
                    (credentials.email,)
                )
                user = cursor.fetchone()
                
                if not user:
                    raise HTTPException(
                        status_code=401,
                        detail="Invalid email or password"
                    )
                
                user_id, name, email, password_hash, plan, api_key = user
                
                # Verify password
                if not verify_password(credentials.password, password_hash):
                    raise HTTPException(
                        status_code=401,
                        detail="Invalid email or password"
                    )
                
                # Generate API key if user doesn't have one
                if not api_key:
                    api_key = generate_api_key()
                    cursor.execute(
                        """
                        UPDATE users 
                        SET api_key = %s, api_key_created_at = %s, api_key_last_used_at = %s
                        WHERE id = %s
                        """,
                        (api_key, datetime.now(), datetime.now(), user_id)
                    )
                    conn.commit()
                else:
                    # Update last used timestamp
                    cursor.execute(
                        "UPDATE users SET api_key_last_used_at = %s WHERE id = %s",
                        (datetime.now(), user_id)
                    )
                    conn.commit()
                
                logger.info("User logged in", user_id=user_id, email=email)
                
                return JSONResponse(content={
                    "success": True,
                    "message": "Login successful",
                    "user": {
                        "id": user_id,
                        "name": name,
                        "email": email,
                        "plan": plan
                    },
                    "api_key": api_key,
                    "api_key_created_at": datetime.now().isoformat() if not api_key else None,
                    "api_key_last_used_at": datetime.now().isoformat()
                })
                
    except HTTPException:
        raise
    except Exception as e:
        logger.error("Login error", error=str(e))
        raise HTTPException(status_code=500, detail=f"Login failed: {str(e)}")

@router.post("/api-key/generate")
async def generate_api_key_endpoint(
    request: Request
):
    """
    Generate a new API key for the authenticated user.
    Requires valid API key in headers (for existing users).
    For new users, API key is automatically generated during registration.
    """
    # Extract API key from request
    api_key = request.headers.get("X-API-Key") or request.headers.get("Authorization", "").replace("Bearer ", "")
    
    if not api_key:
        raise HTTPException(status_code=401, detail="API key required")
    
    try:
        with db_manager.get_connection() as conn:
            with conn.cursor() as cursor:
                # Get user by API key
                cursor.execute("SELECT id FROM users WHERE api_key = %s", (api_key,))
                user = cursor.fetchone()
                
                if not user:
                    raise HTTPException(status_code=401, detail="Invalid API key")
                
                user_id = user[0]
                
                # Generate new API key
                new_api_key = generate_api_key()
                cursor.execute(
                    """
                    UPDATE users 
                    SET api_key = %s, api_key_created_at = %s, api_key_last_used_at = NULL
                    WHERE id = %s
                    """,
                    (new_api_key, datetime.now(), user_id)
                )
                conn.commit()
                
                logger.info("API key generated", user_id=user_id)
                
                return JSONResponse(content={
                    "success": True,
                    "message": "API key generated successfully. Please save it securely as it won't be shown again.",
                    "api_key": new_api_key,
                    "api_key_created_at": datetime.now().isoformat()
                })
                
    except HTTPException:
        raise
    except Exception as e:
        logger.error("API key generation error", error=str(e))
        raise HTTPException(status_code=500, detail=f"API key generation failed: {str(e)}")

@router.post("/api-key/regenerate")
async def regenerate_api_key(
    request: Request
):
    """
    Regenerate API key (same as generate, but explicitly invalidates old one).
    Requires valid API key in headers.
    """
    # Extract API key from request
    api_key = request.headers.get("X-API-Key") or request.headers.get("Authorization", "").replace("Bearer ", "")
    
    if not api_key:
        raise HTTPException(status_code=401, detail="API key required")
    
    try:
        with db_manager.get_connection() as conn:
            with conn.cursor() as cursor:
                # Get user by API key
                cursor.execute("SELECT id FROM users WHERE api_key = %s", (api_key,))
                user = cursor.fetchone()
                
                if not user:
                    raise HTTPException(status_code=401, detail="Invalid API key")
                
                user_id = user[0]
                
                # Generate new API key
                new_api_key = generate_api_key()
                cursor.execute(
                    """
                    UPDATE users 
                    SET api_key = %s, api_key_created_at = %s, api_key_last_used_at = NULL
                    WHERE id = %s
                    """,
                    (new_api_key, datetime.now(), user_id)
                )
                conn.commit()
                
                logger.info("API key regenerated", user_id=user_id)
                
                return JSONResponse(content={
                    "success": True,
                    "message": "API key regenerated successfully. Your old API key has been invalidated.",
                    "api_key": new_api_key,
                    "api_key_created_at": datetime.now().isoformat()
                })
                
    except HTTPException:
        raise
    except Exception as e:
        logger.error("API key regeneration error", error=str(e))
        raise HTTPException(status_code=500, detail=f"API key regeneration failed: {str(e)}")

@router.delete("/api-key/revoke")
async def revoke_api_key(
    request: Request
):
    """
    Revoke (delete) API key for the authenticated user.
    Requires valid API key in headers.
    """
    # Extract API key from request
    api_key = request.headers.get("X-API-Key") or request.headers.get("Authorization", "").replace("Bearer ", "")
    
    if not api_key:
        raise HTTPException(status_code=401, detail="API key required")
    
    try:
        with db_manager.get_connection() as conn:
            with conn.cursor() as cursor:
                # Get user by API key
                cursor.execute("SELECT id FROM users WHERE api_key = %s", (api_key,))
                user = cursor.fetchone()
                
                if not user:
                    raise HTTPException(status_code=401, detail="Invalid API key")
                
                user_id = user[0]
                
                # Revoke API key
                cursor.execute(
                    """
                    UPDATE users 
                    SET api_key = NULL, api_key_created_at = NULL, api_key_last_used_at = NULL
                    WHERE id = %s
                    """,
                    (user_id,)
                )
                conn.commit()
                
                logger.info("API key revoked", user_id=user_id)
                
                return JSONResponse(content={
                    "success": True,
                    "message": "API key revoked successfully"
                })
                
    except HTTPException:
        raise
    except Exception as e:
        logger.error("API key revocation error", error=str(e))
        raise HTTPException(status_code=500, detail=f"API key revocation failed: {str(e)}")

@router.get("/api-key")
async def get_api_key(
    request: Request
):
    """
    Get current API key for the authenticated user.
    Requires valid API key in headers.
    """
    # Extract API key from request
    api_key = request.headers.get("X-API-Key") or request.headers.get("Authorization", "").replace("Bearer ", "")
    
    if not api_key:
        raise HTTPException(status_code=401, detail="API key required")
    
    try:
        with db_manager.get_connection() as conn:
            with conn.cursor() as cursor:
                # Get user by API key
                cursor.execute(
                    """
                    SELECT id, api_key, api_key_created_at, api_key_last_used_at 
                    FROM users WHERE api_key = %s
                    """,
                    (api_key,)
                )
                user = cursor.fetchone()
                
                if not user:
                    raise HTTPException(status_code=401, detail="Invalid API key")
                
                user_id, stored_api_key, created_at, last_used = user
                
                if not stored_api_key:
                    return JSONResponse(content={
                        "api_key": None,
                        "message": "No API key found. Please generate one first."
                    })
                
                return JSONResponse(content={
                    "api_key": stored_api_key,
                    "api_key_created_at": created_at.isoformat() if created_at else None,
                    "api_key_last_used_at": last_used.isoformat() if last_used else None
                })
                
    except HTTPException:
        raise
    except Exception as e:
        logger.error("Get API key error", error=str(e))
        raise HTTPException(status_code=500, detail=f"Failed to get API key: {str(e)}")

