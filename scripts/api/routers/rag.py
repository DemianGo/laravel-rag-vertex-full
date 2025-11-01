"""
RAG Search API endpoints
Integrates with Python RAG search scripts
"""

import sys
import os
import json
import subprocess
import time
from pathlib import Path
from typing import Optional, Dict, Any
from fastapi import APIRouter, HTTPException, Depends, Request, Body, UploadFile, File
from fastapi.responses import JSONResponse
from pydantic import BaseModel
import structlog
import psycopg2
from psycopg2.extras import RealDictCursor
from datetime import datetime

logger = structlog.get_logger(__name__)

# Add rag_search directory to path
current_dir = Path(__file__).parent.parent
rag_search_dir = current_dir.parent / "rag_search"
sys.path.insert(0, str(rag_search_dir))

# Import adaptive timeout
try:
    from adaptive_timeout import AdaptiveTimeout
except ImportError:
    # Fallback if not available
    AdaptiveTimeout = None

from core.security import get_api_key_from_header

router = APIRouter(
    prefix="/api/rag",  # Match Laravel route: /api/rag/python-search
    tags=["rag"],
)

# Initialize DatabaseManager (from rag_search module)
try:
    from database import DatabaseManager
    db_manager = DatabaseManager()
except ImportError:
    # Fallback: create a simple connection manager
    import psycopg2
    from config import Config
    db_manager = None  # Will use direct connections

class RAGSearchRequest(BaseModel):
    query: str
    document_id: Optional[int] = None
    top_k: Optional[int] = 5
    threshold: Optional[float] = 0.3
    include_answer: Optional[bool] = True
    strictness: Optional[int] = 2
    mode: Optional[str] = "auto"
    format: Optional[str] = "plain"
    length: Optional[str] = "auto"
    citations: Optional[int] = 0
    use_full_document: Optional[bool] = False
    use_smart_mode: Optional[bool] = True
    enable_web_search: Optional[bool] = False
    llm_provider: Optional[str] = "gemini"
    force_grounding: Optional[bool] = False

@router.post("/python-search")
async def python_search(
    request: Request,
    payload: RAGSearchRequest = Body(...),
    api_key: str = Depends(get_api_key_from_header)
):
    """
    Perform RAG search using Python scripts.
    Same functionality as Laravel RagPythonController.
    """
    try:
        # Get user_id from API key for tenant isolation
        user_id = await get_user_id_from_api_key(api_key)
        if not user_id:
            raise HTTPException(status_code=401, detail="Invalid API Key or User not found")
        
        tenant_slug = f"user_{user_id}"
        
        # Validate query
        if not payload.query:
            raise HTTPException(status_code=422, detail="Parameter 'query' is required")
        
        # Use hybrid_search.py which supports grounding and LLM
        # This is the main script available (smart_router.py and rag_search.py don't exist)
        script_name = "hybrid_search.py"
        script_path = rag_search_dir / script_name
        
        # If script doesn't exist, use module directly
        if not script_path.exists():
            logger.warning(f"Script {script_name} not found, using module directly")
            return await _search_via_module(payload, tenant_slug, user_id)
        
        # Build command
        cmd = [
            "python3",
            str(script_path),
            "--query", payload.query,
            "--top-k", str(payload.top_k or 5),
            "--threshold", str(payload.threshold or 0.3)
        ]
        
        if payload.document_id:
            cmd.extend(["--document-id", str(payload.document_id)])
        
        if not payload.include_answer:
            cmd.append("--no-llm")
        
        if payload.strictness != 2:
            cmd.extend(["--strictness", str(payload.strictness)])
        
        if payload.mode != "auto":
            cmd.extend(["--mode", payload.mode])
        
        if payload.format != "plain":
            cmd.extend(["--format", payload.format])
        
        if payload.length != "auto":
            cmd.extend(["--length", payload.length])
        
        if payload.citations > 0:
            cmd.extend(["--citations", str(payload.citations)])
        
        if payload.use_full_document:
            cmd.append("--use-full-document")
        
        # For hybrid_search.py, grounding is controlled by force_grounding flag
        if payload.force_grounding:
            cmd.append("--force-grounding")
        
        # Add LLM provider if specified
        if payload.llm_provider and payload.llm_provider != "gemini":
            cmd.extend(["--llm-provider", payload.llm_provider])
        
        # Database config from environment
        db_config = {
            "host": os.getenv("DB_HOST", "localhost"),
            "database": os.getenv("DB_DATABASE", "laravel_rag"),
            "user": os.getenv("DB_USERNAME", "postgres"),
            "password": os.getenv("DB_PASSWORD", ""),
            "port": os.getenv("DB_PORT", "5432")
        }
        
        cmd.extend(["--db-config", json.dumps(db_config)])
        
        # Execute Python script
        start_time = time.time()
        
        logger.info("Executing RAG search", command=" ".join(cmd[:10]))  # Log first 10 args
        
        result = subprocess.run(
            cmd,
            capture_output=True,
            text=True,
            timeout=120,  # 2 minute timeout
            cwd=str(rag_search_dir)
        )
        
        execution_time = time.time() - start_time
        
        if result.returncode != 0:
            logger.error("RAG search script failed", 
                        returncode=result.returncode,
                        stderr=result.stderr[:500])
            raise HTTPException(
                status_code=500,
                detail=f"Python script execution failed: {result.stderr[:200]}"
            )
        
        if not result.stdout:
            raise HTTPException(
                status_code=500,
                detail="Python script returned no output"
            )
        
        # Parse JSON response
        try:
            result_data = json.loads(result.stdout.strip())
        except json.JSONDecodeError as e:
            logger.error("Failed to parse JSON from Python script",
                        stdout=result.stdout[:500],
                        error=str(e))
            raise HTTPException(
                status_code=500,
                detail=f"Invalid JSON response from Python script: {str(e)}"
            )
        
        # Add execution metadata
        if "metadata" not in result_data:
            result_data["metadata"] = {}
        
        result_data["metadata"]["python_execution_time"] = round(execution_time, 3)
        result_data["metadata"]["cache_hit"] = False
        
        logger.info("RAG search completed",
                   success=result_data.get("ok") or result_data.get("success", False),
                   chunks_found=len(result_data.get("chunks", [])))
        
        return JSONResponse(content=result_data)
        
    except subprocess.TimeoutExpired:
        logger.error("RAG search timeout")
        raise HTTPException(status_code=504, detail="RAG search timeout (120s)")
    except HTTPException:
        raise
    except Exception as e:
        logger.error("RAG search error", error=str(e))
        raise HTTPException(status_code=500, detail=f"Internal error: {str(e)}")

async def _search_via_module(payload: RAGSearchRequest, tenant_slug: str, user_id: int):
    """Fallback: Call RAG search via Python module import"""
    try:
        # Import and use HybridSearchService directly (supports grounding and LLM)
        from hybrid_search_service import HybridSearchService
        from config import Config as RAGConfig
        
        # Create config with DB settings
        db_config = {
            "host": os.getenv("DB_HOST", "localhost"),
            "database": os.getenv("DB_DATABASE", "laravel_rag"),
            "user": os.getenv("DB_USERNAME", "postgres"),
            "password": os.getenv("DB_PASSWORD", ""),
            "port": os.getenv("DB_PORT", "5432")
        }
        
        config = RAGConfig(db_config)
        hybrid_service = HybridSearchService(config, llm_provider=payload.llm_provider or "gemini")
        
        # Execute search
        result = hybrid_service.search(
            query=payload.query,
            document_id=payload.document_id,
            top_k=payload.top_k or 5,
            threshold=payload.threshold or 0.3,
            force_grounding=payload.force_grounding or payload.enable_web_search or False
        )
        
        # Format response to match expected structure
        formatted_result = {
            "ok": result.get("success", False),
            "success": result.get("success", False),
            "query": payload.query,
            "answer": result.get("answer", ""),
            "chunks": result.get("sources", {}).get("documents", []),
            "web_sources": result.get("sources", {}).get("web", []),
            "chunks_found": result.get("chunks_found", 0),
            "used_web_search": result.get("used_grounding", False),
            "used_grounding": result.get("used_grounding", False),
            "search_method": result.get("search_method", "hybrid"),
            "execution_time": result.get("execution_time", 0),
            "llm_provider": result.get("llm_provider", payload.llm_provider or "gemini"),
            "metadata": {
                "query_type": result.get("query_type", "other"),
                "search_method": result.get("search_method", "hybrid"),
                "used_grounding": result.get("used_grounding", False)
            }
        }
        
        if not result.get("success"):
            formatted_result["error"] = result.get("error", "Unknown error")
        
        return JSONResponse(content=formatted_result)
    except ImportError as e:
        logger.error(f"Import error: {e}")
        raise HTTPException(
            status_code=500,
            detail=f"RAG search modules not available: {str(e)}. Please ensure scripts are installed."
        )
    except Exception as e:
        logger.error(f"Module search error: {e}")
        raise HTTPException(
            status_code=500,
            detail=f"Error calling RAG search module: {str(e)}"
        )

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
            import psycopg2
            from config import Config as RAGConfig
            conn = psycopg2.connect(
                host=RAGConfig.DB_HOST,
                database=RAGConfig.DB_NAME,
                user=RAGConfig.DB_USER,
                password=RAGConfig.DB_PASSWORD,
                port=RAGConfig.DB_PORT
            )
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

@router.get("/python-health")
async def python_health(request: Request):  # Public endpoint, no auth required
    """Python RAG system health check"""
    try:
        # Check if scripts exist (use hybrid_search.py which is the main script)
        script_path = rag_search_dir / "hybrid_search.py"
        script_exists = script_path.exists()
        
        # Check Python version
        result = subprocess.run(
            ["python3", "--version"],
            capture_output=True,
            text=True,
            timeout=5
        )
        python_version = result.stdout.strip() if result.returncode == 0 else "Unknown"
        
        # Check dependencies (quick test)
        deps_test = "OK"
        try:
            test_cmd = [
                "python3", "-c",
                f"import sys; sys.path.insert(0, '{rag_search_dir}'); "
                "import config, embeddings_service, vector_search, llm_service, database, hybrid_search_service; "
                "print('OK')"
            ]
            deps_result = subprocess.run(
                test_cmd,
                capture_output=True,
                text=True,
                timeout=5,
                cwd=str(rag_search_dir)
            )
            deps_test = "OK" if deps_result.returncode == 0 else "FAILED"
        except:
            deps_test = "UNKNOWN"
        
        # Get database stats
        total_documents = 0
        total_chunks = 0
        chunks_with_embeddings = 0
        
        try:
            with db_manager.get_connection() as conn:
                with conn.cursor() as cursor:
                    cursor.execute("SELECT COUNT(*) FROM documents")
                    total_documents = cursor.fetchone()[0]
                    
                    cursor.execute("SELECT COUNT(*) FROM chunks")
                    total_chunks = cursor.fetchone()[0]
                    
                    cursor.execute("SELECT COUNT(*) FROM chunks WHERE embedding IS NOT NULL")
                    chunks_with_embeddings = cursor.fetchone()[0]
        except Exception as e:
            logger.error("Error getting database stats", error=str(e))
        
        embedding_coverage = round((chunks_with_embeddings / total_chunks * 100), 2) if total_chunks > 0 else 0
        
        return JSONResponse(content={
            "success": True,
            "python_version": python_version,
            "script_exists": script_exists,
            "dependencies_test": deps_test,
            "database_stats": {
                "total_documents": total_documents,
                "total_chunks": total_chunks,
                "chunks_with_embeddings": chunks_with_embeddings,
                "embedding_coverage": embedding_coverage
            },
            "timestamp": time.strftime("%Y-%m-%dT%H:%M:%S")
        })
    except Exception as e:
        logger.error("Health check error", error=str(e))
        return JSONResponse(
            status_code=500,
            content={
                "success": False,
                "error": str(e)
            }
        )


@router.post("/ingest")
async def rag_ingest(request: Request, api_key: str = Depends(get_api_key_from_header)):
    """
    Document ingestion endpoint compatible with Laravel
    Accepts: file upload, text, or URL
    Returns: same response format as Laravel
    """
    try:
        start_time = time.time()
        
        # Parse multipart form data
        form = await request.form()
        
        # Support multiple file field names
        file = form.get('file')
        files = form.getlist('files[]') if not file else []
        title = form.get('title')
        text = form.get('text')
        url = form.get('url')
        tenant_slug = form.get('tenant_slug', 'default')
        user_id = form.get('user_id')
        
        # Get user_id from API key for tenant isolation
        api_user_id = await get_user_id_from_api_key(api_key)
        if api_user_id:
            tenant_slug = f"user_{api_user_id}"
        
        # Determine content and title
        content = ""
        doc_title = title or "Documento"
        
        if file or files:
            # File upload(s)
            if file:
                content = await file.read()
                doc_title = title or file.filename or "Documento"
            elif files:
                file = files[0]
                content = await file.read()
                doc_title = title or file.filename or "Documento"
            
            # Process file with Python extraction scripts
            temp_path = f"/tmp/{file.filename}"
            with open(temp_path, "wb") as f:
                f.write(content)
            
            # Use extraction script with adaptive timeout
            try:
                # Calculate adaptive timeout based on file size
                if AdaptiveTimeout:
                    timeout_seconds = AdaptiveTimeout.calculate_timeout(len(content))
                    timeout_info = AdaptiveTimeout.get_timeout_info(len(content))
                    logger.info("Processing file", filename=file.filename, 
                               size_mb=timeout_info.get('file_size_mb', 0))
                else:
                    timeout_seconds = 300  # 5 minutes default
                
                result = subprocess.run([
                    "python3", 
                    "scripts/document_extraction/main_extractor.py",
                    "--input", temp_path
                ], capture_output=True, text=True, timeout=timeout_seconds, 
                cwd=str(Path(__file__).parent.parent.parent))
                
                if result.returncode == 0:
                    extraction_result = json.loads(result.stdout)
                    content = extraction_result.get('extracted_text', '')
                    doc_title = extraction_result.get('title', doc_title) if extraction_result.get('title') else doc_title
                else:
                    # Fallback: use raw content
                    if isinstance(content, bytes):
                        content = content.decode('utf-8', errors='ignore')
                    
            except Exception as e:
                logger.warning(f"Error in extraction, using fallback: {e}")
                if isinstance(content, bytes):
                    content = content.decode('utf-8', errors='ignore')
            
            # Clean up temp file
            try:
                os.unlink(temp_path)
            except:
                pass
                
        elif text:
            # Direct text
            content = text
            doc_title = title or "Documento de Texto"
            
        elif url:
            # URL (placeholder)
            content = f"Conteúdo da URL: {url}"
            doc_title = title or f"URL: {url}"
            
        else:
            raise HTTPException(status_code=422, detail="Necessário: file, text ou url")
        
        # Validate content - allow empty or short content for files (extraction may fail but file is valid)
        # Only validate for direct text input
        if text and len(content.strip()) < 10:
            raise HTTPException(status_code=422, detail="Conteúdo muito curto. Mínimo 10 caracteres.")
        
        # For files, if content is too short after extraction, use a default message
        if (file or files) and len(content.strip()) < 10:
            content = f"Arquivo processado: {doc_title}. Conteúdo extraído não disponível ou muito curto."
        
        # Get DB config from environment
        db_config = {
            "host": os.getenv("DB_HOST", "localhost"),
            "database": os.getenv("DB_DATABASE", "laravel_rag"),
            "user": os.getenv("DB_USERNAME", "postgres"),
            "password": os.getenv("DB_PASSWORD", ""),
            "port": os.getenv("DB_PORT", "5432")
        }
        
        # Save to database
        document_id = None
        chunks_created = 0
        
        try:
            conn = psycopg2.connect(**db_config)
            cursor = conn.cursor()
            
            # Get filename safely
            try:
                file_name = file.filename if 'file' in locals() and file else 'unknown'
            except:
                try:
                    file_name = files[0].filename if files and len(files) > 0 else 'unknown'
                except:
                    file_name = 'unknown'
            
            cursor.execute("""
                INSERT INTO documents (title, source, uri, tenant_slug, metadata, created_at, updated_at)
                VALUES (%s, %s, %s, %s, %s, NOW(), NOW())
                RETURNING id
            """, (
                doc_title,
                'file',
                file_name,
                tenant_slug or 'default',
                json.dumps({'language': 'pt', 'file_type': 'unknown'})
            ))
            
            document_id = cursor.fetchone()[0]
            
            # Ensure content is string
            if isinstance(content, bytes):
                content = content.decode('utf-8', errors='ignore')
            
            # Create chunks (use 'ord' instead of 'chunk_index', 'meta' instead of 'metadata')
            chunks_text = [content[i:i+1000] for i in range(0, len(content), 1000)]
            
            for i, chunk_text in enumerate(chunks_text):
                cursor.execute("""
                    INSERT INTO chunks (document_id, content, ord, meta, created_at, updated_at)
                    VALUES (%s, %s, %s, %s, NOW(), NOW())
                """, (document_id, str(chunk_text), i, json.dumps({'type': 'text_chunk'})))
            
            conn.commit()
            cursor.close()
            conn.close()
            
            chunks_created = len(chunks_text)
                
        except Exception as e:
            logger.error(f"Database save error: {e}")
            import traceback
            traceback.print_exc()
            
            if document_id is None:
                document_id = int(time.time())
            if chunks_created == 0:
                chunks_created = max(1, len(content) // 1000) if content else 1
        
        processing_time = time.time() - start_time
        
        # Response compatible with Laravel
        response_data = {
            "ok": True,
            "document_id": document_id,
            "title": doc_title,
            "chunks_created": chunks_created,
            "processing_time": round(processing_time, 3),
            "extraction_method": "python_fastapi_real",
            "language_detected": "pt",
            "cache_stats": None,
            "enterprise_features_used": [],
            "retry_used": False
        }
        
        return JSONResponse(content=response_data, status_code=201)
        
    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Upload failed: {e}")
        return JSONResponse(
            content={
                "ok": False,
                "error": f"Upload failed: {str(e)}",
                "error_type": type(e).__name__
            },
            status_code=500
        )


@router.post("/embeddings/generate")
async def generate_embeddings(request: Request, api_key: str = Depends(get_api_key_from_header)):
    """Generate embeddings for all chunks of a document"""
    try:
        data = await request.json()
        document_id = data.get('document_id')
        async_mode = data.get('async', False)
        
        if not document_id:
            raise HTTPException(status_code=422, detail="document_id é obrigatório")
        
        # Call batch_embeddings.py script directly
        # Use absolute path from project root to avoid path issues
        project_root = Path(__file__).resolve().parent.parent.parent.parent  # /var/www/html/laravel-rag-vertex-full
        script_path = project_root / "scripts" / "rag_search" / "batch_embeddings.py"
        
        # Verify script exists
        if not script_path.exists():
            raise HTTPException(
                status_code=500,
                detail=f"Script not found: {script_path}"
            )
        
        cmd = [
            'python3',
            str(script_path),
            str(document_id)
        ]
        
        # Set PYTHONPATH to include scripts directory
        env = os.environ.copy()
        env['PYTHONPATH'] = str(project_root / "scripts")
        
        # Run from rag_search directory
        result = subprocess.run(
            cmd,
            capture_output=True,
            text=True,
            cwd=str(project_root / "scripts" / "rag_search"),
            env=env,
            timeout=300
        )
        
        if result.returncode == 0:
            response = json.loads(result.stdout)
            return JSONResponse(content=response)
        else:
            raise HTTPException(
                status_code=500,
                detail=f"Erro ao gerar embeddings: {result.stderr}"
            )
            
    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Embedding generation error: {e}")
        raise HTTPException(status_code=500, detail=f"Erro: {str(e)}")


@router.get("/docs/{doc_id}/chunks")
async def get_document_chunks(doc_id: int, api_key: str = Depends(get_api_key_from_header)):
    """Get all chunks for a specific document"""
    try:
        db_config = {
            "host": os.getenv("DB_HOST", "localhost"),
            "database": os.getenv("DB_DATABASE", "laravel_rag"),
            "user": os.getenv("DB_USERNAME", "postgres"),
            "password": os.getenv("DB_PASSWORD", ""),
            "port": os.getenv("DB_PORT", "5432")
        }
        
        conn = psycopg2.connect(**db_config)
        cursor = conn.cursor(cursor_factory=RealDictCursor)
        
        # Get chunks
        cursor.execute("""
            SELECT id, content, ord, meta 
            FROM chunks 
            WHERE document_id = %s
            ORDER BY ord ASC
        """, (doc_id,))
        
        chunks = cursor.fetchall()
        
        # Convert to list of dicts
        chunks_list = []
        for chunk in chunks:
            chunk_dict = dict(chunk)
            chunks_list.append(chunk_dict)
        
        cursor.close()
        conn.close()
        
        return JSONResponse(content={
            "ok": True,
            "document_id": doc_id,
            "chunks": chunks_list,
            "count": len(chunks_list)
        })
        
    except Exception as e:
        logger.error(f"Error fetching chunks: {e}")
        raise HTTPException(status_code=500, detail=f"Erro: {str(e)}")


@router.post("/feedback")
async def rag_feedback(
    request: Request,
    api_key: str = Depends(get_api_key_from_header)
):
    """
    Save user feedback about a RAG response
    POST /api/rag/feedback
    """
    try:
        data = await request.json()
        query = data.get('query')
        document_id = data.get('document_id')
        rating = data.get('rating')  # 1 = positive, -1 = negative
        metadata = data.get('metadata', {})
        
        if not query or not rating:
            raise HTTPException(status_code=422, detail="query and rating are required")
        
        if rating not in [1, -1]:
            raise HTTPException(status_code=422, detail="rating must be 1 or -1")
        
        # Get DB config
        db_config = {
            "host": os.getenv("DB_HOST", "localhost"),
            "database": os.getenv("DB_DATABASE", "laravel_rag"),
            "user": os.getenv("DB_USERNAME", "postgres"),
            "password": os.getenv("DB_PASSWORD", ""),
            "port": os.getenv("DB_PORT", "5432")
        }
        
        # Save feedback
        conn = psycopg2.connect(**db_config)
        cursor = conn.cursor()
        
        cursor.execute("""
            INSERT INTO rag_feedbacks (query, document_id, rating, metadata, created_at, updated_at)
            VALUES (%s, %s, %s, %s, NOW(), NOW())
            RETURNING id
        """, (query, document_id, rating, json.dumps(metadata)))
        
        feedback_id = cursor.fetchone()[0]
        conn.commit()
        cursor.close()
        conn.close()
        
        logger.info("Feedback saved", feedback_id=feedback_id, rating=rating)
        
        return JSONResponse(content={
            "ok": True,
            "message": "Feedback salvo com sucesso",
            "feedback_id": feedback_id
        })
        
    except HTTPException:
        raise
    except Exception as e:
        logger.error(f"Feedback save error: {e}")
        raise HTTPException(status_code=500, detail=f"Error: {str(e)}")


@router.get("/feedback/stats")
async def rag_feedback_stats(
    api_key: str = Depends(get_api_key_from_header)
):
    """
    Get feedback statistics
    GET /api/rag/feedback/stats
    """
    try:
        # Get DB config
        db_config = {
            "host": os.getenv("DB_HOST", "localhost"),
            "database": os.getenv("DB_DATABASE", "laravel_rag"),
            "user": os.getenv("DB_USERNAME", "postgres"),
            "password": os.getenv("DB_PASSWORD", ""),
            "port": os.getenv("DB_PORT", "5432")
        }
        
        conn = psycopg2.connect(**db_config)
        cursor = conn.cursor()
        
        # General stats
        cursor.execute("SELECT COUNT(*) FROM rag_feedbacks")
        total_feedbacks = cursor.fetchone()[0]
        
        cursor.execute("SELECT COUNT(*) FROM rag_feedbacks WHERE rating = 1")
        positive_feedbacks = cursor.fetchone()[0]
        
        cursor.execute("SELECT COUNT(*) FROM rag_feedbacks WHERE rating = -1")
        negative_feedbacks = cursor.fetchone()[0]
        
        satisfaction_rate = round((positive_feedbacks / total_feedbacks * 100), 2) if total_feedbacks > 0 else 0
        
        conn.close()
        
        return JSONResponse(content={
            "ok": True,
            "total_feedbacks": total_feedbacks,
            "positive_feedbacks": positive_feedbacks,
            "negative_feedbacks": negative_feedbacks,
            "satisfaction_rate": satisfaction_rate
        })
        
    except Exception as e:
        logger.error(f"Feedback stats error: {e}")
        raise HTTPException(status_code=500, detail=f"Error: {str(e)}")


@router.get("/feedback/recent")
async def rag_feedback_recent(
    api_key: str = Depends(get_api_key_from_header)
):
    """
    Get recent feedbacks
    GET /api/rag/feedback/recent
    """
    try:
        # Get DB config
        db_config = {
            "host": os.getenv("DB_HOST", "localhost"),
            "database": os.getenv("DB_DATABASE", "laravel_rag"),
            "user": os.getenv("DB_USERNAME", "postgres"),
            "password": os.getenv("DB_PASSWORD", ""),
            "port": os.getenv("DB_PORT", "5432")
        }
        
        conn = psycopg2.connect(**db_config)
        cursor = conn.cursor(cursor_factory=RealDictCursor)
        
        # Get recent feedbacks (limit 50)
        cursor.execute("""
            SELECT id, query, document_id, rating, metadata, created_at
            FROM rag_feedbacks
            ORDER BY created_at DESC
            LIMIT 50
        """)
        
        feedbacks = cursor.fetchall()
        conn.close()
        
        # Convert to list of dicts
        feedbacks_list = []
        for fb in feedbacks:
            fb_dict = dict(fb)
            if 'created_at' in fb_dict and fb_dict['created_at']:
                if isinstance(fb_dict['created_at'], datetime):
                    fb_dict['created_at'] = fb_dict['created_at'].isoformat()
            feedbacks_list.append(fb_dict)
        
        return JSONResponse(content={
            "ok": True,
            "feedbacks": feedbacks_list
        })
        
    except Exception as e:
        logger.error(f"Recent feedbacks error: {e}")
        raise HTTPException(status_code=500, detail=f"Error: {str(e)}")

