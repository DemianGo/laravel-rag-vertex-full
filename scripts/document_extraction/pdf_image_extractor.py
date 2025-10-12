#!/usr/bin/env python3
"""
PDF Image Extractor
Extracts images from PDF files for OCR processing.
"""

import sys
import os
import json
import tempfile
from pathlib import Path
from typing import Dict, List, Any

try:
    import fitz  # PyMuPDF
    PYMUPDF_AVAILABLE = True
except ImportError:
    PYMUPDF_AVAILABLE = False

try:
    from PIL import Image
    PIL_AVAILABLE = True
except ImportError:
    PIL_AVAILABLE = False


class PDFImageExtractor:
    """Extract images from PDF files."""
    
    def __init__(self):
        if not PYMUPDF_AVAILABLE:
            raise ImportError("PyMuPDF (fitz) not installed. Install with: pip install PyMuPDF")
        if not PIL_AVAILABLE:
            raise ImportError("Pillow not installed. Install with: pip install Pillow")
    
    def extract_images(self, pdf_path: str, output_dir: str = None, min_size: int = 100) -> Dict[str, Any]:
        """
        Extract all images from PDF.
        
        Args:
            pdf_path: Path to PDF file
            output_dir: Directory to save extracted images (optional)
            min_size: Minimum image size in pixels (width or height)
            
        Returns:
            {
                "success": bool,
                "images": [
                    {
                        "page": int,
                        "index": int,
                        "path": str,
                        "width": int,
                        "height": int,
                        "format": str,
                        "size_bytes": int
                    }
                ],
                "total_images": int,
                "total_pages": int,
                "error": str (if failed)
            }
        """
        try:
            pdf_path = Path(pdf_path)
            if not pdf_path.exists():
                return {
                    "success": False,
                    "error": f"PDF file not found: {pdf_path}"
                }
            
            # Create output directory
            if output_dir:
                output_dir = Path(output_dir)
                output_dir.mkdir(parents=True, exist_ok=True)
            else:
                output_dir = Path(tempfile.mkdtemp(prefix='pdf_images_'))
            
            # Open PDF
            doc = fitz.open(str(pdf_path))
            
            extracted_images = []
            image_counter = 0
            total_pages = len(doc)
            
            # Iterate through pages
            for page_num in range(total_pages):
                page = doc[page_num]
                
                # Get images from page
                image_list = page.get_images(full=True)
                
                for img_index, img in enumerate(image_list):
                    try:
                        # Get image reference
                        xref = img[0]
                        
                        # Extract image
                        base_image = doc.extract_image(xref)
                        
                        if not base_image:
                            continue
                        
                        image_bytes = base_image["image"]
                        image_ext = base_image["ext"]
                        
                        # Load image to check dimensions
                        import io
                        pil_image = Image.open(io.BytesIO(image_bytes))
                        width, height = pil_image.size
                        
                        # Skip small images (likely icons, logos, etc)
                        if width < min_size or height < min_size:
                            continue
                        
                        # Save image
                        image_filename = f"page_{page_num + 1}_img_{image_counter}.{image_ext}"
                        image_path = output_dir / image_filename
                        
                        with open(image_path, "wb") as img_file:
                            img_file.write(image_bytes)
                        
                        extracted_images.append({
                            "page": page_num + 1,
                            "index": image_counter,
                            "path": str(image_path),
                            "width": width,
                            "height": height,
                            "format": image_ext,
                            "size_bytes": len(image_bytes)
                        })
                        
                        image_counter += 1
                        
                    except Exception as e:
                        # Skip problematic images
                        continue
            
            doc.close()
            
            return {
                "success": True,
                "images": extracted_images,
                "total_images": len(extracted_images),
                "total_pages": total_pages,
                "output_dir": str(output_dir)
            }
            
        except Exception as e:
            return {
                "success": False,
                "error": f"Image extraction failed: {str(e)}"
            }
    
    def has_images(self, pdf_path: str) -> Dict[str, Any]:
        """
        Check if PDF contains images (quick check).
        
        Args:
            pdf_path: Path to PDF file
            
        Returns:
            {
                "success": bool,
                "has_images": bool,
                "image_count": int,
                "pages_with_images": int,
                "total_pages": int
            }
        """
        try:
            pdf_path = Path(pdf_path)
            if not pdf_path.exists():
                return {
                    "success": False,
                    "error": f"PDF file not found: {pdf_path}"
                }
            
            doc = fitz.open(str(pdf_path))
            
            total_images = 0
            pages_with_images = 0
            
            for page_num in range(len(doc)):
                page = doc[page_num]
                image_list = page.get_images(full=True)
                
                if image_list:
                    pages_with_images += 1
                    total_images += len(image_list)
            
            total_pages = len(doc)
            doc.close()
            
            return {
                "success": True,
                "has_images": total_images > 0,
                "image_count": total_images,
                "pages_with_images": pages_with_images,
                "total_pages": total_pages,
                "is_scanned": pages_with_images == total_pages and total_images >= total_pages
            }
            
        except Exception as e:
            return {
                "success": False,
                "error": f"Analysis failed: {str(e)}"
            }


def main():
    """CLI interface."""
    if len(sys.argv) < 2:
        print(json.dumps({
            "success": False,
            "error": "Usage: pdf_image_extractor.py <pdf_path> [output_dir] [--check-only]"
        }))
        sys.exit(1)
    
    pdf_path = sys.argv[1]
    check_only = '--check-only' in sys.argv
    output_dir = None
    
    if len(sys.argv) > 2 and not sys.argv[2].startswith('--'):
        output_dir = sys.argv[2]
    
    if not PYMUPDF_AVAILABLE:
        print(json.dumps({
            "success": False,
            "error": "PyMuPDF not installed. Install with: pip install PyMuPDF"
        }))
        sys.exit(1)
    
    extractor = PDFImageExtractor()
    
    if check_only:
        result = extractor.has_images(pdf_path)
    else:
        result = extractor.extract_images(pdf_path, output_dir)
    
    print(json.dumps(result, indent=2, ensure_ascii=False))
    sys.exit(0 if result.get('success') else 1)


if __name__ == "__main__":
    main()

