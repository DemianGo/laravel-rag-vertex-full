"""
Semantic content parser specialized for different document types.
"""

import re
from typing import Dict, Any, List, Optional, Tuple
from bs4 import BeautifulSoup, Tag


class ContentParser:
    """Parse content semantically based on document type."""

    def __init__(self):
        self.pdf_patterns = self._compile_pdf_patterns()
        self.office_patterns = self._compile_office_patterns()
        self.html_patterns = self._compile_html_patterns()

    def parse_content(self, text: str, file_type: str) -> Dict[str, Any]:
        """
        Parse content based on document type.

        Args:
            text: Document text content
            file_type: Type of document (pdf, docx, html, etc.)

        Returns:
            Parsed content structure
        """
        if not text or not text.strip():
            return self._empty_parse_result()

        # Route to specialized parser
        # Ensure file_type is a string and not None
        if not file_type or not isinstance(file_type, str):
            file_type = 'txt'

        file_type_lower = file_type.lower()

        if file_type_lower == 'pdf':
            return self.parse_pdf_content(text)
        elif file_type_lower in ['docx', 'xlsx', 'pptx', 'doc', 'xls', 'ppt']:
            return self.parse_office_content(text)
        elif file_type_lower in ['html', 'htm', 'xml']:
            return self.parse_html_content(text)
        elif file_type_lower in ['txt', 'csv', 'rtf']:
            return self.parse_generic_content(text)
        else:
            return self.parse_generic_content(text)

    def parse_pdf_content(self, text: str) -> Dict[str, Any]:
        """
        Parse PDF content with PDF-specific characteristics.

        Args:
            text: Extracted PDF text

        Returns:
            Parsed PDF structure
        """
        parsed = {
            "document_type": "pdf",
            "elements": [],
            "metadata": {},
            "structure_indicators": {}
        }

        lines = text.split('\n')
        current_element = None
        element_buffer = []
        page_breaks = []

        for i, line in enumerate(lines):
            line_stripped = line.strip()
            if not line_stripped:
                if current_element and element_buffer:
                    # End current element
                    self._finalize_element(current_element, element_buffer, parsed["elements"])
                    current_element = None
                    element_buffer = []
                continue

            # Detect PDF-specific patterns
            element_type = self._classify_pdf_line(line_stripped, i, lines)

            # Check for page breaks
            if self._is_page_break(line_stripped):
                page_breaks.append(i)
                continue

            # Handle element transitions
            if current_element != element_type:
                if current_element and element_buffer:
                    self._finalize_element(current_element, element_buffer, parsed["elements"])

                current_element = element_type
                element_buffer = []

            element_buffer.append({
                "line": line_stripped,
                "line_number": i,
                "original": line
            })

        # Finalize last element
        if current_element and element_buffer:
            self._finalize_element(current_element, element_buffer, parsed["elements"])

        # Add PDF-specific metadata
        parsed["metadata"] = {
            "page_breaks": page_breaks,
            "estimated_pages": len(page_breaks) + 1,
            "has_headers_footers": self._detect_headers_footers(text),
            "table_regions": self._detect_pdf_tables(text),
            "column_layout": self._detect_column_layout(lines)
        }

        # Structure indicators
        parsed["structure_indicators"] = {
            "numbered_sections": self._count_numbered_sections(text),
            "academic_formatting": self._detect_academic_formatting(text),
            "legal_formatting": self._detect_legal_formatting(text),
            "technical_formatting": self._detect_technical_formatting(text)
        }

        return parsed

    def parse_office_content(self, text: str) -> Dict[str, Any]:
        """
        Parse Office document content (Word, Excel, PowerPoint).

        Args:
            text: Extracted Office document text

        Returns:
            Parsed Office structure
        """
        parsed = {
            "document_type": "office",
            "elements": [],
            "metadata": {},
            "structure_indicators": {}
        }

        # Office documents often preserve more structure
        sections = self._split_office_sections(text)

        for section in sections:
            element_type = self._classify_office_section(section)
            parsed["elements"].append({
                "type": element_type,
                "content": section["content"],
                "position": section["start"],
                "length": len(section["content"]),
                "formatting_hints": section.get("formatting", [])
            })

        # Office-specific metadata
        parsed["metadata"] = {
            "has_table_structure": self._detect_office_tables(text),
            "bullet_points": self._count_bullet_points(text),
            "section_breaks": self._detect_section_breaks(text),
            "formatting_preserved": True
        }

        parsed["structure_indicators"] = {
            "outline_structure": self._detect_outline_structure(text),
            "presentation_slides": self._detect_slide_structure(text),
            "spreadsheet_data": self._detect_spreadsheet_patterns(text)
        }

        return parsed

    def parse_html_content(self, text: str) -> Dict[str, Any]:
        """
        Parse HTML content leveraging HTML tags.

        Args:
            text: HTML content

        Returns:
            Parsed HTML structure
        """
        parsed = {
            "document_type": "html",
            "elements": [],
            "metadata": {},
            "structure_indicators": {}
        }

        try:
            soup = BeautifulSoup(text, 'html.parser')

            # Extract structured elements
            for element in soup.find_all(['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'ul', 'ol', 'table', 'div', 'section']):
                parsed_element = self._parse_html_element(element)
                if parsed_element:
                    parsed["elements"].append(parsed_element)

            # HTML-specific metadata
            parsed["metadata"] = {
                "title": soup.title.string if soup.title else None,
                "meta_tags": self._extract_meta_tags(soup),
                "links": len(soup.find_all('a')),
                "images": len(soup.find_all('img')),
                "forms": len(soup.find_all('form')),
                "scripts": len(soup.find_all('script'))
            }

            parsed["structure_indicators"] = {
                "semantic_html5": self._detect_semantic_html5(soup),
                "navigation_structure": self._extract_navigation(soup),
                "content_sections": self._identify_content_sections(soup)
            }

        except Exception:
            # Fallback to text parsing if HTML parsing fails
            return self.parse_generic_content(text)

        return parsed

    def parse_generic_content(self, text: str) -> Dict[str, Any]:
        """
        Generic content parser for unknown document types.

        Args:
            text: Document text

        Returns:
            Basic parsed structure
        """
        parsed = {
            "document_type": "generic",
            "elements": [],
            "metadata": {},
            "structure_indicators": {}
        }

        paragraphs = re.split(r'\n\s*\n', text)
        for i, paragraph in enumerate(paragraphs):
            if paragraph.strip():
                element_type = self._classify_generic_paragraph(paragraph.strip())
                parsed["elements"].append({
                    "type": element_type,
                    "content": paragraph.strip(),
                    "position": i,
                    "length": len(paragraph),
                    "word_count": len(paragraph.split())
                })

        parsed["metadata"] = {
            "total_paragraphs": len([p for p in paragraphs if p.strip()]),
            "average_paragraph_length": sum(len(p) for p in paragraphs if p.strip()) / max(len([p for p in paragraphs if p.strip()]), 1)
        }

        return parsed

    def _compile_pdf_patterns(self) -> Dict[str, re.Pattern]:
        """Compile PDF-specific regex patterns."""
        return {
            'page_number': re.compile(r'^\s*\d+\s*$'),
            'page_header': re.compile(r'^[A-Z\s]{5,50}$'),
            'footnote': re.compile(r'^\d+\s+[a-z]'),
            'reference': re.compile(r'^\[\d+\]|\(\d{4}\)|doi:'),
            'table_row': re.compile(r'.*\s{3,}.*\s{3,}.*'),  # Multiple spaces indicating columns
            'section_number': re.compile(r'^\d+(\.\d+)*\s+[A-Z]')
        }

    def _compile_office_patterns(self) -> Dict[str, re.Pattern]:
        """Compile Office document patterns."""
        return {
            'bullet_point': re.compile(r'^\s*[•\-\*]\s+'),
            'numbered_list': re.compile(r'^\s*\d+[.)]\s+'),
            'table_separator': re.compile(r'\|\s*\|\s*\|'),
            'section_break': re.compile(r'={3,}|---+'),
            'title_case': re.compile(r'^[A-Z][a-z\s]+$')
        }

    def _compile_html_patterns(self) -> Dict[str, re.Pattern]:
        """Compile HTML-specific patterns."""
        return {
            'html_tag': re.compile(r'<[^>]+>'),
            'html_comment': re.compile(r'<!--.*?-->', re.DOTALL),
            'css_style': re.compile(r'style\s*=\s*["\'][^"\']*["\']'),
            'javascript': re.compile(r'<script.*?</script>', re.DOTALL | re.IGNORECASE)
        }

    def _classify_pdf_line(self, line: str, line_idx: int, all_lines: List[str]) -> str:
        """Classify a PDF line by type."""
        # Check patterns in order of specificity
        if self.pdf_patterns['page_number'].match(line):
            return 'page_number'
        elif self.pdf_patterns['footnote'].match(line):
            return 'footnote'
        elif self.pdf_patterns['reference'].match(line):
            return 'reference'
        elif self.pdf_patterns['section_number'].match(line):
            return 'section_header'
        elif self.pdf_patterns['table_row'].match(line):
            return 'table_row'
        elif len(line) < 10 and line.isupper():
            return 'header'
        elif len(line) > 20 and not line.endswith('.'):
            return 'title'
        else:
            return 'paragraph'

    def _is_page_break(self, line: str) -> bool:
        """Detect if line represents a page break."""
        return (self.pdf_patterns['page_number'].match(line) and len(line) < 5) or \
               'Page ' in line or 'Página ' in line

    def _finalize_element(self, element_type: str, buffer: List[Dict], elements: List[Dict]):
        """Finalize and add element to parsed elements."""
        if not buffer:
            return

        content = '\n'.join(item['line'] for item in buffer)
        elements.append({
            "type": element_type,
            "content": content,
            "start_line": buffer[0]['line_number'],
            "end_line": buffer[-1]['line_number'],
            "length": len(content),
            "word_count": len(content.split())
        })

    def _detect_headers_footers(self, text: str) -> bool:
        """Detect if document has headers/footers."""
        lines = text.split('\n')
        if len(lines) < 10:
            return False

        # Check first and last few lines for repetitive patterns
        first_lines = [line.strip() for line in lines[:3] if line.strip()]
        last_lines = [line.strip() for line in lines[-3:] if line.strip()]

        # Simple heuristic: short lines that might be headers/footers
        potential_headers = [line for line in first_lines if len(line) < 50]
        potential_footers = [line for line in last_lines if len(line) < 50]

        return len(potential_headers) > 0 or len(potential_footers) > 0

    def _detect_pdf_tables(self, text: str) -> List[Dict[str, Any]]:
        """Detect table regions in PDF text."""
        tables = []
        lines = text.split('\n')

        table_start = None
        table_lines = []

        for i, line in enumerate(lines):
            if self.pdf_patterns['table_row'].match(line):
                if table_start is None:
                    table_start = i
                table_lines.append(line)
            else:
                if table_start is not None and len(table_lines) >= 2:
                    # End of table
                    tables.append({
                        "start_line": table_start,
                        "end_line": i - 1,
                        "row_count": len(table_lines),
                        "estimated_columns": max(line.count('  ') for line in table_lines if line.strip())
                    })
                table_start = None
                table_lines = []

        return tables

    def _detect_column_layout(self, lines: List[str]) -> Dict[str, Any]:
        """Detect if document has column layout."""
        # Simple heuristic: look for consistent indentation patterns
        indentations = []
        for line in lines[:100]:  # Sample first 100 lines
            if line.strip():
                indent = len(line) - len(line.lstrip())
                indentations.append(indent)

        if not indentations:
            return {"detected": False}

        # Check for multiple common indentation levels
        from collections import Counter
        indent_counts = Counter(indentations)
        common_indents = [indent for indent, count in indent_counts.items() if count > 5]

        return {
            "detected": len(common_indents) > 2,
            "common_indents": sorted(common_indents),
            "estimated_columns": min(len(common_indents), 3)
        }

    def _count_numbered_sections(self, text: str) -> int:
        """Count numbered sections in document."""
        return len(re.findall(r'^\s*\d+(\.\d+)*\s+[A-Z]', text, re.MULTILINE))

    def _detect_academic_formatting(self, text: str) -> bool:
        """Detect academic document formatting."""
        indicators = [
            len(re.findall(r'\[\d+\]', text)) > 5,  # Citations
            'abstract' in text.lower(),
            'introduction' in text.lower(),
            'methodology' in text.lower(),
            'references' in text.lower(),
            len(re.findall(r'\b\d{4}\b', text)) > 10  # Years
        ]
        return sum(indicators) >= 3

    def _detect_legal_formatting(self, text: str) -> bool:
        """Detect legal document formatting."""
        legal_terms = ['whereas', 'therefore', 'pursuant', 'herein', 'thereof', 'section', 'clause', 'article']
        legal_score = sum(1 for term in legal_terms if term in text.lower())
        return legal_score >= 3

    def _detect_technical_formatting(self, text: str) -> bool:
        """Detect technical document formatting."""
        tech_indicators = [
            len(re.findall(r'figure\s+\d+', text, re.IGNORECASE)) > 0,
            len(re.findall(r'table\s+\d+', text, re.IGNORECASE)) > 0,
            len(re.findall(r'equation\s+\d+', text, re.IGNORECASE)) > 0,
            len(re.findall(r'\b[A-Z]{2,}\b', text)) > 20  # Acronyms
        ]
        return sum(tech_indicators) >= 2

    def _split_office_sections(self, text: str) -> List[Dict[str, Any]]:
        """Split Office document into logical sections."""
        sections = []
        current_pos = 0

        # Split by double newlines (paragraphs)
        paragraphs = re.split(r'\n\s*\n', text)

        for paragraph in paragraphs:
            if paragraph.strip():
                sections.append({
                    "content": paragraph.strip(),
                    "start": current_pos,
                    "formatting": self._extract_office_formatting(paragraph)
                })
            current_pos += len(paragraph) + 2

        return sections

    def _extract_office_formatting(self, text: str) -> List[str]:
        """Extract formatting hints from Office text."""
        formatting = []

        if self.office_patterns['bullet_point'].match(text):
            formatting.append('bulleted_list')
        if self.office_patterns['numbered_list'].match(text):
            formatting.append('numbered_list')
        if text.isupper():
            formatting.append('all_caps')
        if self.office_patterns['title_case'].match(text):
            formatting.append('title_case')
        if '|' in text:
            formatting.append('table_content')

        return formatting

    def _classify_office_section(self, section: Dict[str, Any]) -> str:
        """Classify Office document section."""
        content = section["content"]
        formatting = section.get("formatting", [])

        if 'bulleted_list' in formatting:
            return 'bulleted_list'
        elif 'numbered_list' in formatting:
            return 'numbered_list'
        elif 'table_content' in formatting:
            return 'table'
        elif 'title_case' in formatting or len(content) < 100:
            return 'heading'
        else:
            return 'paragraph'

    def _detect_office_tables(self, text: str) -> bool:
        """Detect table structures in Office documents."""
        return '|' in text or len(re.findall(r'\t.*\t.*\t', text)) > 0

    def _count_bullet_points(self, text: str) -> int:
        """Count bullet points in Office document."""
        return len(re.findall(r'^\s*[•\-\*]\s+', text, re.MULTILINE))

    def _detect_section_breaks(self, text: str) -> List[int]:
        """Detect section breaks in Office documents."""
        breaks = []
        lines = text.split('\n')

        for i, line in enumerate(lines):
            if self.office_patterns['section_break'].match(line.strip()):
                breaks.append(i)

        return breaks

    def _detect_outline_structure(self, text: str) -> bool:
        """Detect if Office document has outline structure."""
        numbered_items = len(re.findall(r'^\s*\d+(\.\d+)+', text, re.MULTILINE))
        return numbered_items > 3

    def _detect_slide_structure(self, text: str) -> bool:
        """Detect PowerPoint slide structure."""
        slide_indicators = [
            'slide ' in text.lower(),
            len(re.findall(r'^\s*\d+\s*$', text, re.MULTILINE)) > 5,  # Slide numbers
            text.count('\n\n') > 10  # Slide breaks
        ]
        return sum(slide_indicators) >= 2

    def _detect_spreadsheet_patterns(self, text: str) -> bool:
        """Detect Excel spreadsheet patterns."""
        return (text.count('\t') > 50 or  # Tab-separated values
                len(re.findall(r'\d+\.\d+', text)) > 20)  # Numbers with decimals

    def _parse_html_element(self, element: Tag) -> Optional[Dict[str, Any]]:
        """Parse individual HTML element."""
        if not element.get_text(strip=True):
            return None

        element_type = element.name
        content = element.get_text(strip=True)

        parsed_element = {
            "type": f"html_{element_type}",
            "content": content,
            "tag": element_type,
            "attributes": dict(element.attrs) if element.attrs else {},
            "length": len(content),
            "word_count": len(content.split())
        }

        # Add specific processing for certain elements
        if element_type in ['h1', 'h2', 'h3', 'h4', 'h5', 'h6']:
            parsed_element["heading_level"] = int(element_type[1])
            parsed_element["type"] = "heading"

        elif element_type in ['ul', 'ol']:
            items = element.find_all('li')
            parsed_element["list_items"] = len(items)
            parsed_element["type"] = "list"

        elif element_type == 'table':
            rows = element.find_all('tr')
            parsed_element["table_rows"] = len(rows)
            parsed_element["type"] = "table"

        return parsed_element

    def _extract_meta_tags(self, soup: BeautifulSoup) -> Dict[str, str]:
        """Extract meta tags from HTML."""
        meta_tags = {}
        for meta in soup.find_all('meta'):
            if meta.get('name') and meta.get('content'):
                meta_tags[meta['name']] = meta['content']
        return meta_tags

    def _detect_semantic_html5(self, soup: BeautifulSoup) -> bool:
        """Detect if HTML uses semantic HTML5 elements."""
        semantic_tags = ['article', 'section', 'nav', 'header', 'footer', 'aside', 'main']
        found_tags = [tag for tag in semantic_tags if soup.find(tag)]
        return len(found_tags) >= 2

    def _extract_navigation(self, soup: BeautifulSoup) -> Dict[str, Any]:
        """Extract navigation structure from HTML."""
        nav_elements = soup.find_all('nav')
        nav_links = soup.find_all('a')

        return {
            "nav_elements": len(nav_elements),
            "total_links": len(nav_links),
            "internal_links": len([a for a in nav_links if a.get('href', '').startswith('#')])
        }

    def _identify_content_sections(self, soup: BeautifulSoup) -> List[str]:
        """Identify main content sections in HTML."""
        sections = []

        # Look for common content containers
        content_containers = soup.find_all(['main', 'article', 'section', 'div'])

        for container in content_containers:
            if container.get('id') or container.get('class'):
                identifier = container.get('id') or ' '.join(container.get('class', []))
                if any(keyword in identifier.lower() for keyword in ['content', 'main', 'article', 'post']):
                    sections.append(identifier)

        return sections[:10]  # Limit to first 10

    def _classify_generic_paragraph(self, paragraph: str) -> str:
        """Classify paragraph type for generic content."""
        if len(paragraph) < 20:
            return 'short_text'
        elif paragraph.isupper():
            return 'heading'
        elif paragraph.endswith(':'):
            return 'list_header'
        elif re.match(r'^\d+[.)]\s+', paragraph):
            return 'numbered_item'
        elif re.match(r'^\s*[-*•]\s+', paragraph):
            return 'bullet_item'
        else:
            return 'paragraph'

    def _empty_parse_result(self) -> Dict[str, Any]:
        """Return empty parse result."""
        return {
            "document_type": "unknown",
            "elements": [],
            "metadata": {},
            "structure_indicators": {}
        }