#!/usr/bin/env python3
"""
PDF Text Extractor - Phase 1
Extracts text from non-scanned PDFs without OCR.
"""

import sys
import argparse
import os
from pathlib import Path

try:
    import pdfplumber
    PDFPLUMBER_AVAILABLE = True
except ImportError:
    PDFPLUMBER_AVAILABLE = False

try:
    import PyPDF2
    PYPDF2_AVAILABLE = True
except ImportError:
    PYPDF2_AVAILABLE = False


def extract_with_pdfplumber(pdf_path):
    """Extract text using pdfplumber (preferred method)"""
    try:
        text_content = []
        with pdfplumber.open(pdf_path) as pdf:
            for page in pdf.pages:
                page_text = page.extract_text()
                if page_text:
                    text_content.append(page_text)

        return '\n'.join(text_content)
    except Exception as e:
        raise Exception(f"pdfplumber extraction failed: {str(e)}")


def extract_with_pypdf2(pdf_path):
    """Extract text using PyPDF2 (fallback method)"""
    try:
        text_content = []
        with open(pdf_path, 'rb') as file:
            pdf_reader = PyPDF2.PdfReader(file)

            for page in pdf_reader.pages:
                page_text = page.extract_text()
                if page_text:
                    text_content.append(page_text)

        return '\n'.join(text_content)
    except Exception as e:
        raise Exception(f"PyPDF2 extraction failed: {str(e)}")


def extract_pdf_text(pdf_path):
    """Extract text from PDF using available libraries"""
    if not PDFPLUMBER_AVAILABLE and not PYPDF2_AVAILABLE:
        print("Error: No PDF processing libraries available. Install pdfplumber or PyPDF2.", file=sys.stderr)
        sys.exit(3)

    # Try pdfplumber first (preferred)
    if PDFPLUMBER_AVAILABLE:
        try:
            return extract_with_pdfplumber(pdf_path)
        except Exception as e:
            if not PYPDF2_AVAILABLE:
                print(f"Error: {str(e)}", file=sys.stderr)
                sys.exit(4)
            # Continue to PyPDF2 fallback

    # Fallback to PyPDF2
    if PYPDF2_AVAILABLE:
        try:
            return extract_with_pypdf2(pdf_path)
        except Exception as e:
            print(f"Error: All extraction methods failed. {str(e)}", file=sys.stderr)
            sys.exit(4)

    print("Error: PDF extraction impossible", file=sys.stderr)
    sys.exit(4)


def main():
    parser = argparse.ArgumentParser(description='Extract text from PDF files')
    parser.add_argument('--input', required=True, help='Input PDF file path')
    parser.add_argument('--out', help='Output text file path (optional, prints to stdout if not provided)')

    args = parser.parse_args()

    # Validate input file
    input_path = Path(args.input)
    if not input_path.exists():
        print(f"Error: File not found: {args.input}", file=sys.stderr)
        sys.exit(1)

    if not input_path.is_file():
        print(f"Error: Not a file: {args.input}", file=sys.stderr)
        sys.exit(1)

    # Check if it's a PDF file
    if input_path.suffix.lower() != '.pdf':
        print(f"Error: Not a PDF file: {args.input}", file=sys.stderr)
        sys.exit(2)

    try:
        # Extract text
        extracted_text = extract_pdf_text(str(input_path))

        # Check if extraction yielded any content
        if not extracted_text or extracted_text.strip() == '':
            print("Error: No text could be extracted from PDF (possibly scanned/image-only)", file=sys.stderr)
            sys.exit(4)

        # Output text
        if args.out:
            output_path = Path(args.out)
            try:
                with open(output_path, 'w', encoding='utf-8') as f:
                    f.write(extracted_text)
                print(f"Text extracted and saved to: {args.out}")
            except Exception as e:
                print(f"Error writing to output file: {str(e)}", file=sys.stderr)
                sys.exit(5)
        else:
            print(extracted_text)

        sys.exit(0)

    except Exception as e:
        print(f"Error: {str(e)}", file=sys.stderr)
        sys.exit(4)


if __name__ == "__main__":
    main()