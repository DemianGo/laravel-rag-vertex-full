"""
Video Router
Sistema de processamento de vídeos para FastAPI
"""

from fastapi import APIRouter, HTTPException, Depends, status
from pydantic import BaseModel
from typing import Optional, Dict, Any
import subprocess
import json
import os
import sys
from pathlib import Path

# Adicionar scripts ao path
sys.path.append(str(Path(__file__).parent.parent / "scripts"))

from routers.auth import get_current_user

router = APIRouter()

class VideoProcessRequest(BaseModel):
    url: str
    title: Optional[str] = None

class VideoProcessResponse(BaseModel):
    success: bool
    video_id: str
    status: str
    message: str
    transcription: Optional[str] = None

@router.get("/health")
async def video_health():
    """Health check do processamento de vídeos"""
    return {
        "status": "healthy",
        "service": "FastAPI Video Processing"
    }

@router.post("/process", response_model=VideoProcessResponse)
async def process_video(request: VideoProcessRequest, current_user: dict = Depends(get_current_user)):
    """Processar vídeo do YouTube"""
    try:
        # Verificar se o script existe
        script_path = Path(__file__).parent.parent / "scripts" / "video_processing" / "simple_transcriber.py"
        
        if not script_path.exists():
            raise HTTPException(
                status_code=status.HTTP_503_SERVICE_UNAVAILABLE,
                detail="Script de transcrição não encontrado"
            )
        
        # Executar transcrição
        cmd = [
            "python3",
            str(script_path),
            request.url
        ]
        
        result = subprocess.run(
            cmd,
            capture_output=True,
            text=True,
            timeout=300  # 5 minutos timeout
        )
        
        if result.returncode != 0:
            raise HTTPException(
                status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
                detail=f"Erro na transcrição: {result.stderr}"
            )
        
        # Parse do resultado
        try:
            output = json.loads(result.stdout)
        except json.JSONDecodeError:
            # Se não for JSON, usar como texto simples
            output = {"transcription": result.stdout}
        
        return VideoProcessResponse(
            success=True,
            video_id=request.url.split("=")[-1] if "=" in request.url else "unknown",
            status="completed",
            message="Vídeo processado com sucesso",
            transcription=output.get("transcription", result.stdout)
        )
        
    except subprocess.TimeoutExpired:
        raise HTTPException(
            status_code=status.HTTP_408_REQUEST_TIMEOUT,
            detail="Timeout no processamento do vídeo"
        )
    except Exception as e:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Erro no processamento: {str(e)}"
        )

@router.get("/status/{video_id}")
async def get_video_status(video_id: str, current_user: dict = Depends(get_current_user)):
    """Obter status do processamento de vídeo"""
    # Em produção, implementar sistema de jobs/queue
    return {
        "success": True,
        "video_id": video_id,
        "status": "completed",
        "message": "Vídeo processado"
    }

@router.get("/list")
async def list_videos(current_user: dict = Depends(get_current_user)):
    """Listar vídeos processados"""
    # Em produção, buscar do banco de dados
    return {
        "success": True,
        "videos": []
    }

@router.get("/quota")
async def get_video_quota(current_user: dict = Depends(get_current_user)):
    """Obter quota de vídeos"""
    plan = current_user.get('plan', 'free')
    
    quotas = {
        'free': {'videos_per_month': 0, 'max_duration': 0},
        'pro': {'videos_per_month': 10, 'max_duration': 60},
        'enterprise': {'videos_per_month': -1, 'max_duration': -1}
    }
    
    quota = quotas.get(plan, quotas['free'])
    
    return {
        "success": True,
        "quota": quota,
        "plan": plan
    }
