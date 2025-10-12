#!/usr/bin/env python3
"""
Image Extractor Wrapper for PHP integration
Extracts text from images using OCR (Tesseract)
"""

import sys
import json
from pathlib import Path
from extractors.image_extractor import ImageExtractor


def extract_from_image(file_path: str) -> str:
    """
    Extract text from image file using OCR
    
    Args:
        file_path: Path to the image file
        
    Returns:
        Extracted text as string
    """
    try:
        # Handle temp files without extension by copying to temp file with extension
        import tempfile
        import shutil
        
        path = Path(file_path)
        if not path.suffix or path.suffix.lower() not in ['.png', '.jpg', '.jpeg', '.gif', '.bmp', '.tiff', '.tif', '.webp']:
            # Create a temporary file with .png extension
            temp_file = tempfile.NamedTemporaryFile(suffix='.png', delete=False)
            temp_file.close()
            shutil.copy2(file_path, temp_file.name)
            file_path_to_use = temp_file.name
            delete_temp = True
        else:
            file_path_to_use = file_path
            delete_temp = False
        
        extractor = ImageExtractor()
        result = extractor.extract(file_path_to_use)
        
        # Clean up temp file if created
        if delete_temp and Path(file_path_to_use).exists():
            Path(file_path_to_use).unlink()
        
        if result.get('success'):
            text = result.get('content', {}).get('text_content', '')
            if text:
                return text
            else:
                # No text detected, but extraction was successful
                return "[Image processed - no text detected]"
        else:
            # Extraction failed
            issues = result.get('quality_report', {}).get('issues', ['Unknown error'])
            error_msg = issues[0] if issues else 'OCR extraction failed'
            print(f"Error: {error_msg}", file=sys.stderr)
            return ""
            
    except Exception as e:
        print(f"Error extracting image: {str(e)}", file=sys.stderr)
        return ""


if __name__ == "__main__":
    if len(sys.argv) < 2:
        print("Usage: image_extractor_wrapper.py <file_path>", file=sys.stderr)
        sys.exit(1)
    
    file_path = sys.argv[1]
    
    # Validate file exists
    if not Path(file_path).exists():
        print(f"Error: File not found: {file_path}", file=sys.stderr)
        sys.exit(1)
    
    # Extract text
    text = extract_from_image(file_path)
    
    # Output text (PHP will capture this)
    print(text)

