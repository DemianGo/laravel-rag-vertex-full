"""
Advanced section detection for different document types and styles.
"""

import re
from typing import Dict, Any, List, Optional, Tuple
from enum import Enum


class DocumentProfile(Enum):
    """Document type profiles for section detection."""
    ACADEMIC = "academic"
    TECHNICAL = "technical"
    LEGAL = "legal"
    NARRATIVE = "narrative"
    BUSINESS = "business"
    GENERIC = "generic"


class SectionType(Enum):
    """Types of document sections."""
    INTRODUCTION = "introduction"
    METHODOLOGY = "methodology"
    RESULTS = "results"
    DISCUSSION = "discussion"
    CONCLUSION = "conclusion"
    REFERENCES = "references"
    APPENDIX = "appendix"
    ABSTRACT = "abstract"
    ACKNOWLEDGMENTS = "acknowledgments"
    MAIN_CONTENT = "main_content"
    CHAPTER = "chapter"
    SUBSECTION = "subsection"


class SectionDetector:
    """Detect logical sections in documents based on content and structure."""

    def __init__(self, document_profile: DocumentProfile = DocumentProfile.GENERIC):
        self.document_profile = document_profile
        self.section_patterns = self._compile_section_patterns()
        self.profile_keywords = self._load_profile_keywords()

    def detect_sections(self, text: str, file_type: str = "txt") -> Dict[str, Any]:
        """
        Main section detection function.

        Args:
            text: Document text
            file_type: Type of document (pdf, docx, etc.)

        Returns:
            Dictionary containing detected sections and metadata
        """
        if not text or not text.strip():
            return self._empty_detection_result()

        # Ensure file_type is valid
        if not file_type or not isinstance(file_type, str):
            file_type = 'txt'

        # Preprocess text
        processed_text = self._preprocess_text(text)

        # Detect section boundaries
        boundaries = self._detect_section_boundaries(processed_text, file_type)

        # Classify sections
        sections = self._classify_sections(processed_text, boundaries)

        # Calculate confidence scores
        sections = self._calculate_section_confidence(sections, processed_text)

        # Post-process and validate
        sections = self._post_process_sections(sections, processed_text)

        return {
            "sections": sections,
            "total_sections": len(sections),
            "document_profile": self.document_profile.value,
            "detection_confidence": self._calculate_overall_confidence(sections),
            "section_types": self._count_section_types(sections),
            "metadata": {
                "boundaries_detected": len(boundaries),
                "avg_section_length": self._calculate_avg_section_length(sections),
                "document_structure_score": self._assess_document_structure(sections)
            }
        }

    def _compile_section_patterns(self) -> Dict[str, List[re.Pattern]]:
        """Compile regex patterns for section detection."""
        return {
            "section_headers": [
                re.compile(r'^\s*(\d+(?:\.\d+)*)\s*\.?\s*([A-ZÁÉÍÓÚÀÂÊÔÃŨÇ][^\n]{3,})\s*$', re.IGNORECASE),
                re.compile(r'^\s*([IVX]+)\.\s*([A-ZÁÉÍÓÚÀÂÊÔÃŨÇ][^\n]{3,})\s*$', re.IGNORECASE),
                re.compile(r'^\s*([A-Za-z])[.)]\s*([A-ZÁÉÍÓÚÀÂÊÔÃŨÇ][^\n]{3,})\s*$'),
                re.compile(r'^\s*(#{1,6})\s+([A-ZÁÉÍÓÚÀÂÊÔÃŨÇ][^\n]+)\s*$'),
                re.compile(r'^\s*([A-ZÁÉÍÓÚÀÂÊÔÃŨÇ][A-ZÁÉÍÓÚÀÂÊÔÃŨÇ\s]{3,})\s*$')
            ],
            "section_breaks": [
                re.compile(r'\n\s*\n\s*([A-ZÁÉÍÓÚÀÂÊÔÃŨÇ][^\n]{10,})\s*\n\s*\n', re.IGNORECASE),
                re.compile(r'={3,}.*?={3,}'),
                re.compile(r'-{3,}.*?-{3,}'),
                re.compile(r'\*{3,}.*?\*{3,}')
            ],
            "page_breaks": [
                re.compile(r'Page\s+\d+', re.IGNORECASE),
                re.compile(r'Página\s+\d+', re.IGNORECASE),
                re.compile(r'^\s*\d+\s*$')
            ],
            "chapter_markers": [
                re.compile(r'(?:Chapter|Capítulo|Cap\.)\s*\d+', re.IGNORECASE),
                re.compile(r'(?:Part|Parte)\s*[IVX]+', re.IGNORECASE),
                re.compile(r'(?:Section|Seção)\s*\d+', re.IGNORECASE)
            ]
        }

    def _load_profile_keywords(self) -> Dict[str, Dict[str, List[str]]]:
        """Load keywords for different document profiles."""
        return {
            DocumentProfile.ACADEMIC.value: {
                "introduction": ["introduction", "introdução", "background", "overview"],
                "methodology": ["methodology", "metodologia", "methods", "métodos", "approach"],
                "results": ["results", "resultados", "findings", "achados"],
                "discussion": ["discussion", "discussão", "analysis", "análise"],
                "conclusion": ["conclusion", "conclusão", "summary", "resumo", "final"],
                "references": ["references", "referências", "bibliography", "bibliografia"],
                "abstract": ["abstract", "resumo", "summary", "sumário"],
                "acknowledgments": ["acknowledgments", "agradecimentos", "thanks"]
            },
            DocumentProfile.TECHNICAL.value: {
                "overview": ["overview", "visão geral", "introduction", "introdução"],
                "requirements": ["requirements", "requisitos", "specifications", "especificações"],
                "architecture": ["architecture", "arquitetura", "design", "projeto"],
                "implementation": ["implementation", "implementação", "development"],
                "testing": ["testing", "testes", "validation", "validação"],
                "deployment": ["deployment", "implantação", "installation"],
                "maintenance": ["maintenance", "manutenção", "support"]
            },
            DocumentProfile.LEGAL.value: {
                "preamble": ["whereas", "considerando", "preamble", "preâmbulo"],
                "definitions": ["definitions", "definições", "terms", "termos"],
                "clauses": ["clause", "cláusula", "article", "artigo"],
                "obligations": ["obligations", "obrigações", "duties", "deveres"],
                "rights": ["rights", "direitos", "privileges", "privilégios"],
                "penalties": ["penalties", "penalidades", "sanctions", "sanções"],
                "final_provisions": ["final", "finais", "closing", "fechamento"]
            },
            DocumentProfile.BUSINESS.value: {
                "executive_summary": ["executive summary", "sumário executivo", "overview"],
                "market_analysis": ["market analysis", "análise de mercado", "market"],
                "business_model": ["business model", "modelo de negócio"],
                "financial_projections": ["financial", "financeiro", "projections"],
                "strategy": ["strategy", "estratégia", "strategic", "estratégico"],
                "operations": ["operations", "operações", "operational"],
                "risks": ["risks", "riscos", "risk analysis"]
            },
            DocumentProfile.NARRATIVE.value: {
                "prologue": ["prologue", "prólogo", "preface", "prefácio"],
                "chapters": ["chapter", "capítulo", "part", "parte"],
                "epilogue": ["epilogue", "epílogo", "conclusion", "conclusão"],
                "appendices": ["appendix", "apêndice", "notes", "notas"]
            }
        }

    def _preprocess_text(self, text: str) -> str:
        """Preprocess text for better section detection."""
        # Normalize whitespace
        text = re.sub(r'\r\n', '\n', text)
        text = re.sub(r'\r', '\n', text)

        # Remove excessive whitespace but preserve structure
        lines = text.split('\n')
        processed_lines = []

        for line in lines:
            # Keep empty lines for structure but normalize
            if not line.strip():
                processed_lines.append('')
            else:
                # Normalize internal whitespace
                normalized = re.sub(r'\s+', ' ', line.strip())
                processed_lines.append(normalized)

        return '\n'.join(processed_lines)

    def _detect_section_boundaries(self, text: str, file_type: str) -> List[Dict[str, Any]]:
        """Detect potential section boundaries."""
        boundaries = []
        lines = text.split('\n')

        for line_num, line in enumerate(lines):
            line_stripped = line.strip()
            if not line_stripped:
                continue

            # Check for header patterns
            boundary_info = self._analyze_potential_boundary(line_stripped, line_num, lines)
            if boundary_info:
                boundaries.append(boundary_info)

        # Add document start and end as boundaries
        if boundaries:
            if boundaries[0]['line_number'] > 0:
                boundaries.insert(0, {
                    'line_number': 0,
                    'text': '',
                    'type': 'document_start',
                    'confidence': 1.0
                })

            boundaries.append({
                'line_number': len(lines) - 1,
                'text': '',
                'type': 'document_end',
                'confidence': 1.0
            })

        return sorted(boundaries, key=lambda x: x['line_number'])

    def _analyze_potential_boundary(self, line: str, line_num: int, all_lines: List[str]) -> Optional[Dict[str, Any]]:
        """Analyze line as potential section boundary."""
        # Check header patterns
        for pattern_group, patterns in self.section_patterns.items():
            for pattern in patterns:
                match = pattern.search(line)
                if match:
                    confidence = self._calculate_boundary_confidence(line, line_num, all_lines, pattern_group)
                    if confidence > 0.5:
                        return {
                            'line_number': line_num,
                            'text': line,
                            'type': pattern_group,
                            'confidence': confidence,
                            'match_groups': match.groups() if match.groups() else None
                        }

        # Check for implicit boundaries (content transitions)
        if self._is_implicit_boundary(line, line_num, all_lines):
            return {
                'line_number': line_num,
                'text': line,
                'type': 'implicit_boundary',
                'confidence': 0.6
            }

        return None

    def _calculate_boundary_confidence(self, line: str, line_num: int, all_lines: List[str], pattern_type: str) -> float:
        """Calculate confidence score for boundary detection."""
        confidence = 0.5

        # Pattern type bonuses
        type_bonuses = {
            'section_headers': 0.3,
            'section_breaks': 0.2,
            'page_breaks': 0.1,
            'chapter_markers': 0.4
        }
        confidence += type_bonuses.get(pattern_type, 0)

        # Position factors
        if line_num < len(all_lines) * 0.1:  # Near beginning
            confidence += 0.1
        if line_num > len(all_lines) * 0.9:  # Near end
            confidence += 0.05

        # Context factors
        if line_num > 0 and not all_lines[line_num - 1].strip():  # Preceded by blank
            confidence += 0.1
        if line_num < len(all_lines) - 1 and not all_lines[line_num + 1].strip():  # Followed by blank
            confidence += 0.1

        # Length and format factors
        if 5 <= len(line) <= 80:  # Good header length
            confidence += 0.1
        if line[0].isupper():  # Starts with capital
            confidence += 0.05
        if not line.endswith('.'):  # Headers don't usually end with periods
            confidence += 0.05

        return min(1.0, confidence)

    def _is_implicit_boundary(self, line: str, line_num: int, all_lines: List[str]) -> bool:
        """Check if line represents an implicit section boundary."""
        line_lower = line.lower()

        # Check for profile-specific keywords
        profile_keywords = self.profile_keywords.get(self.document_profile.value, {})

        for section_type, keywords in profile_keywords.items():
            for keyword in keywords:
                if keyword.lower() in line_lower and len(line) < 100:
                    # Additional validation
                    if self._validate_implicit_boundary(line, line_num, all_lines):
                        return True

        return False

    def _validate_implicit_boundary(self, line: str, line_num: int, all_lines: List[str]) -> bool:
        """Validate implicit boundary candidate."""
        # Should be reasonably short (likely a header)
        if len(line) > 150:
            return False

        # Should not be part of a paragraph (check surrounding context)
        context_lines = 2
        start_idx = max(0, line_num - context_lines)
        end_idx = min(len(all_lines), line_num + context_lines + 1)

        surrounding_text = ' '.join(all_lines[start_idx:end_idx]).strip()

        # If surrounded by a lot of text, probably not a boundary
        if len(surrounding_text) > 500 and line in surrounding_text:
            return False

        return True

    def _classify_sections(self, text: str, boundaries: List[Dict[str, Any]]) -> List[Dict[str, Any]]:
        """Classify sections based on boundaries and content."""
        sections = []

        for i in range(len(boundaries) - 1):
            start_boundary = boundaries[i]
            end_boundary = boundaries[i + 1]

            # Extract section content
            section_text = self._extract_section_text(text, start_boundary, end_boundary)

            if section_text.strip():
                section_type = self._classify_section_content(section_text, start_boundary)

                sections.append({
                    'title': self._extract_section_title(start_boundary, section_text),
                    'type': section_type,
                    'content': section_text,
                    'start_line': start_boundary['line_number'],
                    'end_line': end_boundary['line_number'],
                    'word_count': len(section_text.split()),
                    'char_count': len(section_text),
                    'boundary_confidence': start_boundary.get('confidence', 0.5)
                })

        return sections

    def _extract_section_text(self, text: str, start_boundary: Dict[str, Any], end_boundary: Dict[str, Any]) -> str:
        """Extract text content of a section."""
        lines = text.split('\n')
        start_line = start_boundary['line_number']
        end_line = end_boundary['line_number']

        # Skip the header line itself for content
        content_start = start_line + 1 if start_boundary['type'] != 'document_start' else start_line
        section_lines = lines[content_start:end_line]

        return '\n'.join(section_lines).strip()

    def _extract_section_title(self, boundary: Dict[str, Any], content: str) -> str:
        """Extract title from section boundary or content."""
        if boundary['type'] == 'document_start':
            # For document start, try to find title in first few lines
            first_lines = content.split('\n')[:3]
            for line in first_lines:
                if line.strip() and len(line.strip()) < 100:
                    return line.strip()
            return "Document Start"

        elif boundary['type'] == 'document_end':
            return "Document End"

        else:
            # Use the boundary text as title
            boundary_text = boundary.get('text', '').strip()

            # Clean up numbered titles
            boundary_text = re.sub(r'^\d+(\.\d+)*\s*\.?\s*', '', boundary_text)
            boundary_text = re.sub(r'^[A-Za-z][.)]\s*', '', boundary_text)
            boundary_text = re.sub(r'^[IVX]+\.\s*', '', boundary_text, flags=re.IGNORECASE)
            boundary_text = re.sub(r'^#{1,6}\s*', '', boundary_text)

            return boundary_text or f"Section {boundary['line_number']}"

    def _classify_section_content(self, content: str, boundary: Dict[str, Any]) -> str:
        """Classify section type based on content and context."""
        content_lower = content.lower()
        title_lower = boundary.get('text', '').lower()

        # Check profile-specific keywords
        profile_keywords = self.profile_keywords.get(self.document_profile.value, {})

        # Score each section type
        type_scores = {}

        for section_type, keywords in profile_keywords.items():
            score = 0
            for keyword in keywords:
                keyword_lower = keyword.lower()
                # Higher weight for title matches
                if keyword_lower in title_lower:
                    score += 10
                # Lower weight for content matches
                content_matches = content_lower.count(keyword_lower)
                score += content_matches * 2

            if score > 0:
                type_scores[section_type] = score

        # Return highest scoring type
        if type_scores:
            return max(type_scores.items(), key=lambda x: x[1])[0]

        # Default classification based on position and length
        return self._default_section_classification(content, boundary)

    def _default_section_classification(self, content: str, boundary: Dict[str, Any]) -> str:
        """Default section classification when no specific type is detected."""
        line_num = boundary.get('line_number', 0)
        content_length = len(content.split())

        # Very short sections might be headers or transitions
        if content_length < 50:
            return 'subsection'

        # First substantial section is often introduction
        if line_num < 100:
            return 'introduction'

        # Last section is often conclusion
        # (This would need total line count context)

        return 'main_content'

    def _calculate_section_confidence(self, sections: List[Dict[str, Any]], text: str) -> List[Dict[str, Any]]:
        """Calculate confidence scores for section classifications."""
        for section in sections:
            base_confidence = section.get('boundary_confidence', 0.5)

            # Adjust based on content characteristics
            content_length = section.get('word_count', 0)
            if content_length > 50:  # Substantial content
                base_confidence += 0.1
            if content_length > 200:  # Good amount of content
                base_confidence += 0.1

            # Type-specific confidence adjustments
            section_type = section.get('type', '')
            if section_type in ['introduction', 'conclusion', 'abstract']:
                base_confidence += 0.15  # These are usually well-defined

            section['classification_confidence'] = min(1.0, base_confidence)

        return sections

    def _post_process_sections(self, sections: List[Dict[str, Any]], text: str) -> List[Dict[str, Any]]:
        """Post-process sections to improve quality."""
        # Remove very small sections (likely artifacts)
        filtered_sections = []
        for section in sections:
            if section.get('word_count', 0) >= 5:  # At least 5 words
                filtered_sections.append(section)

        # Merge adjacent sections of same type if they're very small
        merged_sections = self._merge_similar_small_sections(filtered_sections)

        # Add section indices
        for i, section in enumerate(merged_sections):
            section['section_index'] = i

        return merged_sections

    def _merge_similar_small_sections(self, sections: List[Dict[str, Any]]) -> List[Dict[str, Any]]:
        """Merge adjacent small sections of the same type."""
        if len(sections) <= 1:
            return sections

        merged = [sections[0]]

        for current_section in sections[1:]:
            last_section = merged[-1]

            # Merge if same type and both are small
            if (current_section['type'] == last_section['type'] and
                current_section.get('word_count', 0) < 100 and
                last_section.get('word_count', 0) < 100):

                # Merge content
                merged_content = last_section['content'] + '\n\n' + current_section['content']
                last_section['content'] = merged_content
                last_section['word_count'] = len(merged_content.split())
                last_section['char_count'] = len(merged_content)
                last_section['end_line'] = current_section['end_line']
            else:
                merged.append(current_section)

        return merged

    def _calculate_overall_confidence(self, sections: List[Dict[str, Any]]) -> float:
        """Calculate overall confidence in section detection."""
        if not sections:
            return 0.0

        confidences = [section.get('classification_confidence', 0.5) for section in sections]
        return sum(confidences) / len(confidences)

    def _count_section_types(self, sections: List[Dict[str, Any]]) -> Dict[str, int]:
        """Count sections by type."""
        type_counts = {}
        for section in sections:
            section_type = section.get('type', 'unknown')
            type_counts[section_type] = type_counts.get(section_type, 0) + 1
        return type_counts

    def _calculate_avg_section_length(self, sections: List[Dict[str, Any]]) -> float:
        """Calculate average section length in words."""
        if not sections:
            return 0.0

        total_words = sum(section.get('word_count', 0) for section in sections)
        return total_words / len(sections)

    def _assess_document_structure(self, sections: List[Dict[str, Any]]) -> float:
        """Assess overall document structure quality."""
        if not sections:
            return 0.0

        structure_score = 0.5

        # Bonus for having introduction and conclusion
        types = [section.get('type', '') for section in sections]
        if 'introduction' in types:
            structure_score += 0.2
        if 'conclusion' in types:
            structure_score += 0.2

        # Bonus for balanced section lengths
        word_counts = [section.get('word_count', 0) for section in sections]
        if word_counts:
            avg_words = sum(word_counts) / len(word_counts)
            variance = sum((wc - avg_words) ** 2 for wc in word_counts) / len(word_counts)
            balance_factor = 1.0 / (1.0 + variance / max(avg_words, 1))
            structure_score += 0.1 * balance_factor

        return min(1.0, structure_score)

    def _empty_detection_result(self) -> Dict[str, Any]:
        """Return empty detection result."""
        return {
            "sections": [],
            "total_sections": 0,
            "document_profile": self.document_profile.value,
            "detection_confidence": 0.0,
            "section_types": {},
            "metadata": {
                "boundaries_detected": 0,
                "avg_section_length": 0.0,
                "document_structure_score": 0.0
            }
        }


# Convenience function for quick section detection
def detect_sections(text: str, file_type: str = "txt", profile: str = "generic") -> Dict[str, Any]:
    """
    Quick section detection function.

    Args:
        text: Document text
        file_type: Document file type
        profile: Document profile (academic, technical, legal, etc.)

    Returns:
        Section detection results
    """
    try:
        doc_profile = DocumentProfile(profile)
    except ValueError:
        doc_profile = DocumentProfile.GENERIC

    detector = SectionDetector(doc_profile)
    return detector.detect_sections(text, file_type)