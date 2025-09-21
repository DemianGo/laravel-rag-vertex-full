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
from web_extractor import WebExtractor
from image_extractor import ImageExtractor
from analyzer import QualityAnalyzer
from reporter import QualityReporter


class DocumentExtractor:
    def __init__(self):
        self.office_extractor = OfficeExtractor()
        self.text_extractor = TextExtractor()
        self.pdf_extractor = PDFExtractor()
        self.web_extractor = WebExtractor()
        self.image_extractor = ImageExtractor()
        self.quality_analyzer = QualityAnalyzer()
        self.quality_reporter = QualityReporter()

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
            '.xml': 'xml',
            '.png': 'image',
            '.jpg': 'image',
            '.jpeg': 'image',
            '.gif': 'image',
            '.bmp': 'image',
            '.tiff': 'image',
            '.tif': 'image',
            '.webp': 'image'
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
                        'text/xml': 'xml',
                        'image/png': 'image',
                        'image/jpeg': 'image',
                        'image/jpg': 'image',
                        'image/gif': 'image',
                        'image/bmp': 'image',
                        'image/tiff': 'image',
                        'image/webp': 'image'
                    }
                    detected_type = mime_mapping.get(mime_type, 'unknown')
                except Exception:
                    detected_type = 'unknown'
            else:
                detected_type = 'unknown'

        return detected_type

    def extract_document(self, file_path, **kwargs):
        """Main extraction method"""
        try:
            # Validate file exists
            if not os.path.exists(file_path):
                return self._error_response(f"File not found: {file_path}")

            # Detect file type
            file_type = self.detect_file_type(file_path)

            # Start timing
            import time
            start_time = time.time()

            # Route to appropriate extractor
            if file_type == 'pdf':
                result = self.pdf_extractor.extract(file_path)
            elif file_type in ['docx', 'xlsx', 'pptx']:
                result = self.office_extractor.extract(file_path)
            elif file_type in ['txt', 'csv']:
                result = self.text_extractor.extract(file_path)
            elif file_type in ['html', 'xml']:
                result = self.web_extractor.extract(file_path)
            elif file_type == 'image':
                # Pass additional OCR parameters if provided
                language = kwargs.get('language', 'por+eng')
                preprocess = kwargs.get('preprocess', True)
                result = self.image_extractor.extract(file_path, language, preprocess)
            else:
                return self._error_response(f"Unsupported file type: {file_type}")

            # Add processing time to result
            processing_time = round(time.time() - start_time, 2)
            result['processing_time'] = processing_time
            result['file_path'] = str(file_path)

            # Apply quality analysis if extraction was successful
            if result.get('success', False):
                try:
                    # Run additional quality analysis
                    enhanced_quality = self.quality_analyzer.analyze(result)

                    # Merge quality analysis results
                    if enhanced_quality:
                        existing_quality = result.get('quality_report', {})
                        result['quality_report'] = {**existing_quality, **enhanced_quality}

                    # Generate enhanced single-file report
                    if kwargs.get('detailed_report', False):
                        detailed_report = self.quality_reporter.generate_single_file_report(result, str(file_path))
                        result['detailed_report'] = detailed_report

                except Exception as e:
                    # Don't fail the entire extraction if quality analysis fails
                    if 'quality_report' not in result:
                        result['quality_report'] = {}
                    result['quality_report']['analysis_error'] = f"Quality analysis failed: {str(e)}"

            return result

        except Exception as e:
            return self._error_response(f"Error processing document: {str(e)}")

    def extract_batch(self, file_paths, **kwargs):
        """Extract multiple documents and generate consolidated report"""
        try:
            import time
            batch_start = time.time()
            results = []

            # Process each file
            for file_path in file_paths:
                try:
                    result = self.extract_document(file_path, **kwargs)
                    results.append(result)
                except Exception as e:
                    error_result = self._error_response(f"Failed to process {file_path}: {str(e)}")
                    error_result['file_path'] = str(file_path)
                    results.append(error_result)

            # Generate consolidated report
            batch_info = {
                'total_files': len(file_paths),
                'processing_time': round(time.time() - batch_start, 2),
                'processed_at': time.strftime('%Y-%m-%d %H:%M:%S')
            }

            consolidated_report = self.quality_reporter.generate_consolidated_report(results, batch_info)

            return {
                'success': True,
                'batch_info': batch_info,
                'individual_results': results,
                'consolidated_report': consolidated_report
            }

        except Exception as e:
            return {
                'success': False,
                'error': f"Batch processing failed: {str(e)}",
                'individual_results': results if 'results' in locals() else []
            }

    def _error_response(self, message):
        """Generate standardized error response"""
        return {
            "success": False,
            "file_type": "unknown",
            "extraction_stats": {
                "total_elements": 0,
                "extracted_elements": 0,
                "extraction_percentage": 0.0
            },
            "content": {},
            "quality_report": {
                "status": "ERROR",
                "issues": [message],
                "recommendations": ["Fix the error and try again"]
            },
            "processing_time": 0
        }


def main():
    """Command-line interface"""
    import argparse

    parser = argparse.ArgumentParser(description='Universal Document Extractor')
    parser.add_argument('files', nargs='+', help='File(s) to extract')
    parser.add_argument('--language', default='por+eng', help='OCR language (for images)')
    parser.add_argument('--no-preprocess', action='store_true', help='Disable image preprocessing')
    parser.add_argument('--detailed-report', action='store_true', help='Generate detailed quality report')
    parser.add_argument('--output', '-o', help='Output file for results')
    parser.add_argument('--batch', action='store_true', help='Process as batch with consolidated report')

    args = parser.parse_args()

    extractor = DocumentExtractor()

    try:
        if args.batch or len(args.files) > 1:
            # Batch processing
            result = extractor.extract_batch(
                args.files,
                language=args.language,
                preprocess=not args.no_preprocess,
                detailed_report=args.detailed_report
            )
        else:
            # Single file processing
            result = extractor.extract_document(
                args.files[0],
                language=args.language,
                preprocess=not args.no_preprocess,
                detailed_report=args.detailed_report
            )

        # Output results
        output_json = json.dumps(result, indent=2, ensure_ascii=False)

        if args.output:
            with open(args.output, 'w', encoding='utf-8') as f:
                f.write(output_json)
            print(f"Results saved to {args.output}")
        else:
            print(output_json)

    except Exception as e:
        error_result = {
            "success": False,
            "error": f"Command execution failed: {str(e)}"
        }
        print(json.dumps(error_result, indent=2, ensure_ascii=False))
        sys.exit(1)


if __name__ == "__main__":
    main()

