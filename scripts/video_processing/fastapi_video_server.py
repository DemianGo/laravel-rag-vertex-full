#!/usr/bin/env python3
"""
FastAPI server para processamento de vídeos YouTube
Usa o simple_transcriber.py que já funciona
"""

import json
import sys
import os
import subprocess
import re
from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
import uvicorn

app = FastAPI(title="Video Processing Server")

# Add CORS middleware
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

class VideoRequest(BaseModel):
    url: str
    tenant_slug: str = "user_1"

@app.get("/health")
async def health():
    return {"status": "ok", "message": "FastAPI video server is running"}

@app.post("/video/process")
async def process_video(request: VideoRequest):
    try:
        # Extract video ID
        video_id = extract_video_id(request.url)
        if not video_id:
            raise HTTPException(status_code=400, detail="Invalid YouTube URL")
        
        # Get transcription using simple_transcriber.py
        transcript = get_transcription(request.url)
        
        if transcript:
            return {
                "success": True,
                "message": "Video processed successfully",
                "video_id": video_id,
                "transcript": transcript,
                "transcript_length": len(transcript),
                "tenant_slug": request.tenant_slug
            }
        else:
            raise HTTPException(status_code=500, detail="Could not get transcription")
            
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Processing failed: {str(e)}")

def extract_video_id(url):
    """Extract video ID from YouTube URL"""
    patterns = [
        r'(?:https?://)?(?:www\.)?(?:m\.)?(?:youtube\.com|youtu\.be)/(?:watch\?v=|embed/|v/|)([\w-]{11})(?:\S+)?',
        r'(?:https?://)?(?:www\.)?(?:youtube\.com|youtu\.be)/shorts/([\w-]{11})'
    ]
    for pattern in patterns:
        match = re.search(pattern, url)
        if match:
            return match.group(1)
    return None

def get_transcription(url):
    """Get transcription using youtube-transcript-api directly"""
    try:
        from youtube_transcript_api import YouTubeTranscriptApi
        
        # Extract video ID
        video_id = extract_video_id(url)
        if not video_id:
            return None
        
        api = YouTubeTranscriptApi()
        
        # Try Portuguese first
        try:
            transcript = api.fetch(video_id, languages=['pt'])
            return ' '.join([entry.text for entry in transcript])
        except:
            pass
        
        # Try auto-generated Portuguese
        try:
            transcript = api.fetch(video_id, languages=['pt-auto'])
            return ' '.join([entry.text for entry in transcript])
        except:
            pass
        
        # Try English
        try:
            transcript = api.fetch(video_id, languages=['en'])
            return ' '.join([entry.text for entry in transcript])
        except:
            pass
        
        # Try auto-generated English
        try:
            transcript = api.fetch(video_id, languages=['en-auto'])
            return ' '.join([entry.text for entry in transcript])
        except:
            pass
        
        # Try any available transcript
        try:
            transcript = api.fetch(video_id)
            return ' '.join([entry.text for entry in transcript])
        except:
            pass
        
        return None
            
    except Exception as e:
        print(f"Error getting transcription: {e}")
        return None

if __name__ == '__main__':
    port = int(sys.argv[1]) if len(sys.argv) > 1 else 8001
    uvicorn.run(app, host="0.0.0.0", port=port)
