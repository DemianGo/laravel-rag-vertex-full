#!/usr/bin/env python3
"""
Text Files Extractor

Handles TXT, CSV, HTML, XML files with proper encoding detection.
"""

import os
import csv
import io
from pathlib import Path


class TextExtractor:
    def __init__(self):
        pass

    def extract(self, file_path, file_type):
        """Extract content based on text file type"""
        try:
            if file_type == 'txt':
                return self._extract_txt(file_path)
            elif file_type == 'csv':
                return self._extract_csv(file_path)
            elif file_type == 'html':
                return self._extract_html(file_path)
            elif file_type == 'xml':
                return self._extract_xml(file_path)
            else:
                return self._error_response(f"Unsupported text type: {file_type}")

        except Exception as e:
            return self._error_response(f"Text extraction failed: {str(e)}")

    def _detect_encoding(self, file_path):
        """Detect file encoding using chardet"""
        try:
            import chardet
            with open(file_path, 'rb') as f:
                raw_data = f.read(10000)  # Read first 10KB for detection
                result = chardet.detect(raw_data)
                return result.get('encoding', 'utf-8')
        except ImportError:
            # Fallback to common encodings
            for encoding in ['utf-8', 'latin-1', 'cp1252']:
                try:
                    with open(file_path, 'r', encoding=encoding) as f:
                        f.read(1000)
                    return encoding
                except UnicodeDecodeError:
                    continue
            return 'utf-8'  # Final fallback
        except Exception:
            return 'utf-8'

    def _extract_txt(self, file_path):
        """Extract content from TXT files"""
        try:
            encoding = self._detect_encoding(file_path)

            with open(file_path, 'r', encoding=encoding) as f:
                content = f.read()

            lines = content.split('\n')
            non_empty_lines = [line.strip() for line in lines if line.strip()]

            return {
                'status': 'success',
                'extracted_text': content,
                'metadata': {
                    'document_type': 'txt',
                    'encoding': encoding,
                    'total_lines': len(lines),
                    'non_empty_lines': len(non_empty_lines),
                    'total_characters': len(content),
                    'file_size_bytes': os.path.getsize(file_path)
                },
                'metrics': {
                    'total_elements': len(lines),
                    'extracted_elements': len(non_empty_lines),
                    'failed_elements': len(lines) - len(non_empty_lines),
                    'extraction_percentage': (len(non_empty_lines) / len(lines) * 100) if len(lines) > 0 else 0
                }
            }

        except Exception as e:
            return self._error_response(f"TXT extraction error: {str(e)}")

    def _extract_csv(self, file_path):
        """Extract data from CSV files"""
        try:
            encoding = self._detect_encoding(file_path)

            # Detect delimiter
            delimiter = self._detect_csv_delimiter(file_path, encoding)

            extracted_text = []
            total_rows = 0
            extracted_rows = 0
            failed_rows = 0

            with open(file_path, 'r', encoding=encoding, newline='') as f:
                reader = csv.reader(f, delimiter=delimiter)

                for row_num, row in enumerate(reader):
                    total_rows += 1
                    try:
                        # Filter out empty cells
                        non_empty_cells = [cell.strip() for cell in row if cell.strip()]
                        if non_empty_cells:
                            extracted_text.append(" | ".join(non_empty_cells))
                            extracted_rows += 1
                        else:
                            failed_rows += 1
                    except Exception:
                        failed_rows += 1

            combined_text = "\n".join(extracted_text)

            return {
                'status': 'success',
                'extracted_text': combined_text,
                'metadata': {
                    'document_type': 'csv',
                    'encoding': encoding,
                    'delimiter': delimiter,
                    'total_rows': total_rows,
                    'rows_with_data': extracted_rows,
                    'total_characters': len(combined_text),
                    'file_size_bytes': os.path.getsize(file_path)
                },
                'metrics': {
                    'total_elements': total_rows,
                    'extracted_elements': extracted_rows,
                    'failed_elements': failed_rows,
                    'extraction_percentage': (extracted_rows / total_rows * 100) if total_rows > 0 else 0
                }
            }

        except Exception as e:
            return self._error_response(f"CSV extraction error: {str(e)}")

    def _detect_csv_delimiter(self, file_path, encoding):
        """Detect CSV delimiter"""
        try:
            with open(file_path, 'r', encoding=encoding) as f:
                sample = f.read(1024)

            # Try common delimiters
            for delimiter in [',', ';', '\t', '|']:
                if delimiter in sample:
                    return delimiter

            return ','  # Default fallback
        except Exception:
            return ','

    def _extract_html(self, file_path):
        """Extract text from HTML files"""
        try:
            from bs4 import BeautifulSoup

            encoding = self._detect_encoding(file_path)

            with open(file_path, 'r', encoding=encoding) as f:
                content = f.read()

            soup = BeautifulSoup(content, 'html.parser')

            # Remove script and style elements
            for script in soup(["script", "style"]):
                script.decompose()

            # Extract text
            text = soup.get_text()

            # Clean up whitespace
            lines = (line.strip() for line in text.splitlines())
            chunks = (phrase.strip() for line in lines for phrase in line.split("  "))
            text = '\n'.join(chunk for chunk in chunks if chunk)

            # Extract some metadata
            title = soup.find('title')
            title_text = title.get_text().strip() if title else ''

            meta_desc = soup.find('meta', attrs={'name': 'description'})
            description = meta_desc.get('content', '').strip() if meta_desc else ''

            # Count elements
            total_tags = len(soup.find_all())
            text_tags = len([tag for tag in soup.find_all() if tag.get_text().strip()])

            return {
                'status': 'success',
                'extracted_text': text,
                'metadata': {
                    'document_type': 'html',
                    'encoding': encoding,
                    'title': title_text,
                    'description': description,
                    'total_html_tags': total_tags,
                    'tags_with_text': text_tags,
                    'total_characters': len(text),
                    'file_size_bytes': os.path.getsize(file_path)
                },
                'metrics': {
                    'total_elements': total_tags,
                    'extracted_elements': text_tags,
                    'failed_elements': total_tags - text_tags,
                    'extraction_percentage': (text_tags / total_tags * 100) if total_tags > 0 else 0
                }
            }

        except ImportError:
            return self._error_response("beautifulsoup4 library not installed. Run: pip install beautifulsoup4")
        except Exception as e:
            return self._error_response(f"HTML extraction error: {str(e)}")

    def _extract_xml(self, file_path):
        """Extract text from XML files"""
        try:
            import xml.etree.ElementTree as ET
            from xml.etree.ElementTree import ParseError

            encoding = self._detect_encoding(file_path)

            with open(file_path, 'r', encoding=encoding) as f:
                content = f.read()

            try:
                root = ET.fromstring(content)
            except ParseError as e:
                return self._error_response(f"Invalid XML format: {str(e)}")

            # Extract all text content
            extracted_text = []
            total_elements = 0
            elements_with_text = 0

            def extract_element_text(element, prefix=""):
                nonlocal total_elements, elements_with_text
                total_elements += 1

                # Extract element text
                if element.text and element.text.strip():
                    text_content = element.text.strip()
                    extracted_text.append(f"{prefix}{element.tag}: {text_content}")
                    elements_with_text += 1

                # Extract attributes if any
                if element.attrib:
                    attr_text = ", ".join([f"{k}={v}" for k, v in element.attrib.items()])
                    extracted_text.append(f"{prefix}{element.tag}[@{attr_text}]")

                # Process child elements
                for child in element:
                    extract_element_text(child, prefix + "  ")

            extract_element_text(root)

            combined_text = "\n".join(extracted_text)

            return {
                'status': 'success',
                'extracted_text': combined_text,
                'metadata': {
                    'document_type': 'xml',
                    'encoding': encoding,
                    'root_element': root.tag,
                    'total_elements': total_elements,
                    'elements_with_text': elements_with_text,
                    'total_characters': len(combined_text),
                    'file_size_bytes': os.path.getsize(file_path)
                },
                'metrics': {
                    'total_elements': total_elements,
                    'extracted_elements': elements_with_text,
                    'failed_elements': total_elements - elements_with_text,
                    'extraction_percentage': (elements_with_text / total_elements * 100) if total_elements > 0 else 0
                }
            }

        except Exception as e:
            return self._error_response(f"XML extraction error: {str(e)}")

    def _error_response(self, error_msg):
        """Standardized error response"""
        return {
            'status': 'error',
            'error': error_msg,
            'extracted_text': '',
            'metadata': {},
            'metrics': {
                'total_elements': 0,
                'extracted_elements': 0,
                'failed_elements': 0,
                'extraction_percentage': 0.0
            }
        }