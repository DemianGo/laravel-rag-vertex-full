"""
Text document extractor for TXT, CSV, RTF files.
"""

import csv
import logging
from pathlib import Path
from typing import Dict, Any, List
import chardet


def detect_encoding(file_path: str) -> str:
    """Detect file encoding automatically."""
    try:
        with open(file_path, 'rb') as f:
            raw_data = f.read()
            result = chardet.detect(raw_data)
            return result['encoding'] or 'utf-8'
    except Exception:
        return 'utf-8'


def extract_txt(file_path: str) -> Dict[str, Any]:
    """Extract text from TXT file."""
    try:
        encoding = detect_encoding(file_path)

        with open(file_path, 'r', encoding=encoding) as f:
            content = f.read()

        return {
            "success": True,
            "extracted_text": content.strip(),
            "error": None
        }

    except Exception as e:
        return {
            "success": False,
            "extracted_text": "",
            "error": f"Error extracting TXT: {str(e)}"
        }


def extract_csv(file_path: str) -> Dict[str, Any]:
    """Extract text from CSV file as tabulated text."""
    try:
        encoding = detect_encoding(file_path)
        text_parts = []

        with open(file_path, 'r', encoding=encoding, newline='') as f:
            # Try to detect CSV dialect
            sample = f.read(1024)
            f.seek(0)

            try:
                dialect = csv.Sniffer().sniff(sample)
                delimiter = dialect.delimiter
            except csv.Error:
                delimiter = ','

            reader = csv.reader(f, delimiter=delimiter)

            for row_num, row in enumerate(reader):
                if row:  # Skip empty rows
                    # Clean and join row data
                    cleaned_row = [str(cell).strip() for cell in row if str(cell).strip()]
                    if cleaned_row:
                        text_parts.append(' | '.join(cleaned_row))

        extracted_text = '\n'.join(text_parts)

        return {
            "success": True,
            "extracted_text": extracted_text,
            "error": None
        }

    except Exception as e:
        return {
            "success": False,
            "extracted_text": "",
            "error": f"Error extracting CSV: {str(e)}"
        }


def extract_rtf(file_path: str) -> Dict[str, Any]:
    """Extract text from RTF file (basic implementation)."""
    try:
        encoding = detect_encoding(file_path)

        with open(file_path, 'r', encoding=encoding) as f:
            content = f.read()

        # Basic RTF text extraction (remove RTF control codes)
        # This is a simple implementation - for complex RTF, consider using striprtf library
        text_parts = []
        in_control_word = False
        in_hex = False
        i = 0

        while i < len(content):
            char = content[i]

            if char == '\\':
                if i + 1 < len(content):
                    next_char = content[i + 1]
                    if next_char == '\\' or next_char == '{' or next_char == '}':
                        # Escaped character
                        text_parts.append(next_char)
                        i += 2
                        continue
                    elif next_char == "'":
                        # Hex encoded character
                        if i + 3 < len(content):
                            try:
                                hex_val = content[i+2:i+4]
                                char_val = chr(int(hex_val, 16))
                                text_parts.append(char_val)
                                i += 4
                                continue
                            except ValueError:
                                pass

                # Start of control word
                in_control_word = True
                i += 1
                continue

            elif char in ['{', '}']:
                in_control_word = False
                i += 1
                continue

            elif in_control_word:
                if char in [' ', '\n', '\r', '\t']:
                    in_control_word = False
                i += 1
                continue

            else:
                # Regular character
                text_parts.append(char)
                i += 1

        # Clean up the extracted text
        extracted_text = ''.join(text_parts)
        # Remove excessive whitespace
        lines = [line.strip() for line in extracted_text.split('\n')]
        extracted_text = '\n'.join(line for line in lines if line)

        # Process images with Google Vision OCR
        try:
            from universal_image_ocr import UniversalImageOCR
            ocr_processor = UniversalImageOCR(use_google_vision=True)
            ocr_result = ocr_processor.extract_and_process_images(file_path, 'rtf')
            
            if ocr_result.get('success') and ocr_result.get('ocr_text'):
                extracted_text += '\n\n=== TEXTO DE IMAGENS (OCR) ===\n\n' + ocr_result['ocr_text']
        except Exception as e:
            # Falha silenciosa - OCR é opcional
            logging.debug(f"RTF OCR processing failed: {e}")

        return {
            "success": True,
            "extracted_text": extracted_text,
            "error": None
        }

    except Exception as e:
        return {
            "success": False,
            "extracted_text": "",
            "error": f"Error extracting RTF: {str(e)}"
        }


def extract_text_document(file_path: str) -> Dict[str, Any]:
    """Main function to extract text from text-based documents."""
    path = Path(file_path)

    if not path.exists():
        return {
            "success": False,
            "extracted_text": "",
            "error": f"File not found: {file_path}"
        }

    extension = path.suffix.lower()

    if extension == '.txt':
        return extract_txt(file_path)
    elif extension == '.csv':
        return extract_csv(file_path)
    elif extension == '.rtf':
        return extract_rtf(file_path)
    else:
        return {
            "success": False,
            "extracted_text": "",
            "error": f"Unsupported text file format: {extension}"
        }


if __name__ == "__main__":
    import sys
    if len(sys.argv) != 2:
        print("Usage: python text_extractor.py <file_path>")
        sys.exit(1)

    result = extract_text_document(sys.argv[1])
    print(f"Success: {result['success']}")
    if result['success']:
        print(f"Text length: {len(result['extracted_text'])}")
        print(f"First 200 chars: {result['extracted_text'][:200]}...")
    else:
        print(f"Error: {result['error']}")