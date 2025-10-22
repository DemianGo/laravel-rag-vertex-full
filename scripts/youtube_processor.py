#!/usr/bin/env python3
"""
YouTube Video Processor
Handles metadata fetching and audio extraction from YouTube videos
"""

import json
import sys
import os
import subprocess
import tempfile
import signal
from typing import Dict, Any, Optional

class TimeoutException(Exception):
    pass

def timeout_handler(signum, frame):
    raise TimeoutException("Operation timed out")

def extract_video_info(video_id: str) -> Dict[str, Any]:
    """Extract video metadata using yt-dlp"""
    try:
        cmd = [
            'yt-dlp',
            '--dump-json',
            '--no-download',
            f'https://www.youtube.com/watch?v={video_id}'
        ]
        
        result = subprocess.run(cmd, capture_output=True, text=True, timeout=120)
        
        if result.returncode != 0:
            return {
                'success': False,
                'error': f'yt-dlp failed: {result.stderr}'
            }
        
        data = json.loads(result.stdout)
        
        return {
            'success': True,
            'data': {
                'title': data.get('title', ''),
                'duration': data.get('duration', 0),
                'uploader': data.get('uploader', ''),
                'description': data.get('description', ''),
                'upload_date': data.get('upload_date', ''),
            }
        }
    except subprocess.TimeoutExpired:
        return {
            'success': False,
            'error': 'Video info extraction timed out'
        }
    except json.JSONDecodeError:
        return {
            'success': False,
            'error': 'Invalid JSON response from yt-dlp'
        }
    except Exception as e:
        return {
            'success': False,
            'error': f'Video info extraction failed: {str(e)}'
        }

def download_audio(video_id: str, output_path: str) -> Dict[str, Any]:
    """Download audio from YouTube video"""
    try:
        # Create output directory if it doesn't exist
        os.makedirs(os.path.dirname(output_path), exist_ok=True)
        
        cmd = [
            'yt-dlp',
            '-x',  # Extract audio
            '--audio-format', 'mp3',
            '--audio-quality', '128K',
            '--output', output_path,
            '--no-playlist',
            '--quiet',
            f'https://www.youtube.com/watch?v={video_id}'
        ]
        
        # Set up timeout
        signal.signal(signal.SIGALRM, timeout_handler)
        signal.alarm(600)  # 10 minutes timeout
        
        try:
            result = subprocess.run(cmd, capture_output=True, text=True, timeout=600)
            signal.alarm(0)  # Cancel timeout
            
            if result.returncode != 0:
                return {
                    'success': False,
                    'error': f'Audio download failed: {result.stderr}'
                }
            
            # Check if file was created
            if not os.path.exists(output_path):
                return {
                    'success': False,
                    'error': 'Audio file was not created'
                }
            
            return {
                'success': True,
                'data': {
                    'file_path': output_path,
                    'file_size': os.path.getsize(output_path)
                }
            }
            
        except TimeoutException:
            return {
                'success': False,
                'error': 'Audio download timed out'
            }
        finally:
            signal.alarm(0)  # Ensure timeout is cancelled
            
    except Exception as e:
        return {
            'success': False,
            'error': f'Audio download failed: {str(e)}'
        }

def main():
    if len(sys.argv) < 3:
        print(json.dumps({
            'success': False,
            'error': 'Usage: python youtube_processor.py <command> <video_id> [output_path]'
        }))
        sys.exit(1)
    
    command = sys.argv[1]
    video_id = sys.argv[2]
    
    if command == 'info':
        result = extract_video_info(video_id)
    elif command == 'download':
        if len(sys.argv) < 4:
            print(json.dumps({
                'success': False,
                'error': 'Output path required for download command'
            }))
            sys.exit(1)
        
        output_path = sys.argv[3]
        result = download_audio(video_id, output_path)
    else:
        result = {
            'success': False,
            'error': f'Unknown command: {command}'
        }
    
    print(json.dumps(result, indent=2, ensure_ascii=False))

if __name__ == '__main__':
    main()
