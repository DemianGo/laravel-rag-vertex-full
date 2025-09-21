#!/usr/bin/env python3
"""PDF Extractor for Document Extraction System"""
import sys
import os
sys.path.insert(0, os.path.join(os.path.dirname(__file__), '../../pdf_extraction'))
from extract_pdf import PdfExtractorRAG
import json

class PDFExtractor:
    def extract(self, file_path):
        """Extract content from PDF files"""
        try:
            extractor = PdfExtractorRAG(file_path)
            result_json = extractor.extract()
            result = json.loads(result_json)
            
            # Padronizar formato de saída
            return {
                'status': 'success' if result.get('success') else 'error',
                'extracted_text': result.get('content', {}).get('full_text', ''),
                'metadata': result.get('content', {}).get('metadata', {}),
                'metrics': {
                    'total_elements': result.get('extraction_stats', {}).get('total_pages', 0),
                    'extracted_elements': result.get('extraction_stats', {}).get('text_pages', 0),
                    'failed_elements': result.get('extraction_stats', {}).get('empty_pages', 0),
                    'extraction_percentage': result.get('quality_report', {}).get('extraction_percentage', 0.0)
                },
                'tables': result.get('content', {}).get('tables', []),
                'quality_status': result.get('quality_report', {}).get('status', 'UNKNOWN')
            }
        except Exception as e:
            return {
                'status': 'error',
                'error': str(e),
                'extracted_text': '',
                'metadata': {},
                'metrics': {
                    'total_elements': 0,
                    'extracted_elements': 0,
                    'failed_elements': 0,
                    'extraction_percentage': 0.0
                }
            }
