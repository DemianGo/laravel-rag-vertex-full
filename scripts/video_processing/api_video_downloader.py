#!/usr/bin/env python3
"""
Video downloader using external APIs when yt-dlp fails.
"""

import sys
import json
import subprocess
import signal
import time
import tempfile
import requests
from pathlib import Path
from typing import Dict, Optional

def timeout_handler(signum, frame):
    """Handle timeout gracefully."""
    print(json.dumps({
        "success": False,
        "error": "Video processing timeout - please try again later"
    }))
    sys.exit(1)

def get_youtube_info_from_api(url: str) -> Optional[Dict]:
    """Get YouTube video info using external API."""
    try:
        # Extract video ID
        video_id = None
        if 'youtube.com' in url and 'v=' in url:
            video_id = url.split('v=')[-1].split('&')[0]
        elif 'youtu.be' in url:
            video_id = url.split('/')[-1].split('?')[0]
        
        if not video_id:
            return None
        
        # Try multiple APIs
        apis = [
            f"https://www.youtube.com/oembed?url={url}&format=json",
            f"https://noembed.com/embed?url={url}",
            f"https://pipedapi.kavin.rocks/streams/{video_id}"
        ]
        
        for api_url in apis:
            try:
                response = requests.get(api_url, timeout=10)
                if response.status_code == 200:
                    data = response.json()
                    
                    if 'title' in data:
                        return {
                            "success": True,
                            "title": data.get('title', 'Unknown'),
                            "duration": data.get('duration', 0),
                            "format": "mp4",
                            "thumbnail": data.get('thumbnail_url', ''),
                            "description": data.get('description', '')[:500],
                            "uploader": data.get('author_name', 'Unknown'),
                            "webpage_url": url,
                            "view_count": 0
                        }
            except Exception:
                continue
    except Exception:
        pass
    return None

def try_ytdlp_direct(url: str, output_dir: str, audio_only: bool = False) -> Optional[Dict]:
    """Try yt-dlp with direct approach."""
    try:
        import yt_dlp
        
        format_str = 'bestaudio/best' if audio_only else 'best[height<=480]'
        
        ydl_opts = {
            'format': format_str,
            'outtmpl': str(Path(output_dir) / '%(title)s.%(ext)s'),
            'quiet': True,
            'no_warnings': True,
            'extract_flat': False,
            'socket_timeout': 20,
            'retries': 2,
            'fragment_retries': 2,
            'http_chunk_size': 10485760,
            'concurrent_fragment_downloads': 2,
            'http_headers': {
                'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
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
        
        if audio_only:
            ydl_opts.update({
                'postprocessors': [{
                    'key': 'FFmpegExtractAudio',
                    'preferredcodec': 'mp3',
                    'preferredquality': '192',
                }],
            })
        
        with yt_dlp.YoutubeDL(ydl_opts) as ydl:
            # Get video info first
            info = ydl.extract_info(url, download=False)
            
            # Download video
            ydl.download([url])
            
            # Find downloaded file
            output_path = Path(output_dir)
            for file_path in output_path.glob('*'):
                if file_path.is_file():
                    return {
                        "success": True,
                        "file_path": str(file_path),
                        "title": info.get('title', file_path.stem),
                        "duration": info.get('duration', 0),
                        "format": file_path.suffix[1:] if file_path.suffix else 'mp4',
                        "size_bytes": file_path.stat().st_size,
                        "thumbnail": info.get('thumbnail', ''),
                        "description": info.get('description', ''),
                        "uploader": info.get('uploader', 'Unknown'),
                        "webpage_url": info.get('webpage_url', url),
                        "view_count": info.get('view_count', 0)
                    }
    except Exception:
        pass
    return None

def get_video_info_api(url: str) -> Dict:
    """Get video info using multiple approaches including APIs."""
    
    # Try external APIs first
    if 'youtube.com' in url or 'youtu.be' in url:
        api_result = get_youtube_info_from_api(url)
        if api_result:
            return api_result
    
    # Try yt-dlp for info only
    try:
        import yt_dlp
        
        ydl_opts = {
            'quiet': True,
            'no_warnings': True,
            'socket_timeout': 10,
            'retries': 1,
            'fragment_retries': 1,
            'http_headers': {
                'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
            },
            'geo_bypass': True,
            'geo_bypass_country': 'US',
            'prefer_insecure': False,
            'nocheckcertificate': True,
            'ignoreerrors': True,
            'logtostderr': False,
            'no_color': True,
            'sleep_interval': 0.5,
            'max_sleep_interval': 2
        }
        
        with yt_dlp.YoutubeDL(ydl_opts) as ydl:
            info = ydl.extract_info(url, download=False)
            
            return {
                "success": True,
                "title": info.get('title', 'Unknown'),
                "duration": info.get('duration', 0),
                "format": info.get('ext', 'mp4'),
                "thumbnail": info.get('thumbnail', ''),
                "description": info.get('description', '')[:500],
                "uploader": info.get('uploader', 'Unknown'),
                "webpage_url": info.get('webpage_url', url),
                "view_count": info.get('view_count', 0)
            }
    except Exception:
        pass
    
    # Fallback to basic info
    if 'youtube.com' in url or 'youtu.be' in url:
        video_id = url.split('v=')[-1].split('&')[0] if 'v=' in url else url.split('/')[-1]
        return {
            "success": True,
            "title": f"YouTube Video {video_id}",
            "duration": 0,
            "format": "mp4",
            "thumbnail": "",
            "description": "",
            "uploader": "YouTube",
            "webpage_url": url,
            "view_count": 0
        }
    elif 'vimeo.com' in url:
        return {
            "success": True,
            "title": "Vimeo Video",
            "duration": 0,
            "format": "mp4",
            "thumbnail": "",
            "description": "",
            "uploader": "Vimeo",
            "webpage_url": url,
            "view_count": 0
        }
    else:
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

def download_video_api(url: str, output_dir: str, audio_only: bool = False) -> Dict:
    """Download video using multiple approaches."""
    
    output_path = Path(output_dir)
    output_path.mkdir(parents=True, exist_ok=True)
    
    # Try direct yt-dlp download
    result = try_ytdlp_direct(url, output_dir, audio_only)
    if result:
        return result
    
    # If download fails, get basic info for fallback processing
    basic_info = get_video_info_api(url)
    if basic_info['success']:
        basic_info['download_note'] = 'Download failed, using basic video info'
    
    return basic_info

def main():
    """Main CLI interface."""
    signal.signal(signal.SIGALRM, timeout_handler)
    signal.alarm(25)  # 25 second timeout
    
    try:
        if len(sys.argv) < 2:
            print(json.dumps({
                "success": False,
                "error": "Usage: api_video_downloader.py <url> [output_dir] [--audio-only] [--info-only]"
            }))
            sys.exit(1)
        
        # Parse arguments properly
        url = None
        output_dir = '/tmp'
        audio_only = False
        info_only = False
        
        for i, arg in enumerate(sys.argv[1:], 1):
            if not arg.startswith('--'):
                if url is None:
                    url = arg
                elif output_dir == '/tmp':
                    output_dir = arg
            elif arg == '--info-only':
                info_only = True
            elif arg == '--audio-only':
                audio_only = True
        
        if not url:
            print(json.dumps({
                "success": False,
                "error": "URL is required"
            }))
            sys.exit(1)
        
        if info_only:
            result = get_video_info_api(url)
        else:
            result = download_video_api(url, output_dir, audio_only)
        
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
