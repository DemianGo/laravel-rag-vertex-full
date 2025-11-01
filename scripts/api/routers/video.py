"""
Video Processing API endpoints
Integrates video processing Python scripts
"""

import sys
import os
import json
import subprocess
import time
from pathlib import Path
from typing import Optional, Dict, Any
from fastapi import APIRouter, HTTPException, Depends, Request, UploadFile, File
from fastapi.responses import JSONResponse
import structlog

logger = structlog.get_logger(__name__)

# Add video_processing directory to path
current_dir = Path(__file__).parent.parent
video_processing_dir = current_dir.parent / "video_processing"
sys.path.insert(0, str(video_processing_dir))

from core.security import get_api_key_from_header

router = APIRouter(
    prefix="/api/video",
    tags=["video"],
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


@router.post("/ingest")
async def video_ingest(
    request: Request,
    api_key: str = Depends(get_api_key_from_header)
):
    """
    Process video upload or URL
    POST /api/video/ingest
    """
    try:
        start_time = time.time()
        
        # Parse multipart form data
        form = await request.form()
        
        # Get inputs
        url = form.get('url')
        file = form.get('file')
        language = form.get('language', 'pt-BR')
        service = form.get('service', 'auto')
        
        # Get user for tenant isolation
        user_id = await get_user_id_from_api_key(api_key)
        if not user_id:
            raise HTTPException(status_code=401, detail="Invalid API Key or User not found")
        
        tenant_slug = f"user_{user_id}"
        
        # Determine source type
        if url:
            # URL-based processing
            logger.info("Processing video URL", url=url)
            
            # Use working_final_transcriber.py
            result = subprocess.run([
                "python3",
                str(video_processing_dir / "working_final_transcriber.py"),
                url
            ], capture_output=True, text=True, timeout=120, 
            cwd=str(Path(__file__).parent.parent.parent))
            
            if result.returncode == 0:
                transcription_result = json.loads(result.stdout)
                
                if not transcription_result.get('success'):
                    raise HTTPException(
                        status_code=500,
                        detail=transcription_result.get('error', 'Transcription failed')
                    )
                
                text = transcription_result.get('transcript', '')
                video_info = transcription_result.get('video_info', {})
                title = video_info.get('title', f'Video from {url}')
                source_type = 'video_url'
                
            else:
                raise HTTPException(status_code=500, detail="Video processing failed")
                
        elif file:
            # File upload processing
            logger.info("Processing video file", filename=file.filename)
            
            # Save uploaded file temporarily
            temp_path = f"/tmp/{file.filename}"
            content = await file.read()
            with open(temp_path, "wb") as f:
                f.write(content)
            
            try:
                # Use working_final_transcriber.py (it accepts file paths)
                result = subprocess.run([
                    "python3",
                    str(video_processing_dir / "working_final_transcriber.py"),
                    temp_path
                ], capture_output=True, text=True, timeout=120,
                cwd=str(Path(__file__).parent.parent.parent))
                
                if result.returncode == 0:
                    transcription_result = json.loads(result.stdout)
                    text = transcription_result.get('transcript', '')
                    video_info = transcription_result.get('video_info', {})
                    title = file.filename
                    source_type = 'video_upload'
                else:
                    raise HTTPException(status_code=500, detail="Video processing failed")
                    
            finally:
                # Clean up temp file
                try:
                    os.unlink(temp_path)
                except:
                    pass
        else:
            raise HTTPException(status_code=422, detail="Either 'url' or 'file' parameter required")
        
        # Get DB config
        db_config = {
            "host": os.getenv("DB_HOST", "localhost"),
            "database": os.getenv("DB_DATABASE", "laravel_rag"),
            "user": os.getenv("DB_USERNAME", "postgres"),
            "password": os.getenv("DB_PASSWORD", ""),
            "port": os.getenv("DB_PORT", "5432")
        }
        
        # Save to database
        try:
            conn = psycopg2.connect(**db_config)
            cursor = conn.cursor()
            
            # Create document
            cursor.execute("""
                INSERT INTO documents (title, source, uri, tenant_slug, metadata, created_at, updated_at)
                VALUES (%s, %s, %s, %s, %s, NOW(), NOW())
                RETURNING id
            """, (
                title,
                source_type,
                url if url else file.filename,
                tenant_slug,
                json.dumps({
                    'type': 'video',
                    'source_type': source_type,
                    'language': language,
                    'video_info': video_info
                })
            ))
            
            document_id = cursor.fetchone()[0]
            
            # Create chunks from transcription
            chunks_text = [text[i:i+1000] for i in range(0, len(text), 1000)]
            
            for i, chunk_text in enumerate(chunks_text):
                cursor.execute("""
                    INSERT INTO chunks (document_id, content, ord, meta, created_at, updated_at)
                    VALUES (%s, %s, %s, %s, NOW(), NOW())
                """, (document_id, str(chunk_text), i, json.dumps({'type': 'video_chunk'})))
            
            conn.commit()
            cursor.close()
            conn.close()
            
            chunks_created = len(chunks_text)
            
        except Exception as e:
            logger.error(f"Database save error: {e}")
            raise HTTPException(status_code=500, detail=f"Database error: {str(e)}")
        
        processing_time = time.time() - start_time
        
        return JSONResponse(content={
            "ok": True,
            "success": True,
            "document_id": document_id,
            "title": title,
            "chunks_created": chunks_created,
            "processing_time": round(processing_time, 3)
        }, status_code=201)
        
    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Video ingest error: {e}")
        raise HTTPException(status_code=500, detail=f"Error: {str(e)}")


@router.post("/info")
async def get_video_info(
    request: Request,
    api_key: str = Depends(get_api_key_from_header)
):
    """
    Get video information without processing
    POST /api/video/info
    """
    try:
        data = await request.json()
        url = data.get('url')
        
        if not url:
            raise HTTPException(status_code=422, detail="URL parameter required")
        
        # Use video_downloader.py to get info
        result = subprocess.run([
            "python3",
            str(video_processing_dir / "video_downloader.py"),
            "--info-only",
            url
        ], capture_output=True, text=True, timeout=30,
        cwd=str(Path(__file__).parent.parent.parent))
        
        if result.returncode == 0:
            info = json.loads(result.stdout)
            return JSONResponse(content=info)
        else:
            raise HTTPException(status_code=500, detail="Failed to get video info")
            
    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Video info error: {e}")
        raise HTTPException(status_code=500, detail=f"Error: {str(e)}")

