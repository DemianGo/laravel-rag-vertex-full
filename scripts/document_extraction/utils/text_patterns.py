"""
Text pattern recognition for titles, lists, citations, and other document elements.
"""

import re
from typing import Dict, List, Tuple, Optional, Any
from enum import Enum


class LanguageProfile(Enum):
    """Supported language profiles."""
    PORTUGUESE = "pt"
    ENGLISH = "en"
    SPANISH = "es"
    GENERIC = "generic"


class PatternType(Enum):
    """Types of text patterns."""
    TITLE = "title"
    LIST_ITEM = "list_item"
    CITATION = "citation"
    REFERENCE = "reference"
    QUOTE = "quote"
    FOOTNOTE = "footnote"
    CODE = "code"
    TABLE = "table"


class TextPatternRecognizer:
    """Recognize various text patterns in documents."""

    def __init__(self, language_profile: LanguageProfile = LanguageProfile.GENERIC):
        self.language_profile = language_profile
        self.patterns = self._compile_patterns()
        self.language_specific = self._load_language_specific_patterns()

    def recognize_patterns(self, text: str) -> List[Dict[str, Any]]:
        """
        Recognize all patterns in text.

        Args:
            text: Input text to analyze

        Returns:
            List of recognized patterns with metadata
        """
        recognized = []
        lines = text.split('\n')

        for line_num, line in enumerate(lines):
            line_patterns = self.analyze_line(line, line_num)
            recognized.extend(line_patterns)

        # Post-process to resolve conflicts and improve accuracy
        return self._post_process_patterns(recognized, text)

    def analyze_line(self, line: str, line_number: int) -> List[Dict[str, Any]]:
        """
        Analyze single line for patterns.

        Args:
            line: Text line to analyze
            line_number: Line number in document

        Returns:
            List of patterns found in this line
        """
        patterns_found = []
        line_stripped = line.strip()

        if not line_stripped:
            return patterns_found

        # Check each pattern type
        for pattern_type in PatternType:
            matches = self._match_pattern_type(line, line_stripped, pattern_type)
            for match in matches:
                patterns_found.append({
                    "type": pattern_type.value,
                    "line_number": line_number,
                    "text": line_stripped,
                    "match": match,
                    "confidence": self._calculate_confidence(match, pattern_type, line_stripped),
                    "language_profile": self.language_profile.value
                })

        return patterns_found

    def detect_titles(self, text: str) -> List[Dict[str, Any]]:
        """
        Detect titles and headings in text.

        Args:
            text: Input text

        Returns:
            List of detected titles
        """
        titles = []
        lines = text.split('\n')

        for line_num, line in enumerate(lines):
            line_stripped = line.strip()
            if not line_stripped:
                continue

            title_info = self._analyze_title_candidate(line_stripped, line_num, lines)
            if title_info:
                titles.append(title_info)

        return self._rank_titles(titles)

    def detect_lists(self, text: str) -> Dict[str, Any]:
        """
        Detect and classify lists in text.

        Args:
            text: Input text

        Returns:
            Dictionary containing list analysis
        """
        lists = {
            "numbered": [],
            "bulleted": [],
            "lettered": [],
            "mixed": [],
            "total_items": 0
        }

        lines = text.split('\n')
        current_list = None
        list_buffer = []

        for line_num, line in enumerate(lines):
            line_stripped = line.strip()
            if not line_stripped:
                if current_list and list_buffer:
                    self._finalize_list(current_list, list_buffer, lists)
                    current_list = None
                    list_buffer = []
                continue

            list_type = self._detect_list_item_type(line_stripped)
            if list_type:
                if current_list != list_type:
                    if current_list and list_buffer:
                        self._finalize_list(current_list, list_buffer, lists)
                    current_list = list_type
                    list_buffer = []

                list_buffer.append({
                    "line_number": line_num,
                    "text": line_stripped,
                    "marker": self._extract_list_marker(line_stripped, list_type)
                })

        # Finalize last list
        if current_list and list_buffer:
            self._finalize_list(current_list, list_buffer, lists)

        lists["total_items"] = sum(len(items) for items in [lists["numbered"], lists["bulleted"], lists["lettered"], lists["mixed"]])
        return lists

    def detect_citations(self, text: str) -> List[Dict[str, Any]]:
        """
        Detect citations and references.

        Args:
            text: Input text

        Returns:
            List of detected citations
        """
        citations = []

        # Academic citations: [1], (Smith, 2020)
        academic_pattern = re.compile(r'(\[[\d\w\s,.-]+\]|\([A-Za-z]+[,\s]+\d{4}[a-z]?\))')
        for match in academic_pattern.finditer(text):
            citations.append({
                "type": "academic",
                "text": match.group(1),
                "position": match.start(),
                "format": self._classify_citation_format(match.group(1))
            })

        # URL citations
        url_pattern = re.compile(r'(https?://[^\s\)]+)')
        for match in url_pattern.finditer(text):
            citations.append({
                "type": "web_url",
                "text": match.group(1),
                "position": match.start(),
                "domain": self._extract_domain(match.group(1))
            })

        # DOI citations
        doi_pattern = re.compile(r'(doi:\s*[\d./\-\w]+)', re.IGNORECASE)
        for match in doi_pattern.finditer(text):
            citations.append({
                "type": "doi",
                "text": match.group(1),
                "position": match.start(),
                "identifier": match.group(1).replace('doi:', '').strip()
            })

        return sorted(citations, key=lambda x: x["position"])

    def detect_quotes(self, text: str) -> List[Dict[str, Any]]:
        """
        Detect quoted content and blockquotes.

        Args:
            text: Input text

        Returns:
            List of detected quotes
        """
        quotes = []

        # Double quotes
        double_quote_pattern = re.compile(r'"([^"]{10,})"')
        for match in double_quote_pattern.finditer(text):
            quotes.append({
                "type": "double_quoted",
                "text": match.group(1),
                "position": match.start(),
                "length": len(match.group(1)),
                "quote_style": "standard"
            })

        # Single quotes
        single_quote_pattern = re.compile(r"'([^']{10,})'")
        for match in single_quote_pattern.finditer(text):
            quotes.append({
                "type": "single_quoted",
                "text": match.group(1),
                "position": match.start(),
                "length": len(match.group(1)),
                "quote_style": "alternative"
            })

        # Blockquotes (lines starting with >)
        lines = text.split('\n')
        blockquote_buffer = []

        for line_num, line in enumerate(lines):
            if line.strip().startswith('>'):
                blockquote_buffer.append({
                    "line_number": line_num,
                    "text": line.strip()[1:].strip()  # Remove > and whitespace
                })
            else:
                if blockquote_buffer:
                    quotes.append({
                        "type": "blockquote",
                        "text": '\n'.join(item["text"] for item in blockquote_buffer),
                        "start_line": blockquote_buffer[0]["line_number"],
                        "end_line": blockquote_buffer[-1]["line_number"],
                        "line_count": len(blockquote_buffer)
                    })
                    blockquote_buffer = []

        return quotes

    def _compile_patterns(self) -> Dict[str, List[re.Pattern]]:
        """Compile regex patterns for different element types."""
        return {
            PatternType.TITLE.value: [
                re.compile(r'^([A-ZÁÉÍÓÚÀÂÊÔÃŨÇ][a-záéíóúàâêôãũç\s]{5,100})$'),  # Title case
                re.compile(r'^([A-ZÁÉÍÓÚÀÂÊÔÃŨÇ\s]{5,100})$'),  # All caps
                re.compile(r'^(\d+(?:\.\d+)*)\s*\.?\s*([A-ZÁÉÍÓÚÀÂÊÔÃŨÇ][^\n]{5,})$'),  # Numbered
                re.compile(r'^(#{1,6})\s+(.+)$'),  # Markdown style
                re.compile(r'^([=-]{3,})\s*(.+?)\s*([=-]{3,})$')  # Decorated
            ],
            PatternType.LIST_ITEM.value: [
                re.compile(r'^\s*(\d+)[.)]\s+(.+)$'),  # Numbered: 1. item
                re.compile(r'^\s*([a-zA-Z])[.)]\s+(.+)$'),  # Lettered: a) item
                re.compile(r'^\s*([IVX]+)[.)]\s+(.+)$'),  # Roman: I. item
                re.compile(r'^\s*([-*•▪▫◦‣⁃])\s+(.+)$'),  # Bulleted
                re.compile(r'^\s*(\([a-zA-Z0-9]+\))\s+(.+)$'),  # Parenthetical: (a) item
                re.compile(r'^\s*(\[[a-zA-Z0-9]+\])\s+(.+)$')  # Bracketed: [1] item
            ],
            PatternType.CITATION.value: [
                re.compile(r'(\[[\d\w\s,.-]+\])'),  # [1], [Smith 2020]
                re.compile(r'(\([A-Za-z]+[,\s]+\d{4}[a-z]?\))'),  # (Smith, 2020)
                re.compile(r'((?:https?://|www\.)[^\s\)]+)'),  # URLs
                re.compile(r'(doi:\s*[\d./\-\w]+)', re.IGNORECASE)  # DOI
            ],
            PatternType.FOOTNOTE.value: [
                re.compile(r'^(\d+)\s+(.+)$'),  # Footnote content
                re.compile(r'(\[\d+\])'),  # Footnote reference [1]
                re.compile(r'(\(\d+\))')  # Footnote reference (1)
            ],
            PatternType.CODE.value: [
                re.compile(r'```[\s\S]*?```'),  # Code blocks
                re.compile(r'`([^`]+)`'),  # Inline code
                re.compile(r'^\s{4,}([^\s].*)$'),  # Indented code
                re.compile(r'\b(?:def|function|class|import|return|if|else|for|while)\b')  # Keywords
            ],
            PatternType.TABLE.value: [
                re.compile(r'.*\|.*\|.*'),  # Pipe-separated
                re.compile(r'.*\t.*\t.*'),  # Tab-separated
                re.compile(r'.+\s{3,}.+\s{3,}.+')  # Space-aligned columns
            ]
        }

    def _load_language_specific_patterns(self) -> Dict[str, Dict[str, Any]]:
        """Load language-specific patterns and keywords."""
        patterns = {
            LanguageProfile.PORTUGUESE.value: {
                "section_keywords": [
                    "capítulo", "seção", "parte", "título", "subtítulo",
                    "introdução", "conclusão", "resumo", "abstract",
                    "metodologia", "resultados", "discussão", "referências"
                ],
                "list_keywords": ["lista", "itens", "pontos", "tópicos"],
                "reference_keywords": ["bibliografia", "referências", "fontes", "citações"],
                "quote_indicators": ["citação", "conforme", "segundo", "de acordo com"]
            },
            LanguageProfile.ENGLISH.value: {
                "section_keywords": [
                    "chapter", "section", "part", "title", "subtitle",
                    "introduction", "conclusion", "summary", "abstract",
                    "methodology", "results", "discussion", "references"
                ],
                "list_keywords": ["list", "items", "points", "topics"],
                "reference_keywords": ["bibliography", "references", "sources", "citations"],
                "quote_indicators": ["quote", "according to", "as stated", "citing"]
            },
            LanguageProfile.SPANISH.value: {
                "section_keywords": [
                    "capítulo", "sección", "parte", "título", "subtítulo",
                    "introducción", "conclusión", "resumen", "abstract",
                    "metodología", "resultados", "discusión", "referencias"
                ],
                "list_keywords": ["lista", "elementos", "puntos", "temas"],
                "reference_keywords": ["bibliografía", "referencias", "fuentes", "citas"],
                "quote_indicators": ["cita", "según", "conforme a", "citando"]
            },
            "generic": {
                "title_indicators": ["title", "chapter", "section", "part", "heading"],
                "list_indicators": ["item", "point", "note", "step"],
                "quote_indicators": ["quote", "citation", "according", "citing"]
            }
        }

        return patterns.get(self.language_profile.value, patterns.get("generic", {}))

    def _match_pattern_type(self, line: str, line_stripped: str, pattern_type: PatternType) -> List[Dict[str, Any]]:
        """Match specific pattern type against line."""
        matches = []
        type_patterns = self.patterns.get(pattern_type.value, [])

        for pattern in type_patterns:
            match = pattern.search(line_stripped)
            if match:
                matches.append({
                    "pattern_type": pattern_type.value,
                    "matched_text": match.group(0),
                    "groups": match.groups(),
                    "span": match.span(),
                    "pattern": pattern.pattern
                })

        return matches

    def _calculate_confidence(self, match: Dict[str, Any], pattern_type: PatternType, line: str) -> float:
        """Calculate confidence score for pattern match."""
        base_confidence = 0.7

        # Pattern-specific adjustments
        if pattern_type == PatternType.TITLE:
            # Titles should be reasonable length
            if 5 <= len(line) <= 100:
                base_confidence += 0.1
            # All caps less likely to be real titles
            if line.isupper() and len(line) > 50:
                base_confidence -= 0.2

        elif pattern_type == PatternType.LIST_ITEM:
            # Well-formatted list items get higher confidence
            if match.get("groups") and len(match["groups"]) >= 2:
                base_confidence += 0.15

        elif pattern_type == PatternType.CITATION:
            # Academic citations with years get high confidence
            if re.search(r'\d{4}', match["matched_text"]):
                base_confidence += 0.2

        # Length-based adjustments
        if len(line) < 5:
            base_confidence -= 0.3
        elif len(line) > 200:
            base_confidence -= 0.1

        return max(0.0, min(1.0, base_confidence))

    def _analyze_title_candidate(self, line: str, line_num: int, all_lines: List[str]) -> Optional[Dict[str, Any]]:
        """Analyze line as potential title."""
        # Basic title patterns
        title_patterns = self.patterns[PatternType.TITLE.value]

        for pattern in title_patterns:
            match = pattern.match(line)
            if match:
                confidence = self._calculate_title_confidence(line, line_num, all_lines)
                if confidence > 0.5:
                    return {
                        "text": line,
                        "line_number": line_num,
                        "confidence": confidence,
                        "type": self._classify_title_type(line, match),
                        "estimated_level": self._estimate_title_level(line, match)
                    }

        return None

    def _calculate_title_confidence(self, line: str, line_num: int, all_lines: List[str]) -> float:
        """Calculate confidence that line is a title."""
        confidence = 0.5

        # Position-based factors
        if line_num == 0:  # First line
            confidence += 0.2
        elif line_num < len(all_lines) * 0.1:  # Early in document
            confidence += 0.1

        # Content-based factors
        if 10 <= len(line) <= 80:  # Good title length
            confidence += 0.2
        if not line.endswith('.'):  # Titles don't usually end with periods
            confidence += 0.1
        if line[0].isupper():  # Starts with capital
            confidence += 0.1

        # Context-based factors
        if line_num > 0 and not all_lines[line_num - 1].strip():  # Preceded by blank line
            confidence += 0.1
        if line_num < len(all_lines) - 1 and not all_lines[line_num + 1].strip():  # Followed by blank line
            confidence += 0.1

        return min(1.0, confidence)

    def _classify_title_type(self, line: str, match: re.Match) -> str:
        """Classify type of title."""
        if re.match(r'^\d+', line):
            return "numbered"
        elif line.isupper():
            return "all_caps"
        elif line.startswith('#'):
            return "markdown"
        elif '===' in line or '---' in line:
            return "decorated"
        else:
            return "standard"

    def _estimate_title_level(self, line: str, match: re.Match) -> int:
        """Estimate hierarchical level of title."""
        if line.startswith('#'):
            return min(6, line.count('#'))
        elif re.match(r'^\d+\.\d+\.\d+', line):
            return 3
        elif re.match(r'^\d+\.\d+', line):
            return 2
        elif re.match(r'^\d+', line):
            return 1
        else:
            return 1

    def _detect_list_item_type(self, line: str) -> Optional[str]:
        """Detect type of list item."""
        list_patterns = self.patterns[PatternType.LIST_ITEM.value]

        numbered_pattern = list_patterns[0]
        if numbered_pattern.match(line):
            return "numbered"

        lettered_pattern = list_patterns[1]
        if lettered_pattern.match(line):
            return "lettered"

        roman_pattern = list_patterns[2]
        if roman_pattern.match(line):
            return "roman"

        bullet_pattern = list_patterns[3]
        if bullet_pattern.match(line):
            return "bulleted"

        # Check other patterns
        for i, pattern in enumerate(list_patterns[4:], 4):
            if pattern.match(line):
                return ["parenthetical", "bracketed"][i-4]

        return None

    def _extract_list_marker(self, line: str, list_type: str) -> str:
        """Extract list marker from line."""
        if list_type == "numbered":
            match = re.match(r'^\s*(\d+)[.)]', line)
            return match.group(1) if match else ""
        elif list_type == "bulleted":
            match = re.match(r'^\s*([-*•▪▫◦‣⁃])', line)
            return match.group(1) if match else ""
        elif list_type == "lettered":
            match = re.match(r'^\s*([a-zA-Z])[.)]', line)
            return match.group(1) if match else ""
        else:
            return ""

    def _finalize_list(self, list_type: str, items: List[Dict[str, Any]], lists: Dict[str, Any]):
        """Finalize and add list to results."""
        if list_type in ["numbered", "lettered", "roman"]:
            list_key = list_type
        elif list_type == "bulleted":
            list_key = "bulleted"
        else:
            list_key = "mixed"

        lists[list_key].append({
            "type": list_type,
            "items": items,
            "item_count": len(items),
            "start_line": items[0]["line_number"],
            "end_line": items[-1]["line_number"]
        })

    def _classify_citation_format(self, citation: str) -> str:
        """Classify citation format style."""
        if citation.startswith('[') and citation.endswith(']'):
            if re.match(r'\[\d+\]', citation):
                return "numeric"
            else:
                return "author_year_bracket"
        elif citation.startswith('(') and citation.endswith(')'):
            return "author_year_paren"
        else:
            return "unknown"

    def _extract_domain(self, url: str) -> str:
        """Extract domain from URL."""
        try:
            from urllib.parse import urlparse
            return urlparse(url).netloc
        except:
            # Simple fallback
            if '://' in url:
                return url.split('://')[1].split('/')[0]
            return url.split('/')[0]

    def _rank_titles(self, titles: List[Dict[str, Any]]) -> List[Dict[str, Any]]:
        """Rank titles by confidence and context."""
        return sorted(titles, key=lambda t: t["confidence"], reverse=True)

    def _post_process_patterns(self, patterns: List[Dict[str, Any]], text: str) -> List[Dict[str, Any]]:
        """Post-process patterns to resolve conflicts and improve accuracy."""
        # Remove duplicate patterns on same line
        seen_lines = set()
        filtered_patterns = []

        for pattern in patterns:
            line_key = (pattern["line_number"], pattern["type"])
            if line_key not in seen_lines:
                filtered_patterns.append(pattern)
                seen_lines.add(line_key)

        # Sort by line number and confidence
        return sorted(filtered_patterns, key=lambda p: (p["line_number"], -p["confidence"]))


# Convenience functions for quick pattern detection
def detect_titles(text: str, language: str = "generic") -> List[Dict[str, Any]]:
    """Quick title detection function."""
    lang_profile = LanguageProfile(language) if language in [e.value for e in LanguageProfile] else LanguageProfile.GENERIC
    recognizer = TextPatternRecognizer(lang_profile)
    return recognizer.detect_titles(text)


def detect_lists(text: str, language: str = "generic") -> Dict[str, Any]:
    """Quick list detection function."""
    lang_profile = LanguageProfile(language) if language in [e.value for e in LanguageProfile] else LanguageProfile.GENERIC
    recognizer = TextPatternRecognizer(lang_profile)
    return recognizer.detect_lists(text)


def detect_citations(text: str, language: str = "generic") -> List[Dict[str, Any]]:
    """Quick citation detection function."""
    lang_profile = LanguageProfile(language) if language in [e.value for e in LanguageProfile] else LanguageProfile.GENERIC
    recognizer = TextPatternRecognizer(lang_profile)
    return recognizer.detect_citations(text)