#!/usr/bin/env python3
"""
Universal Document Extractor - Main Orchestrator

Detects file types and delegates to appropriate extractors.
"""

import sys
import json
import os
import magic
from pathlib import Path

# Add extractors directory to path
sys.path.insert(0, os.path.join(os.path.dirname(__file__), 'extractors'))
sys.path.insert(0, os.path.join(os.path.dirname(__file__), 'quality'))

from office_extractor import OfficeExtractor
from text_extractor import TextExtractor
from pdf_extractor import PDFExtractor
from analyzer import QualityAnalyzer


class DocumentExtractor:
    def __init__(self):
        self.office_extractor = OfficeExtractor()
        self.text_extractor = TextExtractor()
        self.pdf_extractor = PDFExtractor()
        self.quality_analyzer = QualityAnalyzer()

        # Initialize python-magic
        try:
            self.mime = magic.Magic(mime=True)
        except Exception:
            self.mime = None

    def detect_file_type(self, file_path):
        """Detect file type using both MIME and extension"""
        file_path = Path(file_path)
        extension = file_path.suffix.lower()

        # Primary detection by extension
        extension_mapping = {
            '.pdf': 'pdf',
            '.docx': 'docx',
            '.xlsx': 'xlsx',
            '.pptx': 'pptx',
            '.txt': 'txt',
            '.csv': 'csv',
            '.html': 'html',
            '.htm': 'html',
            '.xml': 'xml'
        }

        if extension in extension_mapping:
            detected_type = extension_mapping[extension]
        else:
            # Fallback to MIME detection
            if self.mime:
                try:
                    mime_type = self.mime.from_file(str(file_path))
                    mime_mapping = {
                        'application/pdf': 'pdf',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document': 'docx',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': 'xlsx',
                        'application/vnd.openxmlformats-officedocument.presentationml.presentation': 'pptx',
                        'text/plain': 'txt',
                        'text/csv': 'csv',
                        'text/html': 'html',
                        'application/xml': 'xml',
                        'text/xml': 'xml'
                    }
                    detected_type = mime_mapping.get(mime_type, 'unknown')
                except Exception:
                    detected_type = 'unknown'
            else:
                detected_type = 'unknown'

        return detected_type

    def extract_document(self, file_path):
        """Main extraction method"""
        try:
            # Validate file exists
            if not os.path.exists(file_path):
                return self._error_response(f"File not found: {file_path}")

