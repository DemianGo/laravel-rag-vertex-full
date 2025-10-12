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
                
                file_path = output_dir / f"{info['title']}.{file_ext}"
                
                # Verify file exists
                if not file_path.exists():
                    # Try to find the downloaded file
                    possible_files = list(output_dir.glob(f"{info['title']}.*"))
                    if possible_files:
                        file_path = possible_files[0]
                    else:
                        return {
                            "success": False,
                            "error": "Downloaded file not found"
                        }
                
                return {
                    "success": True,
                    "file_path": str(file_path),
                    "title": info.get('title', 'Unknown'),
                    "duration": info.get('duration', 0),
                    "format": file_ext,
                    "size_bytes": file_path.stat().st_size,
                    "thumbnail": info.get('thumbnail', ''),
                    "description": info.get('description', '')[:500],  # Limit to 500 chars
                    "uploader": info.get('uploader', 'Unknown'),
                    "webpage_url": info.get('webpage_url', url),
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
            "error": "Usage: video_downloader.py <url> [output_dir] [--audio-only]"
        }))
        sys.exit(1)
    
    url = sys.argv[1]
    output_dir = sys.argv[2] if len(sys.argv) > 2 and not sys.argv[2].startswith('--') else None
    audio_only = '--audio-only' in sys.argv
    
    if not YT_DLP_AVAILABLE:
        print(json.dumps({
            "success": False,
            "error": "yt-dlp not installed. Install with: pip install yt-dlp"
        }))
        sys.exit(1)
    
    downloader = VideoDownloader()
    result = downloader.download_video(url, output_dir, audio_only)
    
    print(json.dumps(result, indent=2, ensure_ascii=False))
    sys.exit(0 if result['success'] else 1)


if __name__ == "__main__":
    main()


