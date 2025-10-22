#!/usr/bin/env python3
"""
Google Cloud Vision OCR Engine
Best-in-class OCR with 99%+ accuracy
"""

import sys
import json
import os
from pathlib import Path
from typing import Dict, Any, Optional

try:
    from google.cloud import vision
    from google.oauth2 import service_account
    VISION_AVAILABLE = True
except ImportError:
    VISION_AVAILABLE = False


class GoogleVisionOCR:
    """Google Cloud Vision OCR processor with maximum accuracy."""
    
    def __init__(self, credentials_path: Optional[str] = None, project_id: Optional[str] = None):
        """
        Initialize Google Vision OCR.
        
        Args:
            credentials_path: Path to service account JSON (optional if GOOGLE_APPLICATION_CREDENTIALS is set)
            project_id: Google Cloud project ID (optional)
        """
        if not VISION_AVAILABLE:
            raise ImportError("google-cloud-vision not installed. Run: pip install google-cloud-vision")
        
        # Try to get credentials from environment or parameter
        creds_path = credentials_path or os.getenv('GOOGLE_APPLICATION_CREDENTIALS')
        
        if creds_path and os.path.exists(creds_path):
            # Try to use explicit credentials file
            try:
                credentials = service_account.Credentials.from_service_account_file(creds_path)
                self.client = vision.ImageAnnotatorClient(credentials=credentials)
            except Exception as e:
                # If service account fails, try authorized user credentials
                print(f"Service account failed, trying authorized user: {e}")
                self.client = vision.ImageAnnotatorClient()
        else:
            # Use default credentials (gcloud auth)
            self.client = vision.ImageAnnotatorClient()
        
        self.project_id = project_id or os.getenv('GOOGLE_CLOUD_PROJECT', 'liberai-ai')
    
    def detect_text(self, image_path: str, language_hints: list = None) -> Dict[str, Any]:
        """
        Detect text in image using Google Cloud Vision OCR.
        
        Args:
            image_path: Path to image file
            language_hints: List of language codes (e.g., ['pt', 'en'])
        
        Returns:
            Dict with success, text, confidence, and metadata
        """
        try:
            # Read image
            with open(image_path, 'rb') as image_file:
                content = image_file.read()
            
            image = vision.Image(content=content)
            
            # Configure request
            image_context = None
            if language_hints:
                image_context = vision.ImageContext(language_hints=language_hints)
            
            # Perform OCR - DOCUMENT_TEXT_DETECTION for best results
            response = self.client.document_text_detection(
                image=image,
                image_context=image_context
            )
            
            if response.error.message:
                return {
                    'success': False,
                    'error': response.error.message,
                    'text': '',
                    'confidence': 0
                }
            
            # Extract full text
            full_text = response.full_text_annotation.text if response.full_text_annotation else ''
            
            # Calculate average confidence
            confidences = []
            words_data = []
            
            for page in response.full_text_annotation.pages:
                for block in page.blocks:
                    for paragraph in block.paragraphs:
                        for word in paragraph.words:
                            word_text = ''.join([symbol.text for symbol in word.symbols])
                            word_confidence = word.confidence if hasattr(word, 'confidence') else 0.0
                            
                            confidences.append(word_confidence)
                            words_data.append({
                                'text': word_text,
                                'confidence': word_confidence
                            })
            
            avg_confidence = (sum(confidences) / len(confidences) * 100) if confidences else 0.0
            
            # Detect orientation
            orientation = 'normal'
            if response.full_text_annotation.pages:
                page = response.full_text_annotation.pages[0]
                if hasattr(page, 'property') and hasattr(page.property, 'detected_break'):
                    orientation = page.property.detected_break.type_.name if page.property.detected_break else 'normal'
            
            return {
                'success': True,
                'text': full_text,
                'confidence': avg_confidence,
                'word_count': len(words_data),
                'char_count': len(full_text),
                'words': words_data[:100],  # First 100 words for debugging
                'orientation': orientation,
                'engine': 'google_cloud_vision',
                'language_detected': language_hints[0] if language_hints else 'auto'
            }
            
        except FileNotFoundError:
            return {
                'success': False,
                'error': f'Image file not found: {image_path}',
                'text': '',
                'confidence': 0
            }
        except Exception as e:
            return {
                'success': False,
                'error': f'Google Vision API error: {str(e)}',
                'text': '',
                'confidence': 0
            }
    
    def detect_document(self, image_path: str, language_hints: list = None) -> Dict[str, Any]:
        """
        Detect document structure (text + tables + blocks) using Google Cloud Vision.
        
        Args:
            image_path: Path to image file
            language_hints: List of language codes
        
        Returns:
            Dict with text, blocks, tables, and metadata
        """
        try:
            # Read image
            with open(image_path, 'rb') as image_file:
                content = image_file.read()
            
            image = vision.Image(content=content)
            
            # Configure request
            image_context = None
            if language_hints:
                image_context = vision.ImageContext(language_hints=language_hints)
            
            # Perform document detection
            response = self.client.document_text_detection(
                image=image,
                image_context=image_context
            )
            
            if response.error.message:
                return {
                    'success': False,
                    'error': response.error.message
                }
            
            # Extract structured data
            full_text = response.full_text_annotation.text if response.full_text_annotation else ''
            
            blocks = []
            paragraphs = []
            
            for page in response.full_text_annotation.pages:
                for block in page.blocks:
                    block_text = ''
                    block_confidence = 0.0
                    block_paragraphs = []
                    
                    for paragraph in block.paragraphs:
                        para_text = ''
                        for word in paragraph.words:
                            word_text = ''.join([symbol.text for symbol in word.symbols])
                            para_text += word_text + ' '
                        
                        block_text += para_text.strip() + '\n'
                        block_paragraphs.append(para_text.strip())
                        
                        if hasattr(paragraph, 'confidence'):
                            block_confidence += paragraph.confidence
                    
                    blocks.append({
                        'text': block_text.strip(),
                        'paragraphs': block_paragraphs,
                        'confidence': block_confidence / len(block.paragraphs) if block.paragraphs else 0.0
                    })
            
            return {
                'success': True,
                'text': full_text,
                'blocks': blocks,
                'block_count': len(blocks),
                'engine': 'google_cloud_vision_document'
            }
            
        except Exception as e:
            return {
                'success': False,
                'error': f'Google Vision Document API error: {str(e)}'
            }


def main():
    """CLI interface for testing."""
    if len(sys.argv) < 2:
        print(json.dumps({
            'success': False,
            'error': 'Usage: python3 google_vision_ocr.py <image_path> [language_hints]'
        }))
        sys.exit(1)
    
    image_path = sys.argv[1]
    language_hints = sys.argv[2:] if len(sys.argv) > 2 else ['pt', 'en']
    
    if not os.path.exists(image_path):
        print(json.dumps({
            'success': False,
            'error': f'File not found: {image_path}'
        }))
        sys.exit(1)
    
    try:
        ocr = GoogleVisionOCR()
        result = ocr.detect_text(image_path, language_hints=language_hints)
        print(json.dumps(result, ensure_ascii=False, indent=2))
    except Exception as e:
        print(json.dumps({
            'success': False,
            'error': str(e)
        }))
        sys.exit(1)


if __name__ == '__main__':
    main()

