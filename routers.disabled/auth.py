"""
Authentication Router
Sistema de autenticação JWT para FastAPI
"""

from fastapi import APIRouter, HTTPException, Depends, status
from fastapi.security import HTTPBearer, HTTPAuthorizationCredentials
from pydantic import BaseModel
from typing import Optional
import jwt
import bcrypt
import os
from datetime import datetime, timedelta
import psycopg2
from psycopg2.extras import RealDictCursor

router = APIRouter()
security = HTTPBearer()

# Configuração JWT
SECRET_KEY = os.getenv("JWT_SECRET_KEY", "your-secret-key-here")
ALGORITHM = "HS256"
ACCESS_TOKEN_EXPIRE_MINUTES = 30

# Configuração do banco
DB_CONFIG = {
    "host": "127.0.0.1",
    "port": "5432",
    "database": "laravel_rag",
    "user": "postgres",
    "password": "postgres"
}

class LoginRequest(BaseModel):
    email: str
    password: str

class RegisterRequest(BaseModel):
    name: str
    email: str
    password: str

class TokenResponse(BaseModel):
    access_token: str
    token_type: str
    user: dict

def get_db_connection():
    """Obter conexão com o banco"""
    return psycopg2.connect(**DB_CONFIG)

def verify_password(plain_password: str, hashed_password: str) -> bool:
    """Verificar senha"""
    return bcrypt.checkpw(plain_password.encode('utf-8'), hashed_password.encode('utf-8'))

def hash_password(password: str) -> str:
    """Hash da senha"""
    return bcrypt.hashpw(password.encode('utf-8'), bcrypt.gensalt()).decode('utf-8')

def create_access_token(data: dict, expires_delta: Optional[timedelta] = None):
    """Criar token JWT"""
    to_encode = data.copy()
    if expires_delta:
        expire = datetime.utcnow() + expires_delta
    else:
        expire = datetime.utcnow() + timedelta(minutes=15)
    to_encode.update({"exp": expire})
    encoded_jwt = jwt.encode(to_encode, SECRET_KEY, algorithm=ALGORITHM)
    return encoded_jwt

async def get_current_user(credentials: HTTPAuthorizationCredentials = Depends(security)):
    """Obter usuário atual do token"""
    credentials_exception = HTTPException(
        status_code=status.HTTP_401_UNAUTHORIZED,
        detail="Could not validate credentials",
        headers={"WWW-Authenticate": "Bearer"},
    )
    
    try:
        payload = jwt.decode(credentials.credentials, SECRET_KEY, algorithms=[ALGORITHM])
        user_id: str = payload.get("sub")
        if user_id is None:
            raise credentials_exception
    except jwt.PyJWTError:
        raise credentials_exception
    
    # Buscar usuário no banco
    conn = get_db_connection()
    try:
        with conn.cursor(cursor_factory=RealDictCursor) as cursor:
            cursor.execute("SELECT * FROM users WHERE id = %s", (user_id,))
            user = cursor.fetchone()
            if user is None:
                raise credentials_exception
            return dict(user)
    finally:
        conn.close()

@router.post("/login", response_model=TokenResponse)
async def login(request: LoginRequest):
    """Login do usuário"""
    conn = get_db_connection()
    try:
        with conn.cursor(cursor_factory=RealDictCursor) as cursor:
            cursor.execute("SELECT * FROM users WHERE email = %s", (request.email,))
            user = cursor.fetchone()
            
            if not user or not verify_password(request.password, user['password']):
                raise HTTPException(
                    status_code=status.HTTP_401_UNAUTHORIZED,
                    detail="Email ou senha incorretos"
                )
            
            # Criar token
            access_token_expires = timedelta(minutes=ACCESS_TOKEN_EXPIRE_MINUTES)
            access_token = create_access_token(
                data={"sub": str(user['id'])}, expires_delta=access_token_expires
            )
            
            return TokenResponse(
                access_token=access_token,
                token_type="bearer",
                user={
                    "id": user['id'],
                    "name": user['name'],
                    "email": user['email'],
                    "plan": user.get('plan', 'free'),
                    "is_admin": user.get('is_admin', False)
                }
            )
    finally:
        conn.close()

@router.post("/register", response_model=TokenResponse)
async def register(request: RegisterRequest):
    """Registro de novo usuário"""
    conn = get_db_connection()
    try:
        with conn.cursor(cursor_factory=RealDictCursor) as cursor:
            # Verificar se email já existe
            cursor.execute("SELECT id FROM users WHERE email = %s", (request.email,))
            if cursor.fetchone():
                raise HTTPException(
                    status_code=status.HTTP_400_BAD_REQUEST,
                    detail="Email já está em uso"
                )
            
            # Criar usuário
            hashed_password = hash_password(request.password)
            cursor.execute(
                """
                INSERT INTO users (name, email, password, plan, tokens_used, tokens_limit, 
                                 documents_used, documents_limit, email_verified_at, created_at, updated_at)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
                RETURNING *
                """,
                (
                    request.name,
                    request.email,
                    hashed_password,
                    'free',
                    0,
                    100,
                    0,
                    1,
                    datetime.utcnow(),
                    datetime.utcnow(),
                    datetime.utcnow()
                )
            )
            user = cursor.fetchone()
            conn.commit()
            
            # Criar token
            access_token_expires = timedelta(minutes=ACCESS_TOKEN_EXPIRE_MINUTES)
            access_token = create_access_token(
                data={"sub": str(user['id'])}, expires_delta=access_token_expires
            )
            
            return TokenResponse(
                access_token=access_token,
                token_type="bearer",
                user={
                    "id": user['id'],
                    "name": user['name'],
                    "email": user['email'],
                    "plan": user.get('plan', 'free'),
                    "is_admin": user.get('is_admin', False)
                }
            )
    finally:
        conn.close()

@router.get("/me")
async def get_current_user_info(current_user: dict = Depends(get_current_user)):
    """Obter informações do usuário atual"""
    return {
        "id": current_user['id'],
        "name": current_user['name'],
        "email": current_user['email'],
        "plan": current_user.get('plan', 'free'),
        "tokens_used": current_user.get('tokens_used', 0),
        "tokens_limit": current_user.get('tokens_limit', 100),
        "documents_used": current_user.get('documents_used', 0),
        "documents_limit": current_user.get('documents_limit', 1),
        "is_admin": current_user.get('is_admin', False)
    }

@router.post("/logout")
async def logout():
    """Logout (invalidar token no cliente)"""
    return {"message": "Logout realizado com sucesso"}
