#!/usr/bin/env python3
"""
Universal Video Downloader
Uses yt-dlp to download videos from 1000+ websites.
Supports: YouTube, Vimeo, Dailymotion, Facebook, Instagram, TikTok, etc.
"""

import sys
import os
import json
import tempfile
from pathlib import Path
from typing import Dict, Optional

try:
    import yt_dlp
    YT_DLP_AVAILABLE = True
except ImportError:
    YT_DLP_AVAILABLE = False


class VideoDownloader:
    """Universal video downloader using yt-dlp."""
    
    def __init__(self):
        if not YT_DLP_AVAILABLE:
            raise ImportError("yt-dlp not installed. Install with: pip install yt-dlp")
    
    def download_video(self, url: str, output_dir: Optional[str] = None, 
                       audio_only: bool = False) -> Dict:
        """
        Download video from URL.
        
        Args:
            url: Video URL (YouTube, Vimeo, etc)
            output_dir: Directory to save video
            audio_only: If True, downloads only audio (faster)
            
        Returns:
            {
                "success": bool,
                "file_path": str,
                "title": str,
                "duration": float,
                "format": str,
                "size_bytes": int,
                "thumbnail": str,
                "description": str,
                "uploader": str,
                "error": str (if failed)
            }
        """
        try:
            # Determine output directory
            if output_dir:
                output_dir = Path(output_dir)
            else:
                output_dir = Path(tempfile.gettempdir())
            
            output_dir.mkdir(parents=True, exist_ok=True)
            
            # Configure yt-dlp options
            ydl_opts = {
                'format': 'bestaudio/best' if audio_only else 'best',
                'outtmpl': str(output_dir / '%(title)s.%(ext)s'),
                'quiet': True,
                'no_warnings': True,
                'extract_flat': False,
            }
            
            # Add audio conversion if audio_only
            if audio_only:
                ydl_opts.update({
                    'postprocessors': [{
                        'key': 'FFmpegExtractAudio',
                        'preferredcodec': 'mp3',
                        'preferredquality': '192',
                    }],
                })
            
            # Download video
            with yt_dlp.YoutubeDL(ydl_opts) as ydl:
                # Get video info first
                info = ydl.extract_info(url, download=False)
                
                # Download video
                ydl.download([url])
                
                # Get downloaded file path
                if audio_only:
                    file_ext = 'mp3'
                else:
                    file_ext = info.get('ext', 'mp4')
                
                # Sanitize title for filename (yt-dlp does this automatically)
                title = info['title']
                file_path = output_dir / f"{title}.{file_ext}"
                
                # Verify file exists
                if not file_path.exists():
                    # Try to find the downloaded file (yt-dlp may sanitize filename)
                    possible_files = list(output_dir.glob(f"*{title[:20]}*.{file_ext}"))
                    if not possible_files:
                        # Try any file in the directory
                        possible_files = list(output_dir.glob(f"*.{file_ext}"))
                    
                    if possible_files:
                        # Get the most recent file
                        file_path = max(possible_files, key=lambda p: p.stat().st_mtime)
                    else:
                        return {
                            "success": False,
                            "error": "Downloaded file not found"
                        }
                
                # Clean strings to avoid UTF-8 issues
                def clean_string(s):
                    if not s:
                        return ''
                    # Remove problematic characters
                    return s.encode('utf-8', errors='ignore').decode('utf-8', errors='ignore')
                
                return {
                    "success": True,
                    "file_path": str(file_path),
                    "title": clean_string(info.get('title', 'Unknown')),
                    "duration": info.get('duration', 0),
                    "format": file_ext,
                    "size_bytes": file_path.stat().st_size,
                    "thumbnail": clean_string(info.get('thumbnail', '')),
                    "description": clean_string(info.get('description', ''))[:500],  # Limit to 500 chars
                    "uploader": clean_string(info.get('uploader', 'Unknown')),
                    "webpage_url": clean_string(info.get('webpage_url', url)),
                    "view_count": info.get('view_count', 0)
                }
        
        except yt_dlp.utils.DownloadError as e:
            return {
                "success": False,
                "error": f"Download failed: {str(e)}"
            }
        except Exception as e:
            return {
                "success": False,
                "error": f"Unexpected error: {str(e)}"
            }
    
    def get_video_info(self, url: str) -> Dict:
        """
        Get video information without downloading.
        
        Args:
            url: Video URL
            
        Returns:
            Dict with video metadata
        """
        try:
            ydl_opts = {
                'quiet': True,
                'no_warnings': True,
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
                    "view_count": info.get('view_count', 0),
                    "webpage_url": info.get('webpage_url', url)
                }
        except Exception as e:
            return {
                "success": False,
                "error": f"Failed to get video info: {str(e)}"
            }
    
    def is_supported_url(self, url: str) -> bool:
        """Check if URL is supported by yt-dlp."""
        try:
            # List of common video platforms
            supported_domains = [
                'youtube.com', 'youtu.be',
                'vimeo.com',
                'dailymotion.com',
                'facebook.com', 'fb.watch',
                'instagram.com',
                'tiktok.com',
                'twitter.com', 'x.com',
                'twitch.tv',
                'reddit.com',
                'streamable.com',
                'bitchute.com',
                'rumble.com',
                'odysee.com'
            ]
            
            url_lower = url.lower()
            return any(domain in url_lower for domain in supported_domains)
        except:
            return False


def main():
    """CLI interface."""
    if len(sys.argv) < 2:
        print(json.dumps({
            "success": False,
            "error": "Usage: video_downloader.py <url> [output_dir] [--audio-only] [--info-only]"
        }))
        sys.exit(1)
    
    # Check for --info-only flag first
    info_only = '--info-only' in sys.argv
    
    # URL is always the first non-flag argument
    url = None
    output_dir = None
    for i, arg in enumerate(sys.argv[1:], 1):
        if not arg.startswith('--'):
            if url is None:
                url = arg
            elif output_dir is None:
                output_dir = arg
    
    audio_only = '--audio-only' in sys.argv
    
    if not url:
        print(json.dumps({
            "success": False,
            "error": "URL is required"
        }))
        sys.exit(1)
    
    if not YT_DLP_AVAILABLE:
        print(json.dumps({
            "success": False,
            "error": "yt-dlp not installed. Install with: pip install yt-dlp"
        }))
        sys.exit(1)
    
    downloader = VideoDownloader()
    
    # If info-only flag, just get video info without downloading
    if info_only:
        result = downloader.get_video_info(url)
    else:
        result = downloader.download_video(url, output_dir, audio_only)
    
    print(json.dumps(result, indent=2, ensure_ascii=False))
    sys.exit(0 if result['success'] else 1)


if __name__ == "__main__":
    main()


