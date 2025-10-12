#!/usr/bin/env python3
"""
PDF OCR Processor
Combines PDF text extraction with OCR on embedded images.
"""

import sys
import os
import json
import tempfile
import shutil
from pathlib import Path
from typing import Dict, Any, List

# Import our existing extractors
try:
    from pdf_image_extractor import PDFImageExtractor
    IMAGE_EXTRACTOR_AVAILABLE = True
except ImportError:
    IMAGE_EXTRACTOR_AVAILABLE = False

try:
    from extractors.image_extractor import ImageExtractor
    OCR_AVAILABLE = True
except ImportError:
    OCR_AVAILABLE = False

try:
    from advanced_ocr_processor import AdvancedOCRProcessor
    ADVANCED_OCR_AVAILABLE = True
except ImportError:
    ADVANCED_OCR_AVAILABLE = False

try:
    import fitz  # PyMuPDF
    PYMUPDF_AVAILABLE = True
except ImportError:
    PYMUPDF_AVAILABLE = False


class PDFOCRProcessor:
    """Process PDF with text extraction + OCR on images."""
    
    def __init__(self, use_advanced_ocr: bool = True):
        if not IMAGE_EXTRACTOR_AVAILABLE:
            raise ImportError("pdf_image_extractor not available")
        if not OCR_AVAILABLE:
            raise ImportError("image_extractor not available")
        if not PYMUPDF_AVAILABLE:
            raise ImportError("PyMuPDF not installed")
        
        self.image_extractor = PDFImageExtractor()
        self.ocr_extractor = ImageExtractor()
        
        # Use advanced OCR if available
        self.use_advanced_ocr = use_advanced_ocr and ADVANCED_OCR_AVAILABLE
        if self.use_advanced_ocr:
            self.advanced_ocr = AdvancedOCRProcessor()
    
    def process_pdf(self, pdf_path: str, language: str = "por+eng") -> Dict[str, Any]:
        """
        Process PDF: extract text + OCR on images.
        
        Args:
            pdf_path: Path to PDF file
            language: OCR language (por+eng, eng, etc)
            
        Returns:
            {
                "success": bool,
                "text": str,
                "direct_text": str,
                "ocr_text": str,
                "has_images": bool,
                "images_processed": int,
                "method": str,
                "metadata": dict,
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
            
            # Step 1: Extract direct text from PDF
            direct_text = self._extract_direct_text(pdf_path)
            
            # Step 2: Check if PDF has images
            image_check = self.image_extractor.has_images(str(pdf_path))
            
            if not image_check.get('success'):
                return {
                    "success": False,
                    "error": "Failed to analyze PDF"
                }
            
            has_images = image_check.get('has_images', False)
            is_scanned = image_check.get('is_scanned', False)
            
            # Step 3: If has images, extract and OCR them
            ocr_text = ""
            images_processed = 0
            
            if has_images:
                # Create temp directory for images
                temp_dir = tempfile.mkdtemp(prefix='pdf_ocr_')
                
                try:
                    # Extract images
                    extraction_result = self.image_extractor.extract_images(
                        str(pdf_path),
                        temp_dir,
                        min_size=100  # Skip small images
                    )
                    
                    if extraction_result.get('success'):
                        images = extraction_result.get('images', [])
                        ocr_texts = []
                        
                        # OCR each image
                        for img_info in images:
                            img_path = img_info['path']
                            
                            try:
                                # Try advanced OCR first (better for complex documents)
                                if self.use_advanced_ocr:
                                    ocr_result = self.advanced_ocr.process_image(img_path)
                                    
                                    if ocr_result.get('success'):
                                        img_text = ocr_result.get('text', '')
                                        confidence = ocr_result.get('confidence', 0)
                                        
                                        if img_text and len(img_text.strip()) > 10:
                                            ocr_texts.append(
                                                f"\n[Imagem da página {img_info['page']} - Confiança: {confidence:.1f}%]\n{img_text}"
                                            )
                                            images_processed += 1
                                            continue
                                
                                # Fallback to standard OCR
                                ocr_result = self.ocr_extractor.extract(
                                    img_path,
                                    language=language,
                                    preprocess=True
                                )
                                
                                if ocr_result.get('success'):
                                    img_text = ocr_result.get('content', {}).get('text_content', '')
                                    if img_text and len(img_text.strip()) > 10:
                                        ocr_texts.append(f"\n[Imagem da página {img_info['page']}]\n{img_text}")
                                        images_processed += 1
                            except Exception as e:
                                # Skip problematic images
                                continue
                        
                        ocr_text = '\n'.join(ocr_texts)
                
                finally:
                    # Clean up temp directory
                    try:
                        shutil.rmtree(temp_dir)
                    except:
                        pass
            
            # Step 4: Combine texts
            combined_text = direct_text
            
            if ocr_text:
                if direct_text:
                    combined_text += "\n\n=== TEXTO EXTRAÍDO DE IMAGENS ===\n\n" + ocr_text
                else:
                    # If no direct text, it's probably a scanned PDF
                    combined_text = ocr_text
            
            # Determine method
            if is_scanned:
                method = "pdf_ocr_scanned"
            elif has_images and images_processed > 0:
                method = "pdf_hybrid_text_ocr"
            else:
                method = "pdf_text_only"
            
            return {
                "success": True,
                "text": combined_text,
                "direct_text": direct_text,
                "ocr_text": ocr_text,
                "has_images": has_images,
                "images_processed": images_processed,
                "is_scanned": is_scanned,
                "method": method,
                "metadata": {
                    "total_images": image_check.get('image_count', 0),
                    "pages_with_images": image_check.get('pages_with_images', 0),
                    "total_pages": image_check.get('total_pages', 0),
                    "direct_text_length": len(direct_text),
                    "ocr_text_length": len(ocr_text),
                    "combined_text_length": len(combined_text)
                }
            }
            
        except Exception as e:
            return {
                "success": False,
                "error": f"PDF OCR processing failed: {str(e)}"
            }
    
    def _extract_direct_text(self, pdf_path: Path) -> str:
        """Extract text directly from PDF (non-OCR)."""
        try:
            doc = fitz.open(str(pdf_path))
            text_parts = []
            
            for page in doc:
                text = page.get_text()
                if text and text.strip():
                    text_parts.append(text)
            
            doc.close()
            return '\n'.join(text_parts)
        except:
            return ""


def main():
    """CLI interface."""
    if len(sys.argv) < 2:
        print(json.dumps({
            "success": False,
            "error": "Usage: pdf_ocr_processor.py <pdf_path> [language]"
        }))
        sys.exit(1)
    
    pdf_path = sys.argv[1]
    language = sys.argv[2] if len(sys.argv) > 2 else "por+eng"
    
    if not IMAGE_EXTRACTOR_AVAILABLE or not OCR_AVAILABLE or not PYMUPDF_AVAILABLE:
        print(json.dumps({
            "success": False,
            "error": "Required dependencies not available"
        }))
        sys.exit(1)
    
    processor = PDFOCRProcessor()
    result = processor.process_pdf(pdf_path, language)
    
    # For CLI output, only print text (not full JSON)
    if result.get('success'):
        print(result['text'])
        sys.exit(0)
    else:
        print(json.dumps(result, indent=2, ensure_ascii=False), file=sys.stderr)
        sys.exit(1)


if __name__ == "__main__":
    main()

