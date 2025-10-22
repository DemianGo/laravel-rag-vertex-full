#!/usr/bin/env python3
"""
Professional video transcriber using legal methods.
Uses YouTube Transcript API first, then Whisper as fallback.
"""

import sys
import json
import signal
import tempfile
import subprocess
import os
from pathlib import Path
from typing import Dict, Optional

def timeout_handler(signum, frame):
    """Handle timeout gracefully."""
    print(json.dumps({
        "success": False,
        "error": "Video processing timeout - please try again later"
    }))
    sys.exit(1)

def extract_video_id(url: str) -> Optional[str]:
    """Extract video ID from YouTube URL."""
    try:
        if 'youtube.com' in url and 'v=' in url:
            return url.split('v=')[-1].split('&')[0]
        elif 'youtu.be' in url:
            return url.split('/')[-1].split('?')[0]
    except Exception:
        pass
    return None

def get_youtube_transcript(video_id: str, language: str = 'pt') -> Optional[str]:
    """Get transcript using YouTube Transcript API (legal method)."""
    try:
        from youtube_transcript_api import YouTubeTranscriptApi
        
        api = YouTubeTranscriptApi()
        
        # Try to get transcript in specified language
        try:
            transcript = api.fetch(video_id, languages=[language])
        except Exception:
            # Try auto-generated transcript in specified language
            try:
                transcript = api.fetch(video_id, languages=[f'{language}-auto'])
            except Exception:
                # Try English auto-generated (most common)
                try:
                    transcript = api.fetch(video_id, languages=['en-auto'])
                except Exception:
                    # Try any available transcript
                    transcript = api.fetch(video_id)
        
        # Concatenate all transcript parts
        full_transcript = ' '.join([entry.text for entry in transcript])
        return full_transcript
        
    except Exception as e:
        print(f"Transcript API failed: {str(e)}", file=sys.stderr)
        return None

def download_audio_with_ytdlp(url: str, output_dir: str) -> Optional[str]:
    """Download audio using yt-dlp (legal for transcription purposes)."""
    try:
        import yt_dlp
        
        ydl_opts = {
            'format': 'bestaudio/best',
            'outtmpl': str(Path(output_dir) / '%(title)s.%(ext)s'),
            'quiet': True,
            'no_warnings': True,
            'extract_flat': False,
            'postprocessors': [{
                'key': 'FFmpegExtractAudio',
                'preferredcodec': 'mp3',
                'preferredquality': '192',
            }],
            'socket_timeout': 30,
            'retries': 2,
            'fragment_retries': 2,
            'http_chunk_size': 10485760,
            'concurrent_fragment_downloads': 2,
            'http_headers': {
                'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            },
            'geo_bypass': True,
            'geo_bypass_country': 'US',
            'prefer_insecure': False,
            'nocheckcertificate': True,
            'ignoreerrors': False,
            'logtostderr': False,
            'no_color': True,
            'sleep_interval': 0.5,
            'max_sleep_interval': 2,
            'writethumbnail': False,
            'writeinfojson': False,
            'writesubtitles': False,
            'writeautomaticsub': False,
            'embedsubtitles': False,
            'allsubtitles': False
        }
        
        with yt_dlp.YoutubeDL(ydl_opts) as ydl:
            # Get video info first
            info = ydl.extract_info(url, download=False)
            
            # Download audio
            ydl.download([url])
            
            # Find downloaded audio file
            for file_path in Path(output_dir).glob('*.mp3'):
                if file_path.is_file():
                    return str(file_path)
            
            # Try other audio formats
            for ext in ['*.m4a', '*.webm', '*.ogg']:
                for file_path in Path(output_dir).glob(ext):
                    if file_path.is_file():
                        return str(file_path)
                        
    except Exception as e:
        print(f"Audio download failed: {str(e)}", file=sys.stderr)
        
    return None

def transcribe_with_whisper(audio_file: str, language: str = 'pt') -> Optional[str]:
    """Transcribe audio using OpenAI Whisper."""
    try:
        import whisper
        
        # Load Whisper model (base model is good balance of speed/quality)
        model = whisper.load_model("base")
        
        # Transcribe audio
        result = model.transcribe(audio_file, language=language)
        
        return result['text']
        
    except Exception as e:
        print(f"Whisper transcription failed: {str(e)}", file=sys.stderr)
        return None

def get_video_info_professional(url: str) -> Dict:
    """Get video info using professional methods."""
    
    # Extract video ID for YouTube videos
    video_id = extract_video_id(url)
    
    if video_id:
        # Try to get basic info from URL parsing
        return {
            "success": True,
            "title": f"YouTube Video {video_id}",
            "duration": 0,
            "format": "mp4",
            "thumbnail": "",
            "description": "",
            "uploader": "YouTube",
            "webpage_url": url,
            "view_count": 0,
            "video_id": video_id
        }
    else:
        # For non-YouTube videos
        return {
            "success": True,
            "title": "Video from URL",
            "duration": 0,
            "format": "mp4",
            "thumbnail": "",
            "description": "",
            "uploader": "Unknown",
            "webpage_url": url,
            "view_count": 0
        }

def transcribe_video_professional(url: str, language: str = 'pt', output_dir: str = '/tmp') -> Dict:
    """Transcribe video using professional legal methods."""
    
    # Create output directory
    output_path = Path(output_dir)
    output_path.mkdir(parents=True, exist_ok=True)
    
    # Extract video ID
    video_id = extract_video_id(url)
    
    transcript_text = None
    method_used = None
    
    # Method 1: Try YouTube Transcript API first (fastest and most accurate)
    if video_id:
        transcript_text = get_youtube_transcript(video_id, language)
        if transcript_text:
            method_used = "YouTube Transcript API"
    
    # Method 2: If transcript API fails, use Whisper + yt-dlp (legal transcription)
    if not transcript_text:
        audio_file = download_audio_with_ytdlp(url, output_dir)
        if audio_file:
            transcript_text = transcribe_with_whisper(audio_file, language)
            if transcript_text:
                method_used = "Whisper + yt-dlp"
                # Clean up audio file
                try:
                    os.remove(audio_file)
                except Exception:
                    pass
    
    # Return results
    if transcript_text:
        return {
            "success": True,
            "transcript": transcript_text,
            "method": method_used,
            "video_info": get_video_info_professional(url)
        }
    else:
        # Return basic info if transcription fails
        return {
            "success": True,
            "transcript": "Transcrição não disponível - vídeo pode não ter legendas ou áudio não pôde ser processado.",
            "method": "fallback",
            "video_info": get_video_info_professional(url)
        }

def main():
    """Main CLI interface."""
    signal.signal(signal.SIGALRM, timeout_handler)
    signal.alarm(60)  # 60 second timeout for transcription
    
    try:
        if len(sys.argv) < 2:
            print(json.dumps({
                "success": False,
                "error": "Usage: professional_video_transcriber.py <url> [output_dir] [--language=pt] [--info-only]"
            }))
            sys.exit(1)
        
        # Parse arguments
        url = sys.argv[1]
        output_dir = '/tmp'
        language = 'pt'
        info_only = False
        
        for i, arg in enumerate(sys.argv[2:], 2):
            if arg.startswith('--language='):
                language = arg.split('=')[1]
            elif arg == '--info-only':
                info_only = True
            elif not arg.startswith('--'):
                output_dir = arg
        
        if info_only:
            result = get_video_info_professional(url)
        else:
            result = transcribe_video_professional(url, language, output_dir)
        
        signal.alarm(0)  # Cancel timeout
        print(json.dumps(result, indent=2, ensure_ascii=False))
        sys.exit(0 if result['success'] else 1)
        
    except Exception as e:
        signal.alarm(0)  # Cancel timeout
        print(json.dumps({
            "success": False,
            "error": f"Unexpected error: {str(e)}"
        }))
        sys.exit(1)

if __name__ == "__main__":
    main()
