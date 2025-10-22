#!/usr/bin/env python3
"""
Servidor Python standalone para processamento de vídeos YouTube
Funciona independente do Laravel, processa vídeos rapidamente
"""

import json
import sys
import os
import re
from http.server import HTTPServer, BaseHTTPRequestHandler
from urllib.parse import urlparse, parse_qs
import threading
import time
import psycopg2
from youtube_transcript_api import YouTubeTranscriptApi

# Configuração do banco PostgreSQL
DB_CONFIG = {
    'host': '127.0.0.1',
    'port': 5432,
    'database': 'laravel_rag',
    'user': 'postgres',
    'password': 'senhasegura123'
}

class VideoHandler(BaseHTTPRequestHandler):
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
            self.send_json_response({'status': 'ok', 'message': 'Video server is running'})
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
            
            # Get transcription
            transcript = self.get_transcription(video_id)
            
            if transcript:
                # Save to database
                document_id = self.save_to_database(url, video_id, transcript, tenant_slug)
                
                # Return success response
                self.send_json_response({
                    'success': True,
                    'message': 'Video processed successfully',
                    'video_id': video_id,
                    'document_id': document_id,
                    'transcript': transcript,
                    'transcript_length': len(transcript)
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
    
    def get_transcription(self, video_id):
        """Get transcription using the working simple_transcriber.py script"""
        print(f"Getting transcription for video_id: {video_id}")
        try:
            import subprocess
            import os
            
            # Use the working script
            script_path = os.path.join(os.path.dirname(__file__), 'simple_transcriber.py')
            url = f"https://www.youtube.com/watch?v={video_id}"
            
            print(f"Running script: {script_path} with URL: {url}")
            result = subprocess.run([
                'python3', script_path, url
            ], capture_output=True, text=True, timeout=30)
            
            if result.returncode == 0:
                import json
                data = json.loads(result.stdout)
                if data.get('success') and data.get('transcript'):
                    print(f"Transcript found: {len(data['transcript'])} characters")
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
    
    def save_to_database(self, url, video_id, transcript, tenant_slug):
        """Save video data to PostgreSQL database"""
        try:
            conn = psycopg2.connect(**DB_CONFIG)
            cursor = conn.cursor()
            
            # Insert document
            cursor.execute("""
                INSERT INTO documents (title, source, uri, tenant_slug, metadata, created_at, updated_at)
                VALUES (%s, %s, %s, %s, %s, NOW(), NOW())
                RETURNING id
            """, (
                f"YouTube Video: {video_id}",
                'video_url',
                url,
                tenant_slug,
                json.dumps({
                    'video_id': video_id,
                    'transcript_length': len(transcript),
                    'method': 'python_server'
                })
            ))
            
            document_id = cursor.fetchone()[0]
            
            # Create chunks from transcript
            chunks = [transcript[i:i+1000] for i in range(0, len(transcript), 1000)]
            for i, chunk in enumerate(chunks):
                cursor.execute("""
                    INSERT INTO chunks (document_id, content, chunk_index, metadata, created_at, updated_at)
                    VALUES (%s, %s, %s, %s, NOW(), NOW())
                """, (
                    document_id,
                    chunk,
                    i,
                    json.dumps({'type': 'video_transcript'})
                ))
            
            conn.commit()
            cursor.close()
            conn.close()
            
            return document_id
            
        except Exception as e:
            print(f"Error saving to database: {e}")
            return None
    
    def send_json_response(self, data, status_code=200):
        """Send JSON response"""
        self.send_response(status_code)
        self.send_header('Content-Type', 'application/json')
        self.send_header('Access-Control-Allow-Origin', '*')
        self.end_headers()
        self.wfile.write(json.dumps(data, ensure_ascii=False).encode('utf-8'))

def run_server(port=8001):
    """Run the video processing server"""
    server = HTTPServer(('0.0.0.0', port), VideoHandler)
    print(f"Video processing server running on port {port}")
    server.serve_forever()

if __name__ == '__main__':
    port = int(sys.argv[1]) if len(sys.argv) > 1 else 8001
    run_server(port)
