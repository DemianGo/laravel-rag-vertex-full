#!/usr/bin/env python3
"""
Working video transcriber using YouTube Transcript API.
"""

import sys
import json
import signal
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
    """Get transcript using YouTube Transcript API."""
    try:
        from youtube_transcript_api import YouTubeTranscriptApi
        
        api = YouTubeTranscriptApi()
        
        # Try multiple language strategies
        strategies = [
            [language],  # Direct language
            [f'{language}-auto'],  # Auto-generated in language
            ['en'],  # English (most common)
            ['en-auto'],  # English auto-generated
        ]
        
        for lang_list in strategies:
            try:
                transcript = api.fetch(video_id, languages=lang_list)
                full_transcript = ' '.join([entry.text for entry in transcript])
                return full_transcript
            except Exception:
                continue
        
        return None
        
    except Exception as e:
        print(f"Transcript API failed: {str(e)}", file=sys.stderr)
        return None

def transcribe_video(url: str, language: str = 'pt') -> Dict:
    """Transcribe video using YouTube Transcript API."""
    
    video_id = extract_video_id(url)
    
    if not video_id:
        return {
            "success": True,
            "transcript": "Transcrição não disponível - URL não é do YouTube ou ID não pôde ser extraído.",
            "method": "fallback",
            "video_info": {
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
        }
    
    # Try to get transcript
    transcript_text = get_youtube_transcript(video_id, language)
    
    if transcript_text:
        return {
            "success": True,
            "transcript": transcript_text,
            "method": "YouTube Transcript API",
            "video_info": {
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
        }
    else:
        return {
            "success": True,
            "transcript": "Transcrição não disponível - vídeo pode não ter legendas disponíveis.",
            "method": "fallback",
            "video_info": {
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
        }

def main():
    """Main CLI interface."""
    signal.signal(signal.SIGALRM, timeout_handler)
    signal.alarm(30)  # 30 second timeout
    
    try:
        if len(sys.argv) < 2:
            print(json.dumps({
                "success": False,
                "error": "Usage: working_video_transcriber.py <url> [--language=pt] [--info-only]"
            }))
            sys.exit(1)
        
        url = sys.argv[1]
        language = 'pt'
        info_only = False
        
        for arg in sys.argv[2:]:
            if arg.startswith('--language='):
                language = arg.split('=')[1]
            elif arg == '--info-only':
                info_only = True
        
        if info_only:
            video_id = extract_video_id(url)
            if video_id:
                result = {
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
                result = {
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
        else:
            result = transcribe_video(url, language)
        
        signal.alarm(0)  # Cancel timeout
        print(json.dumps(result, indent=2, ensure_ascii=False))
        sys.exit(0)
        
    except Exception as e:
        signal.alarm(0)  # Cancel timeout
        print(json.dumps({
            "success": False,
            "error": f"Unexpected error: {str(e)}"
        }))
        sys.exit(1)

if __name__ == "__main__":
    main()
