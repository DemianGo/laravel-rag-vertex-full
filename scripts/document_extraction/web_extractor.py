"""
Web document extractor for HTML, XML files.
"""

import logging
from pathlib import Path
from typing import Dict, Any
import chardet

try:
    from bs4 import BeautifulSoup
except ImportError as e:
    logging.error(f"Missing required dependencies: {e}")
    raise


def detect_encoding(file_path: str) -> str:
    """Detect file encoding automatically."""
    try:
        with open(file_path, 'rb') as f:
            raw_data = f.read()
            result = chardet.detect(raw_data)
            return result['encoding'] or 'utf-8'
    except Exception:
        return 'utf-8'


def extract_html(file_path: str) -> Dict[str, Any]:
    """Extract text from HTML file."""
    try:
        encoding = detect_encoding(file_path)

        with open(file_path, 'r', encoding=encoding) as f:
            content = f.read()

        soup = BeautifulSoup(content, 'lxml')

        # Remove script and style elements
        for script in soup(["script", "style"]):
            script.decompose()

        # Extract text from specific elements in order
        text_parts = []

        # Title
        title = soup.find('title')
        if title and title.get_text().strip():
            text_parts.append(f"Title: {title.get_text().strip()}")

        # Headers
        for i in range(1, 7):
            headers = soup.find_all(f'h{i}')
            for header in headers:
                header_text = header.get_text().strip()
                if header_text:
                    text_parts.append(f"H{i}: {header_text}")

        # Paragraphs and other content
        for tag in soup.find_all(['p', 'div', 'article', 'section', 'main']):
            text = tag.get_text().strip()
            if text:  # Accept all non-empty text
                # Avoid duplicating content already captured in headers
                if not any(text.startswith(f"H{i}:") for i in range(1, 7)):
                    text_parts.append(text)

        # Tables
        for table in soup.find_all('table'):
            text_parts.append("=== Table ===")
            for row in table.find_all('tr'):
                cells = row.find_all(['td', 'th'])
                row_data = []
                for cell in cells:
                    cell_text = cell.get_text().strip()
                    if cell_text:
                        row_data.append(cell_text)
                if row_data:
                    text_parts.append(' | '.join(row_data))

        # Lists
        for ul in soup.find_all(['ul', 'ol']):
            list_items = ul.find_all('li')
            for li in list_items:
                item_text = li.get_text().strip()
                if item_text:
                    text_parts.append(f"• {item_text}")

        extracted_text = '\n'.join(text_parts)

        # Process images with Google Vision OCR
        try:
            from universal_image_ocr import UniversalImageOCR
            ocr_processor = UniversalImageOCR(use_google_vision=True)
            ocr_result = ocr_processor.extract_and_process_images(file_path, 'html')
            
            if ocr_result.get('success') and ocr_result.get('ocr_text'):
                extracted_text += '\n\n=== TEXTO DE IMAGENS (OCR) ===\n\n' + ocr_result['ocr_text']
        except Exception as e:
            # Falha silenciosa - OCR é opcional
            logging.debug(f"HTML OCR processing failed: {e}")

        return {
            "success": True,
            "extracted_text": extracted_text,
            "error": None
        }

    except Exception as e:
        return {
            "success": False,
            "extracted_text": "",
            "error": f"Error extracting HTML: {str(e)}"
        }


def extract_xml(file_path: str) -> Dict[str, Any]:
    """Extract text from XML file."""
    try:
        encoding = detect_encoding(file_path)

        with open(file_path, 'r', encoding=encoding) as f:
            content = f.read()

        soup = BeautifulSoup(content, 'xml')

        # Extract all text content from XML
        text_parts = []

        def extract_element_text(element, level=0):
            """Recursively extract text from XML elements."""
            indent = "  " * level

            if element.name:
                # Add element name as context
                if level == 0 or element.get_text().strip():
                    element_text = element.get_text().strip()
                    if element_text:
                        # For leaf elements, show tag name and content
                        if not element.find_all():
                            text_parts.append(f"{indent}{element.name}: {element_text}")
                        else:
                            # For parent elements, just show the tag name
                            text_parts.append(f"{indent}=== {element.name} ===")

            # Process child elements
            for child in element.find_all(recursive=False):
                extract_element_text(child, level + 1)

        # Start extraction from root
        if soup.contents:
            for root_element in soup.find_all():
                if root_element.parent is None or root_element.parent == soup:
                    extract_element_text(root_element)

        extracted_text = '\n'.join(text_parts)

        # Process images with Google Vision OCR
        try:
            from universal_image_ocr import UniversalImageOCR
            ocr_processor = UniversalImageOCR(use_google_vision=True)
            ocr_result = ocr_processor.extract_and_process_images(file_path, 'xml')
            
            if ocr_result.get('success') and ocr_result.get('ocr_text'):
                extracted_text += '\n\n=== TEXTO DE IMAGENS (OCR) ===\n\n' + ocr_result['ocr_text']
        except Exception as e:
            # Falha silenciosa - OCR é opcional
            logging.debug(f"XML OCR processing failed: {e}")

        return {
            "success": True,
            "extracted_text": extracted_text,
            "error": None
        }

    except Exception as e:
        return {
            "success": False,
            "extracted_text": "",
            "error": f"Error extracting XML: {str(e)}"
        }


def extract_from_html(file_path: str) -> Dict[str, Any]:
    """Alias for extract_html to maintain compatibility."""
    return extract_html(file_path)


def extract_web_document(file_path: str) -> Dict[str, Any]:
    """Main function to extract text from web documents."""
    path = Path(file_path)

    if not path.exists():
        return {
            "success": False,
            "extracted_text": "",
            "error": f"File not found: {file_path}"
        }

    extension = path.suffix.lower()

    if extension in ['.html', '.htm']:
        return extract_html(file_path)
    elif extension == '.xml':
        return extract_xml(file_path)
    else:
        return {
            "success": False,
            "extracted_text": "",
            "error": f"Unsupported web file format: {extension}"
        }


if __name__ == "__main__":
    import sys
    if len(sys.argv) != 2:
        print("Usage: python web_extractor.py <file_path>")
        sys.exit(1)

    result = extract_web_document(sys.argv[1])
    print(f"Success: {result['success']}")
    if result['success']:
        print(f"Text length: {len(result['extracted_text'])}")
        print(f"First 200 chars: {result['extracted_text'][:200]}...")
    else:
        print(f"Error: {result['error']}")