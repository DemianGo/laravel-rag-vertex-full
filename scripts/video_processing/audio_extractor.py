#!/usr/bin/env python3
"""
Audio Extractor from Video Files
Uses FFmpeg to extract audio from any video format.
"""

import sys
import os
import json
import subprocess
import tempfile
from pathlib import Path
from typing import Dict, Optional


class AudioExtractor:
    """Extract audio from video files using FFmpeg."""
    
    SUPPORTED_VIDEO_FORMATS = [
        '.mp4', '.avi', '.mov', '.mkv', '.flv', '.wmv', 
        '.webm', '.m4v', '.mpg', '.mpeg', '.3gp', '.ogv'
    ]
    
    def __init__(self):
        self.ffmpeg_path = self._find_ffmpeg()
    
    def _find_ffmpeg(self) -> Optional[str]:
        """Find FFmpeg installation."""
        try:
            result = subprocess.run(['which', 'ffmpeg'], 
                                    capture_output=True, 
                                    text=True, 
                                    check=True)
            return result.stdout.strip()
        except subprocess.CalledProcessError:
            return None
    
    def extract_audio(self, video_path: str, output_path: Optional[str] = None) -> Dict:
        """
        Extract audio from video file.
        
        Args:
            video_path: Path to video file
            output_path: Optional output path for audio file
            
        Returns:
            {
                "success": bool,
                "audio_path": str,
                "duration": float,
                "format": str,
                "size_bytes": int,
                "error": str (if failed)
            }
        """
        try:
            # Check if FFmpeg is available
            if not self.ffmpeg_path:
                return {
                    "success": False,
                    "error": "FFmpeg not found. Please install FFmpeg."
                }
            
            # Check if video file exists
            video_path = Path(video_path)
            if not video_path.exists():
                return {
                    "success": False,
                    "error": f"Video file not found: {video_path}"
                }
            
            # Check if file is a video
            if video_path.suffix.lower() not in self.SUPPORTED_VIDEO_FORMATS:
                return {
                    "success": False,
                    "error": f"Unsupported video format: {video_path.suffix}"
                }
            
            # Determine output path
            if output_path:
                audio_path = Path(output_path)
            else:
                # Create temp file with .mp3 extension
                temp_file = tempfile.NamedTemporaryFile(
                    suffix='.mp3', 
                    delete=False,
                    dir=video_path.parent
                )
                audio_path = Path(temp_file.name)
                temp_file.close()
            
            # Extract audio using FFmpeg
            # -i: input file
            # -vn: no video
            # -acodec: audio codec (mp3)
            # -ar: audio sample rate (44100 Hz)
            # -ac: audio channels (2 = stereo)
            # -ab: audio bitrate (192k)
            cmd = [
                self.ffmpeg_path,
                '-i', str(video_path),
                '-vn',                    # No video
                '-acodec', 'libmp3lame',  # MP3 codec
                '-ar', '44100',           # Sample rate
                '-ac', '2',               # Stereo
                '-ab', '192k',            # Bitrate
                '-y',                     # Overwrite output
                str(audio_path)
            ]
            
            # Run FFmpeg
            result = subprocess.run(
                cmd,
                capture_output=True,
                text=True,
                timeout=600  # 10 minutes max
            )
            
            if result.returncode != 0:
                return {
                    "success": False,
                    "error": f"FFmpeg failed: {result.stderr[:500]}"
                }
            
            # Get audio metadata
            duration = self._get_audio_duration(str(audio_path))
            size_bytes = audio_path.stat().st_size
            
            return {
                "success": True,
                "audio_path": str(audio_path),
                "duration": duration,
                "format": "mp3",
                "size_bytes": size_bytes,
                "bitrate": "192k",
                "sample_rate": "44100",
                "channels": 2
            }
            
        except subprocess.TimeoutExpired:
            return {
                "success": False,
                "error": "FFmpeg timeout (video too long or processing too slow)"
            }
        except Exception as e:
            return {
                "success": False,
                "error": f"Extraction failed: {str(e)}"
            }
    
    def _get_audio_duration(self, audio_path: str) -> float:
        """Get audio duration using FFmpeg."""
        try:
            cmd = [
                self.ffmpeg_path,
                '-i', audio_path,
                '-f', 'null',
                '-'
            ]
            
            result = subprocess.run(
                cmd,
                capture_output=True,
                text=True
            )
            
            # Parse duration from FFmpeg output
            # Format: "Duration: 00:05:30.12"
            for line in result.stderr.split('\n'):
                if 'Duration:' in line:
                    time_str = line.split('Duration:')[1].split(',')[0].strip()
                    h, m, s = time_str.split(':')
                    duration = int(h) * 3600 + int(m) * 60 + float(s)
                    return duration
            
            return 0.0
        except:
            return 0.0
    
    def is_video_file(self, file_path: str) -> bool:
        """Check if file is a supported video format."""
        return Path(file_path).suffix.lower() in self.SUPPORTED_VIDEO_FORMATS


def main():
    """CLI interface."""
    if len(sys.argv) < 2:
        print(json.dumps({
            "success": False,
            "error": "Usage: audio_extractor.py <video_path> [output_path]"
        }))
        sys.exit(1)
    
    video_path = sys.argv[1]
    output_path = sys.argv[2] if len(sys.argv) > 2 else None
    
    extractor = AudioExtractor()
    result = extractor.extract_audio(video_path, output_path)
    
    print(json.dumps(result, indent=2))
    sys.exit(0 if result['success'] else 1)


if __name__ == "__main__":
    main()


