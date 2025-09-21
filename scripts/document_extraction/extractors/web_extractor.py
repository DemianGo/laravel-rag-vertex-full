#!/usr/bin/env python3
"""
Web Document Extractor

Extracts content from HTML and XML documents.
"""

import json
import re
from pathlib import Path
from bs4 import BeautifulSoup
import lxml.etree as etree
from typing import Dict, Any, List, Tuple


class WebExtractor:
    def __init__(self):
        self.supported_formats = ['html', 'htm', 'xml']

    def extract(self, file_path: str) -> Dict[str, Any]:
        """Extract content from HTML or XML file"""
        try:
            file_path = Path(file_path)

            if not file_path.exists():
                return self._error_response(f"File not found: {file_path}")

            # Read file content
            try:
                with open(file_path, 'r', encoding='utf-8') as f:
                    content = f.read()
            except UnicodeDecodeError:
                with open(file_path, 'r', encoding='latin-1') as f:
                    content = f.read()

            # Determine file type
            file_type = self._detect_format(content, file_path.suffix.lower())

            if file_type == 'html':
                return self._extract_html(content, str(file_path))
            elif file_type == 'xml':
                return self._extract_xml(content, str(file_path))
            else:
                return self._error_response(f"Unsupported web format: {file_type}")

        except Exception as e:
            return self._error_response(f"Error processing web document: {str(e)}")

    def _detect_format(self, content: str, extension: str) -> str:
        """Detect if content is HTML or XML"""
        if extension in ['.html', '.htm']:
            return 'html'
        elif extension == '.xml':
            return 'xml'

        # Content-based detection
        content_lower = content.lower().strip()
        if content_lower.startswith('<!doctype html') or '<html' in content_lower:
            return 'html'
        elif content_lower.startswith('<?xml'):
            return 'xml'

        # Default to HTML if has typical HTML tags
        html_tags = ['<body', '<head', '<title', '<div', '<p', '<span']
        if any(tag in content_lower for tag in html_tags):
            return 'html'

        return 'xml'

    def _extract_html(self, content: str, file_path: str) -> Dict[str, Any]:
        """Extract content from HTML"""
        soup = BeautifulSoup(content, 'html.parser')

        # Initialize stats
        stats = {
            'total_elements': 0,
            'extracted_elements': 0,
            'extraction_percentage': 0.0
        }

        issues = []
        recommendations = []

        # Extract basic structure
        extracted_content = {
            'title': '',
            'meta_description': '',
            'headings': [],
            'paragraphs': [],
            'links': [],
            'images': [],
            'tables': [],
            'forms': [],
            'lists': [],
            'text_content': ''
        }

        # Count all elements for stats
        all_elements = soup.find_all()
        stats['total_elements'] = len(all_elements)

        try:
            # Title
            title_tag = soup.find('title')
            if title_tag:
                extracted_content['title'] = title_tag.get_text().strip()
                stats['extracted_elements'] += 1

            # Meta description
            meta_desc = soup.find('meta', attrs={'name': 'description'})
            if meta_desc and meta_desc.get('content'):
                extracted_content['meta_description'] = meta_desc.get('content').strip()
                stats['extracted_elements'] += 1

            # Headings (h1-h6)
            for level in range(1, 7):
                headings = soup.find_all(f'h{level}')
                for heading in headings:
                    extracted_content['headings'].append({
                        'level': level,
                        'text': heading.get_text().strip()
                    })
                    stats['extracted_elements'] += 1

            # Paragraphs
            paragraphs = soup.find_all('p')
            for p in paragraphs:
                text = p.get_text().strip()
                if text:
                    extracted_content['paragraphs'].append(text)
                    stats['extracted_elements'] += 1

            # Links
            links = soup.find_all('a', href=True)
            for link in links:
                extracted_content['links'].append({
                    'url': link['href'],
                    'text': link.get_text().strip()
                })
                stats['extracted_elements'] += 1

            # Images
            images = soup.find_all('img')
            for img in images:
                img_data = {
                    'src': img.get('src', ''),
                    'alt': img.get('alt', ''),
                    'title': img.get('title', '')
                }
                extracted_content['images'].append(img_data)
                stats['extracted_elements'] += 1

            # Tables
            tables = soup.find_all('table')
            for table in tables:
                table_data = self._extract_table(table)
                extracted_content['tables'].append(table_data)
                stats['extracted_elements'] += 1

            # Forms
            forms = soup.find_all('form')
            for form in forms:
                form_data = self._extract_form(form)
                extracted_content['forms'].append(form_data)
                stats['extracted_elements'] += 1

            # Lists (ul, ol)
            lists = soup.find_all(['ul', 'ol'])
            for list_elem in lists:
                list_data = self._extract_list(list_elem)
                extracted_content['lists'].append(list_data)
                stats['extracted_elements'] += 1

            # Full text content
            extracted_content['text_content'] = soup.get_text(separator=' ', strip=True)

        except Exception as e:
            issues.append(f"Error extracting HTML elements: {str(e)}")

        # Calculate extraction percentage
        if stats['total_elements'] > 0:
            stats['extraction_percentage'] = (stats['extracted_elements'] / stats['total_elements']) * 100

        # Quality analysis
        if not extracted_content['title']:
            issues.append("Missing page title")
            recommendations.append("Add <title> tag for better SEO")

        if not extracted_content['meta_description']:
            issues.append("Missing meta description")
            recommendations.append("Add meta description for better SEO")

        if len(extracted_content['headings']) == 0:
            issues.append("No headings found")
            recommendations.append("Add heading tags (h1-h6) for better structure")

        if len(extracted_content['images']) > 0:
            missing_alt = sum(1 for img in extracted_content['images'] if not img['alt'])
            if missing_alt > 0:
                issues.append(f"{missing_alt} images missing alt text")
                recommendations.append("Add alt text to images for accessibility")

        # Determine quality status
        if stats['extraction_percentage'] >= 80 and len(issues) <= 2:
            status = "GOOD"
        elif stats['extraction_percentage'] >= 60 and len(issues) <= 5:
            status = "FAIR"
        else:
            status = "POOR"

        return {
            "success": True,
            "file_type": "html",
            "extraction_stats": stats,
            "content": extracted_content,
            "quality_report": {
                "status": status,
                "issues": issues,
                "recommendations": recommendations
            }
        }

    def _extract_xml(self, content: str, file_path: str) -> Dict[str, Any]:
        """Extract content from XML"""
        try:
            root = etree.fromstring(content.encode('utf-8'))
        except etree.XMLSyntaxError as e:
            return self._error_response(f"Invalid XML syntax: {str(e)}")

        # Initialize stats
        stats = {
            'total_elements': 0,
            'extracted_elements': 0,
            'extraction_percentage': 0.0
        }

        issues = []
        recommendations = []

        # Extract XML structure
        extracted_content = {
            'root_tag': root.tag,
            'namespaces': dict(root.nsmap) if root.nsmap else {},
            'attributes': dict(root.attrib),
            'elements': [],
            'text_content': '',
            'structure': self._get_xml_structure(root)
        }

        # Count all elements
        all_elements = root.xpath('.//*')
        stats['total_elements'] = len(all_elements) + 1  # +1 for root

        try:
            # Extract all elements
            extracted_content['elements'] = self._extract_xml_elements(root)
            stats['extracted_elements'] = len(extracted_content['elements']) + 1  # +1 for root

            # Get text content
            text_parts = []
            for elem in root.iter():
                if elem.text and elem.text.strip():
                    text_parts.append(elem.text.strip())
                if elem.tail and elem.tail.strip():
                    text_parts.append(elem.tail.strip())

            extracted_content['text_content'] = ' '.join(text_parts)

        except Exception as e:
            issues.append(f"Error extracting XML elements: {str(e)}")

        # Calculate extraction percentage
        if stats['total_elements'] > 0:
            stats['extraction_percentage'] = (stats['extracted_elements'] / stats['total_elements']) * 100

        # Quality analysis
        if not extracted_content['text_content']:
            issues.append("No text content found")
            recommendations.append("Check if XML contains meaningful text data")

        if not extracted_content['namespaces']:
            recommendations.append("Consider using XML namespaces for better organization")

        # Check for common XML issues
        if len(extracted_content['elements']) == 0:
            issues.append("No child elements found")
            recommendations.append("Verify XML structure is complete")

        # Determine quality status
        if stats['extraction_percentage'] >= 90 and len(issues) == 0:
            status = "GOOD"
        elif stats['extraction_percentage'] >= 70 and len(issues) <= 2:
            status = "FAIR"
        else:
            status = "POOR"

        return {
            "success": True,
            "file_type": "xml",
            "extraction_stats": stats,
            "content": extracted_content,
            "quality_report": {
                "status": status,
                "issues": issues,
                "recommendations": recommendations
            }
        }

    def _extract_table(self, table) -> Dict[str, Any]:
        """Extract data from HTML table"""
        headers = []
        rows = []

        # Extract headers
        header_row = table.find('thead')
        if header_row:
            th_tags = header_row.find_all(['th', 'td'])
            headers = [th.get_text().strip() for th in th_tags]

        # Extract rows
        tbody = table.find('tbody') or table
        tr_tags = tbody.find_all('tr')

        for tr in tr_tags:
            if tr.find('th') and not headers:  # Header row in tbody
                headers = [th.get_text().strip() for th in tr.find_all(['th', 'td'])]
            else:
                cells = [td.get_text().strip() for td in tr.find_all(['td', 'th'])]
                if cells:
                    rows.append(cells)

        return {
            'headers': headers,
            'rows': rows,
            'row_count': len(rows),
            'column_count': len(headers) if headers else (len(rows[0]) if rows else 0)
        }

    def _extract_form(self, form) -> Dict[str, Any]:
        """Extract data from HTML form"""
        fields = []

        inputs = form.find_all(['input', 'textarea', 'select'])
        for input_elem in inputs:
            field = {
                'type': input_elem.name,
                'name': input_elem.get('name', ''),
                'id': input_elem.get('id', ''),
                'placeholder': input_elem.get('placeholder', ''),
                'required': input_elem.has_attr('required')
            }

            if input_elem.name == 'input':
                field['input_type'] = input_elem.get('type', 'text')
            elif input_elem.name == 'select':
                options = [opt.get_text().strip() for opt in input_elem.find_all('option')]
                field['options'] = options

            fields.append(field)

        return {
            'action': form.get('action', ''),
            'method': form.get('method', 'GET').upper(),
            'fields': fields,
            'field_count': len(fields)
        }

    def _extract_list(self, list_elem) -> Dict[str, Any]:
        """Extract data from HTML list"""
        items = []

        li_tags = list_elem.find_all('li')
        for li in li_tags:
            items.append(li.get_text().strip())

        return {
            'type': list_elem.name,  # 'ul' or 'ol'
            'items': items,
            'item_count': len(items)
        }

    def _get_xml_structure(self, element, level=0) -> Dict[str, Any]:
        """Get XML structure recursively"""
        structure = {
            'tag': element.tag,
            'level': level,
            'attributes': dict(element.attrib),
            'has_text': bool(element.text and element.text.strip()),
            'children': []
        }

        for child in element:
            structure['children'].append(self._get_xml_structure(child, level + 1))

        return structure

    def _extract_xml_elements(self, element) -> List[Dict[str, Any]]:
        """Extract all XML elements recursively"""
        elements = []

        for child in element:
            elem_data = {
                'tag': child.tag,
                'attributes': dict(child.attrib),
                'text': child.text.strip() if child.text else '',
                'tail': child.tail.strip() if child.tail else '',
                'has_children': len(list(child)) > 0
            }
            elements.append(elem_data)

            # Recursively extract children
            if len(list(child)) > 0:
                elements.extend(self._extract_xml_elements(child))

        return elements

    def _error_response(self, message: str) -> Dict[str, Any]:
        """Generate error response"""
        return {
            "success": False,
            "file_type": "web",
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
            }
        }


if __name__ == "__main__":
    import sys

    if len(sys.argv) != 2:
        print("Usage: python web_extractor.py <file_path>")
        sys.exit(1)

    extractor = WebExtractor()
    result = extractor.extract(sys.argv[1])
    print(json.dumps(result, indent=2, ensure_ascii=False))