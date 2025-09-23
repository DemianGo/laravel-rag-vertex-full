"""
Advanced document structure analysis with hierarchical detection.
"""

import re
from typing import Dict, Any, List, Tuple, Optional
from collections import defaultdict


class StructureAnalyzer:
    """Analyze document structure and hierarchy."""

    def __init__(self):
        self.header_patterns = self._compile_header_patterns()
        self.section_patterns = self._compile_section_patterns()
        self.numbering_patterns = self._compile_numbering_patterns()

    def analyze_document_structure(self, text: str, file_type: str) -> Dict[str, Any]:
        """
        Main structure analysis function.

        Args:
            text: Document text content
            file_type: Type of document (pdf, docx, etc.)

        Returns:
            Dictionary containing complete structural analysis
        """
        if not text or not text.strip():
            return self._empty_structure_result()

        # Ensure file_type is valid
        if not file_type or not isinstance(file_type, str):
            file_type = 'txt'

        # Detect headers and their levels
        headers = self.detect_headers(text)

        # Extract sections based on headers and content flow
        sections = self.extract_sections(text, headers)

        # Map hierarchical structure
        hierarchy = self.map_hierarchy(headers, sections, text)

        # Analyze content elements
        content_elements = self._analyze_content_elements(text)

        # Calculate structural quality metrics
        quality_metrics = self._calculate_structural_quality(hierarchy, content_elements, text)

        return {
            "document_hierarchy": hierarchy,
            "content_elements": content_elements,
            "structural_quality": quality_metrics,
            "headers_detected": headers,
            "sections_detected": sections,
            "analysis_confidence": self._calculate_analysis_confidence(headers, sections, text)
        }

    def detect_headers(self, text: str) -> List[Dict[str, Any]]:
        """
        Detect document headers with hierarchy levels.

        Args:
            text: Document text

        Returns:
            List of detected headers with metadata
        """
        headers = []
        lines = text.split('\n')

        for line_idx, line in enumerate(lines):
            line_stripped = line.strip()
            if not line_stripped:
                continue

            header_info = self._analyze_header_line(line, line_stripped, line_idx)
            if header_info:
                headers.append(header_info)

        # Sort headers by position and refine levels
        headers.sort(key=lambda x: x['position'])
        return self._refine_header_levels(headers)

    def extract_sections(self, text: str, headers: List[Dict[str, Any]]) -> List[Dict[str, Any]]:
        """
        Extract document sections based on headers and content flow.

        Args:
            text: Document text
            headers: Detected headers

        Returns:
            List of document sections
        """
        if not headers:
            # If no headers, treat entire document as one section
            return [{
                "level": 1,
                "title": "Document Content",
                "start_position": 0,
                "end_position": len(text),
                "content_preview": text[:200].strip(),
                "word_count": len(text.split()),
                "type": "main_content"
            }]

        sections = []
        text_lines = text.split('\n')
        total_chars = 0
        char_positions = []

        # Calculate character positions for each line
        for line in text_lines:
            char_positions.append(total_chars)
            total_chars += len(line) + 1  # +1 for newline

        for i, header in enumerate(headers):
            start_pos = char_positions[header['line_number']] if header['line_number'] < len(char_positions) else 0

            # Find end position (start of next header or end of document)
            if i + 1 < len(headers):
                next_header_line = headers[i + 1]['line_number']
                end_pos = char_positions[next_header_line] if next_header_line < len(char_positions) else len(text)
            else:
                end_pos = len(text)

            # Extract content for this section
            section_content = text[start_pos:end_pos].strip()
            content_lines = section_content.split('\n')[1:]  # Skip header line
            section_text = '\n'.join(content_lines).strip()

            sections.append({
                "level": header['level'],
                "title": header['text'],
                "number": header.get('number'),
                "start_position": start_pos,
                "end_position": end_pos,
                "content_preview": section_text[:200].strip(),
                "word_count": len(section_text.split()) if section_text else 0,
                "type": self._classify_section_type(header['text']),
                "header_info": header
            })

        return sections

    def map_hierarchy(self, headers: List[Dict[str, Any]], sections: List[Dict[str, Any]], text: str) -> Dict[str, Any]:
        """
        Map document hierarchy and create navigation structure.

        Args:
            headers: Detected headers
            sections: Document sections
            text: Full document text

        Returns:
            Hierarchical structure mapping
        """
        if not sections:
            return {"sections": [], "table_of_contents": [], "navigation_tree": {}}

        # Build hierarchical sections
        hierarchical_sections = self._build_section_hierarchy(sections)

        # Generate table of contents
        toc = self._generate_toc(hierarchical_sections)

        # Create navigation tree
        nav_tree = self._create_navigation_tree(hierarchical_sections)

        return {
            "sections": hierarchical_sections,
            "table_of_contents": toc,
            "navigation_tree": nav_tree,
            "max_depth": self._calculate_max_depth(hierarchical_sections),
            "section_balance": self._calculate_section_balance(hierarchical_sections)
        }

    def _compile_header_patterns(self) -> List[Tuple[re.Pattern, int, str]]:
        """Compile regex patterns for header detection."""
        patterns = [
            # Numbered headers: 1. Title, 1.1 Title, etc. - More flexible pattern
            (re.compile(r'^\s*(\d+(?:\.\d+)*)\s*\.?\s*(.+?)\s*$'), 0, 'numbered'),

            # Roman numerals: I. Title, II. Title
            (re.compile(r'^\s*([IVX]+)\.\s+(.+?)\s*$'), 0, 'roman'),

            # Letter headers: A. Title, a) Title
            (re.compile(r'^\s*([A-Za-z])[.)]\s+(.+?)\s*$'), 0, 'letter'),

            # All caps headers - more flexible
            (re.compile(r'^\s*([A-ZÁÉÍÓÚÀÂÊÔÃŨÇ][A-ZÁÉÍÓÚÀÂÊÔÃŨÇ\s]{2,})\s*$'), 1, 'caps'),

            # Title case headers (standalone lines)
            (re.compile(r'^\s*([A-ZÁÉÍÓÚÀÂÊÔÃŨÇ][a-záéíóúàâêôãũç\s]{3,})\s*$'), 2, 'title_case'),

            # Special markers: === Title ===, --- Title ---
            (re.compile(r'^\s*[=-]{3,}\s*([^\n]+?)\s*[=-]{3,}\s*$'), 0, 'decorated'),

            # Markdown-style headers: # Title, ## Title
            (re.compile(r'^\s*(#{1,6})\s+([^\n]+)\s*$'), 0, 'markdown')
        ]
        return patterns

    def _compile_section_patterns(self) -> List[re.Pattern]:
        """Compile patterns for section detection."""
        return [
            re.compile(r'\n\s*\n\s*([A-ZÁÉÍÓÚÀÂÊÔÃŨÇ][^\n]{10,})\s*\n\s*\n', re.IGNORECASE),
            re.compile(r'(?:capítulo|chapter|seção|section|parte|part)\s*\d+', re.IGNORECASE),
            re.compile(r'(?:conclusão|conclusion|introdução|introduction|resumo|abstract)', re.IGNORECASE)
        ]

    def _compile_numbering_patterns(self) -> Dict[str, re.Pattern]:
        """Compile numbering patterns for different styles."""
        return {
            'decimal': re.compile(r'^\d+(?:\.\d+)*'),
            'roman_upper': re.compile(r'^[IVX]+'),
            'roman_lower': re.compile(r'^[ivx]+'),
            'letter_upper': re.compile(r'^[A-Z]'),
            'letter_lower': re.compile(r'^[a-z]'),
            'parenthetical': re.compile(r'^\([a-zA-Z0-9]+\)'),
            'bracketed': re.compile(r'^\[[a-zA-Z0-9]+\]')
        }

    def _analyze_header_line(self, original_line: str, stripped_line: str, line_idx: int) -> Optional[Dict[str, Any]]:
        """Analyze a single line to determine if it's a header."""
        for pattern, base_level, header_type in self.header_patterns:
            match = pattern.match(stripped_line)
            if match:
                if header_type == 'numbered':
                    # Calculate level based on numbering depth
                    numbering = match.group(1)
                    level = len(numbering.split('.'))
                    title = match.group(2).strip()
                    number = numbering
                elif header_type == 'markdown':
                    # Level based on number of # symbols
                    level = len(match.group(1))
                    title = match.group(2).strip()
                    number = None
                elif header_type in ['roman', 'letter']:
                    level = 2
                    title = match.group(2).strip()
                    number = match.group(1)
                else:
                    level = base_level + 1
                    title = match.group(1).strip()
                    number = None

                # Additional validation
                if self._validate_header_candidate(title, stripped_line):
                    return {
                        'text': title,
                        'number': number,
                        'level': level,
                        'line_number': line_idx,
                        'position': line_idx,
                        'type': header_type,
                        'original_line': original_line.strip(),
                        'confidence': self._calculate_header_confidence(stripped_line, header_type)
                    }

        return None

    def _validate_header_candidate(self, title: str, line: str) -> bool:
        """Validate if a candidate is likely a real header."""
        # Too short or too long
        if len(title) < 2 or len(title) > 200:
            return False

        # Contains too many special characters
        special_char_ratio = len(re.findall(r'[^\w\s]', title)) / len(title)
        if special_char_ratio > 0.3:
            return False

        # Contains too many numbers (likely not a title)
        number_ratio = len(re.findall(r'\d', title)) / len(title)
        if number_ratio > 0.5:
            return False

        return True

    def _calculate_header_confidence(self, line: str, header_type: str) -> float:
        """Calculate confidence score for header detection."""
        base_confidence = {
            'numbered': 0.9,
            'roman': 0.8,
            'letter': 0.7,
            'caps': 0.6,
            'title_case': 0.5,
            'decorated': 0.95,
            'markdown': 0.9
        }.get(header_type, 0.5)

        # Adjust based on line characteristics
        if len(line) > 100:
            base_confidence *= 0.8
        if re.search(r'[.!?]$', line):
            base_confidence *= 0.7  # Sentences less likely to be headers

        return min(1.0, base_confidence)

    def _refine_header_levels(self, headers: List[Dict[str, Any]]) -> List[Dict[str, Any]]:
        """Refine header levels for consistency."""
        if not headers:
            return headers

        # Sort by position
        headers.sort(key=lambda x: x['position'])

        # Normalize levels to ensure hierarchy makes sense
        level_mapping = {}
        current_level = 1

        for header in headers:
            original_level = header['level']

            if original_level not in level_mapping:
                level_mapping[original_level] = current_level
                current_level += 1

            header['level'] = level_mapping[original_level]

        return headers

    def _analyze_content_elements(self, text: str) -> Dict[str, Any]:
        """Analyze various content elements in the document."""
        elements = {
            "paragraphs": self._count_paragraphs(text),
            "lists": self._analyze_lists(text),
            "tables": self._detect_tables(text),
            "citations": self._detect_citations(text),
            "footnotes": self._detect_footnotes(text),
            "code_blocks": self._detect_code_blocks(text),
            "quotes": self._detect_quotes(text)
        }

        return elements

    def _count_paragraphs(self, text: str) -> Dict[str, Any]:
        """Count and analyze paragraphs."""
        paragraphs = re.split(r'\n\s*\n', text)
        paragraphs = [p.strip() for p in paragraphs if p.strip()]

        if not paragraphs:
            return {"count": 0, "avg_length": 0, "total_words": 0}

        lengths = [len(p) for p in paragraphs]
        word_counts = [len(p.split()) for p in paragraphs]

        return {
            "count": len(paragraphs),
            "avg_length": sum(lengths) / len(lengths),
            "avg_words": sum(word_counts) / len(word_counts),
            "total_words": sum(word_counts),
            "shortest": min(lengths),
            "longest": max(lengths)
        }

    def _analyze_lists(self, text: str) -> Dict[str, Any]:
        """Analyze lists in the document."""
        numbered_lists = len(re.findall(r'^\s*\d+[.)]\s+', text, re.MULTILINE))
        bulleted_lists = len(re.findall(r'^\s*[-*•]\s+', text, re.MULTILINE))
        letter_lists = len(re.findall(r'^\s*[a-zA-Z][.)]\s+', text, re.MULTILINE))

        return {
            "numbered": numbered_lists,
            "bulleted": bulleted_lists,
            "lettered": letter_lists,
            "total": numbered_lists + bulleted_lists + letter_lists
        }

    def _detect_tables(self, text: str) -> Dict[str, Any]:
        """Detect table-like structures."""
        # Look for pipe-separated content
        pipe_tables = re.findall(r'.*\|.*\|.*', text)

        # Look for tab-separated content
        tab_tables = re.findall(r'.*\t.*\t.*', text)

        # Look for aligned content patterns
        aligned_patterns = []
        lines = text.split('\n')
        for i in range(len(lines) - 1):
            if len(lines[i].split()) >= 3 and len(lines[i+1].split()) >= 3:
                aligned_patterns.append(i)

        table_positions = []
        for i, line in enumerate(lines):
            if '|' in line or '\t' in line:
                table_positions.append(i)

        return {
            "detected": len(pipe_tables) + len(tab_tables) > 0,
            "pipe_separated": len(pipe_tables),
            "tab_separated": len(tab_tables),
            "positions": table_positions[:10],  # Limit to first 10
            "estimated_count": max(len(pipe_tables), len(tab_tables))
        }

    def _detect_citations(self, text: str) -> Dict[str, Any]:
        """Detect citations and references."""
        # Academic citations: [1], (Smith, 2020)
        academic_citations = len(re.findall(r'\[[\d\w\s,.-]+\]|\([A-Za-z]+,?\s*\d{4}\)', text))

        # URL citations
        url_citations = len(re.findall(r'https?://[^\s]+', text))

        # DOI citations
        doi_citations = len(re.findall(r'doi:\s*[\d./]+', text, re.IGNORECASE))

        citation_types = []
        if academic_citations > 0:
            citation_types.append("academic")
        if url_citations > 0:
            citation_types.append("web")
        if doi_citations > 0:
            citation_types.append("doi")

        return {
            "count": academic_citations + url_citations + doi_citations,
            "academic": academic_citations,
            "web_urls": url_citations,
            "doi": doi_citations,
            "types": citation_types
        }

    def _detect_footnotes(self, text: str) -> Dict[str, Any]:
        """Detect footnotes and endnotes."""
        # Superscript-style footnotes
        footnote_refs = len(re.findall(r'\[\d+\]|\(\d+\)', text))

        # Footnote content (lines starting with numbers)
        footnote_content = len(re.findall(r'^\s*\d+\.\s+', text, re.MULTILINE))

        return {
            "count": max(footnote_refs, footnote_content),
            "references": footnote_refs,
            "content_lines": footnote_content
        }

    def _detect_code_blocks(self, text: str) -> Dict[str, Any]:
        """Detect code blocks and programming content."""
        # Markdown code blocks
        code_blocks = len(re.findall(r'```[\s\S]*?```', text))

        # Indented code (4+ spaces)
        indented_code = len(re.findall(r'^\s{4,}[^\s]', text, re.MULTILINE))

        # Programming keywords
        prog_keywords = len(re.findall(r'\b(?:def|function|class|import|return|if|else|for|while)\b', text))

        return {
            "blocks": code_blocks,
            "indented_lines": indented_code,
            "programming_keywords": prog_keywords,
            "detected": code_blocks > 0 or indented_code > 5 or prog_keywords > 10
        }

    def _detect_quotes(self, text: str) -> Dict[str, Any]:
        """Detect quoted content and blockquotes."""
        # Quoted text
        double_quotes = len(re.findall(r'"[^"]{10,}"', text))
        single_quotes = len(re.findall(r"'[^']{10,}'", text))

        # Blockquotes (lines starting with >)
        blockquotes = len(re.findall(r'^\s*>[^\n]+', text, re.MULTILINE))

        return {
            "double_quoted": double_quotes,
            "single_quoted": single_quotes,
            "blockquotes": blockquotes,
            "total": double_quotes + single_quotes + blockquotes
        }

    def _classify_section_type(self, title: str) -> str:
        """Classify section type based on title."""
        title_lower = title.lower()

        if any(word in title_lower for word in ['introdução', 'introduction', 'início']):
            return 'introduction'
        elif any(word in title_lower for word in ['conclusão', 'conclusion', 'final']):
            return 'conclusion'
        elif any(word in title_lower for word in ['método', 'methodology', 'approach']):
            return 'methodology'
        elif any(word in title_lower for word in ['resultado', 'results', 'findings']):
            return 'results'
        elif any(word in title_lower for word in ['discussão', 'discussion', 'analysis']):
            return 'discussion'
        elif any(word in title_lower for word in ['referência', 'references', 'bibliography']):
            return 'references'
        elif any(word in title_lower for word in ['anexo', 'appendix', 'apêndice']):
            return 'appendix'
        else:
            return 'main_content'

    def _build_section_hierarchy(self, sections: List[Dict[str, Any]]) -> List[Dict[str, Any]]:
        """Build hierarchical section structure."""
        if not sections:
            return []

        hierarchical = []
        stack = []

        for section in sections:
            level = section['level']

            # Pop sections from stack that are at same or deeper level
            while stack and stack[-1]['level'] >= level:
                stack.pop()

            # Add subsections array if not present
            section['subsections'] = []

            # If we have a parent, add this section as subsection
            if stack:
                stack[-1]['subsections'].append(section)
            else:
                hierarchical.append(section)

            stack.append(section)

        return hierarchical

    def _generate_toc(self, hierarchical_sections: List[Dict[str, Any]]) -> List[Dict[str, Any]]:
        """Generate table of contents from hierarchical sections."""
        toc = []

        def extract_toc_entries(sections: List[Dict[str, Any]], level: int = 1):
            for section in sections:
                toc.append({
                    "title": section['title'],
                    "level": level,
                    "page": None,  # Could be calculated based on position
                    "position": section['start_position'],
                    "word_count": section['word_count'],
                    "type": section['type']
                })

                if section.get('subsections'):
                    extract_toc_entries(section['subsections'], level + 1)

        extract_toc_entries(hierarchical_sections)
        return toc

    def _create_navigation_tree(self, hierarchical_sections: List[Dict[str, Any]]) -> Dict[str, Any]:
        """Create a navigation tree structure."""
        def build_nav_node(section: Dict[str, Any]) -> Dict[str, Any]:
            node = {
                "id": f"section_{section['start_position']}",
                "title": section['title'],
                "type": section['type'],
                "position": section['start_position'],
                "children": []
            }

            for subsection in section.get('subsections', []):
                node['children'].append(build_nav_node(subsection))

            return node

        return {
            "root": [build_nav_node(section) for section in hierarchical_sections],
            "total_nodes": self._count_total_sections(hierarchical_sections)
        }

    def _count_total_sections(self, sections: List[Dict[str, Any]]) -> int:
        """Count total sections including subsections."""
        total = len(sections)
        for section in sections:
            total += self._count_total_sections(section.get('subsections', []))
        return total

    def _calculate_max_depth(self, hierarchical_sections: List[Dict[str, Any]]) -> int:
        """Calculate maximum depth of section hierarchy."""
        if not hierarchical_sections:
            return 0

        max_depth = 1
        for section in hierarchical_sections:
            if section.get('subsections'):
                subsection_depth = 1 + self._calculate_max_depth(section['subsections'])
                max_depth = max(max_depth, subsection_depth)

        return max_depth

    def _calculate_section_balance(self, hierarchical_sections: List[Dict[str, Any]]) -> float:
        """Calculate how balanced the section structure is."""
        if not hierarchical_sections:
            return 1.0

        # Count sections at each level
        level_counts = defaultdict(int)

        def count_levels(sections: List[Dict[str, Any]], level: int = 1):
            level_counts[level] += len(sections)
            for section in sections:
                if section.get('subsections'):
                    count_levels(section['subsections'], level + 1)

        count_levels(hierarchical_sections)

        # Calculate balance score (higher is more balanced)
        if len(level_counts) <= 1:
            return 1.0

        counts = list(level_counts.values())
        mean_count = sum(counts) / len(counts)
        variance = sum((c - mean_count) ** 2 for c in counts) / len(counts)

        # Normalize to 0-1 scale
        balance_score = 1.0 / (1.0 + variance / mean_count)
        return min(1.0, max(0.0, balance_score))

    def _calculate_structural_quality(self, hierarchy: Dict[str, Any], content_elements: Dict[str, Any], text: str) -> Dict[str, Any]:
        """Calculate overall structural quality metrics."""
        # Hierarchy consistency
        hierarchy_consistency = self._assess_hierarchy_consistency(hierarchy)

        # Section balance
        section_balance = hierarchy.get('section_balance', 0.0)

        # Logical flow score
        logical_flow = self._assess_logical_flow(hierarchy, content_elements)

        # Completeness indicators
        completeness = self._assess_completeness(content_elements, text)

        return {
            "hierarchy_consistency": round(hierarchy_consistency, 2),
            "section_balance": round(section_balance, 2),
            "logical_flow_score": round(logical_flow, 2),
            "completeness_score": round(completeness, 2),
            "overall_score": round((hierarchy_consistency + section_balance + logical_flow + completeness) / 4, 2),
            "completeness_indicators": self._get_completeness_indicators(content_elements)
        }

    def _assess_hierarchy_consistency(self, hierarchy: Dict[str, Any]) -> float:
        """Assess how consistent the document hierarchy is."""
        sections = hierarchy.get('sections', [])
        if not sections:
            return 0.0

        # Check for gaps in numbering levels
        max_depth = hierarchy.get('max_depth', 1)
        if max_depth > 4:  # Too deep is inconsistent
            return 0.6

        # Check section count distribution
        total_sections = hierarchy.get('table_of_contents', [])
        if len(total_sections) < 2:
            return 0.5

        return 0.85  # Good consistency for properly detected structure

    def _assess_logical_flow(self, hierarchy: Dict[str, Any], content_elements: Dict[str, Any]) -> float:
        """Assess logical flow of the document."""
        toc = hierarchy.get('table_of_contents', [])
        if not toc:
            return 0.5

        # Check for introduction and conclusion
        has_intro = any(section['type'] == 'introduction' for section in toc)
        has_conclusion = any(section['type'] == 'conclusion' for section in toc)

        flow_score = 0.5
        if has_intro:
            flow_score += 0.2
        if has_conclusion:
            flow_score += 0.2

        # Check for balanced section lengths
        word_counts = [section.get('word_count', 0) for section in toc if section.get('word_count', 0) > 0]
        if word_counts:
            avg_words = sum(word_counts) / len(word_counts)
            variance = sum((wc - avg_words) ** 2 for wc in word_counts) / len(word_counts)
            balance_factor = 1.0 / (1.0 + variance / max(avg_words, 1))
            flow_score += 0.1 * balance_factor

        return min(1.0, flow_score)

    def _assess_completeness(self, content_elements: Dict[str, Any], text: str) -> float:
        """Assess document completeness."""
        completeness_score = 0.0

        # Check for various elements
        if content_elements.get('paragraphs', {}).get('count', 0) > 0:
            completeness_score += 0.3

        if content_elements.get('lists', {}).get('total', 0) > 0:
            completeness_score += 0.1

        if content_elements.get('tables', {}).get('detected', False):
            completeness_score += 0.1

        if content_elements.get('citations', {}).get('count', 0) > 0:
            completeness_score += 0.2

        # Check document length
        word_count = len(text.split())
        if word_count > 100:
            completeness_score += 0.1
        if word_count > 500:
            completeness_score += 0.1
        if word_count > 1000:
            completeness_score += 0.1

        return min(1.0, completeness_score)

    def _get_completeness_indicators(self, content_elements: Dict[str, Any]) -> List[str]:
        """Get list of completeness indicators."""
        indicators = []

        paragraphs = content_elements.get('paragraphs', {})
        if paragraphs.get('count', 0) > 0:
            indicators.append(f"Contains {paragraphs['count']} paragraphs")

        lists = content_elements.get('lists', {})
        if lists.get('total', 0) > 0:
            indicators.append(f"Contains {lists['total']} lists")

        if content_elements.get('tables', {}).get('detected', False):
            indicators.append("Contains tabular data")

        citations = content_elements.get('citations', {})
        if citations.get('count', 0) > 0:
            indicators.append(f"Contains {citations['count']} citations")

        if content_elements.get('footnotes', {}).get('count', 0) > 0:
            indicators.append("Contains footnotes")

        return indicators

    def _calculate_analysis_confidence(self, headers: List[Dict[str, Any]], sections: List[Dict[str, Any]], text: str) -> float:
        """Calculate confidence in the structural analysis."""
        if not text.strip():
            return 0.0

        confidence_factors = []

        # Header detection confidence
        if headers:
            avg_header_confidence = sum(h.get('confidence', 0.5) for h in headers) / len(headers)
            confidence_factors.append(avg_header_confidence)
        else:
            confidence_factors.append(0.3)  # Low confidence without headers

        # Section count factor
        if len(sections) > 1:
            confidence_factors.append(0.8)
        elif len(sections) == 1:
            confidence_factors.append(0.5)
        else:
            confidence_factors.append(0.2)

        # Document length factor
        word_count = len(text.split())
        if word_count > 1000:
            confidence_factors.append(0.9)
        elif word_count > 300:
            confidence_factors.append(0.7)
        else:
            confidence_factors.append(0.5)

        return sum(confidence_factors) / len(confidence_factors)

    def _empty_structure_result(self) -> Dict[str, Any]:
        """Return empty structure analysis result."""
        return {
            "document_hierarchy": {"sections": [], "table_of_contents": [], "navigation_tree": {}},
            "content_elements": {},
            "structural_quality": {"hierarchy_consistency": 0, "section_balance": 0, "logical_flow_score": 0, "completeness_indicators": []},
            "headers_detected": 0,
            "sections_detected": 0,
            "analysis_confidence": 0.0
        }