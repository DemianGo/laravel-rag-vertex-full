#!/usr/bin/env python3
"""
Servidor Python simples para processamento de vídeos YouTube
Usa o simple_transcriber.py que já funciona
"""

import json
import sys
import os
import subprocess
from http.server import HTTPServer, BaseHTTPRequestHandler
from urllib.parse import urlparse, parse_qs
import re

class SimpleVideoHandler(BaseHTTPRequestHandler):
    def do_OPTIONS(self):
        """Handle CORS preflight requests"""
        self.send_response(200)
        self.send_header('Access-Control-Allow-Origin', '*')
        self.send_header('Access-Control-Allow-Methods', 'POST, GET, OPTIONS')
        self.send_header('Access-Control-Allow-Headers', 'Content-Type')
        self.end_headers()
    
    def do_POST(self):
        """Handle video processing requests"""
        if self.path == '/video/process':
            self.handle_video_process()
        else:
            self.send_error(404)
    
    def do_GET(self):
        """Handle health check"""
        if self.path == '/health':
            self.send_json_response({'status': 'ok', 'message': 'Simple video server is running'})
        else:
            self.send_error(404)
    
    def handle_video_process(self):
        """Process YouTube video and return transcription"""
        try:
            # Read request body
            content_length = int(self.headers['Content-Length'])
            post_data = self.rfile.read(content_length)
            data = json.loads(post_data.decode('utf-8'))
            
            url = data.get('url')
            tenant_slug = data.get('tenant_slug', 'user_1')
            
            if not url:
                self.send_json_response({'error': 'URL is required'}, 400)
                return
            
            # Extract video ID
            video_id = self.extract_video_id(url)
            if not video_id:
                self.send_json_response({'error': 'Invalid YouTube URL'}, 400)
                return
            
            # Get transcription using simple_transcriber.py
            transcript = self.get_transcription(url)
            
            if transcript:
                # Return success response with transcript
                self.send_json_response({
                    'success': True,
                    'message': 'Video processed successfully',
                    'video_id': video_id,
                    'transcript': transcript,
                    'transcript_length': len(transcript),
                    'tenant_slug': tenant_slug
                })
            else:
                self.send_json_response({'error': 'Could not get transcription'}, 500)
                
        except Exception as e:
            self.send_json_response({'error': f'Processing failed: {str(e)}'}, 500)
    
    def extract_video_id(self, url):
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
    
    def get_transcription(self, url):
        """Get transcription using simple_transcriber.py"""
        try:
            # Get the directory of this script
            script_dir = os.path.dirname(os.path.abspath(__file__))
            script_path = os.path.join(script_dir, 'simple_transcriber.py')
            
            # Run the script
            result = subprocess.run([
                'python3', script_path, url
            ], capture_output=True, text=True, timeout=30)
            
            if result.returncode == 0:
                data = json.loads(result.stdout)
                if data.get('success') and data.get('transcript'):
                    return data['transcript']
                else:
                    print(f"Script failed: {data.get('error', 'Unknown error')}")
                    return None
            else:
                print(f"Script failed with return code {result.returncode}: {result.stderr}")
                return None
                
        except Exception as e:
            print(f"Error getting transcription: {e}")
            return None
    
    def send_json_response(self, data, status_code=200):
        """Send JSON response"""
        self.send_response(status_code)
        self.send_header('Content-Type', 'application/json')
        self.send_header('Access-Control-Allow-Origin', '*')
        self.end_headers()
        self.wfile.write(json.dumps(data, ensure_ascii=False).encode('utf-8'))

def run_server(port=8001):
    """Run the simple video processing server"""
    server = HTTPServer(('0.0.0.0', port), SimpleVideoHandler)
    print(f"Simple video processing server running on port {port}")
    server.serve_forever()

if __name__ == '__main__':
    port = int(sys.argv[1]) if len(sys.argv) > 1 else 8001
    run_server(port)
