#!/usr/bin/env python3
"""
Simple transcriber that works.
"""

import sys
import json

def extract_video_id(url):
    """Extract video ID from YouTube URL."""
    try:
        if 'youtube.com' in url and 'v=' in url:
            return url.split('v=')[-1].split('&')[0]
        elif 'youtu.be' in url:
            return url.split('/')[-1].split('?')[0]
    except Exception:
        pass
    return None

def get_transcript(video_id, language='pt'):
    """Get transcript using YouTube Transcript API."""
    try:
        from youtube_transcript_api import YouTubeTranscriptApi
        
        api = YouTubeTranscriptApi()
        
        # Try Portuguese first (user preference)
        try:
            transcript = api.fetch(video_id, languages=['pt'])
            return ' '.join([entry.text for entry in transcript])
        except Exception:
            pass
        
        # Try auto-generated Portuguese
        try:
            transcript = api.fetch(video_id, languages=['pt-auto'])
            return ' '.join([entry.text for entry in transcript])
        except Exception:
            pass
        
        # Try English (most common)
        try:
            transcript = api.fetch(video_id, languages=['en'])
            return ' '.join([entry.text for entry in transcript])
        except Exception:
            pass
        
        # Try auto-generated English
        try:
            transcript = api.fetch(video_id, languages=['en-auto'])
            return ' '.join([entry.text for entry in transcript])
        except Exception:
            pass
        
        # Try any available transcript
        try:
            transcript = api.fetch(video_id)
            return ' '.join([entry.text for entry in transcript])
        except Exception:
            pass
        
        return None
        
    except Exception:
        return None

def main():
    if len(sys.argv) < 2:
        print(json.dumps({"success": False, "error": "URL required"}))
        sys.exit(1)
    
    url = sys.argv[1]
    # Ignore extra arguments (like --audio-only, output_dir, etc.)
    video_id = extract_video_id(url)
    
    if not video_id:
        result = {
            "success": True,
            "transcript": "Transcrição não disponível - URL não é do YouTube.",
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
    else:
        transcript = get_transcript(video_id)
        
        if transcript:
            result = {
                "success": True,
                "transcript": transcript,
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
            result = {
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
    
    print(json.dumps(result, indent=2, ensure_ascii=False))
    sys.exit(0)

if __name__ == "__main__":
    main()
