"""
Enhanced quality analyzer for extracted text content.
"""

import re
from typing import Dict, Any, Optional, List
from collections import Counter


def analyze_quality(text: str, file_type: str, total_pages: Optional[int] = None) -> Dict[str, Any]:
    """
    Analyze the quality of extracted text (backward compatible).

    Args:
        text: The extracted text to analyze
        file_type: The type of file (pdf, docx, etc.)
        total_pages: Total number of pages (if applicable)

    Returns:
        Dictionary containing quality metrics
    """
    analyzer = QualityAnalyzer()
    return analyzer.analyze_basic(text, file_type, total_pages)


class QualityAnalyzer:
    """Advanced quality analyzer with detailed content analysis."""

    def analyze_basic(self, text: str, file_type: str, total_pages: Optional[int] = None) -> Dict[str, Any]:
        """Basic analysis for backward compatibility."""
        if not text or not text.strip():
            return {
                "extraction_success_rate": 0,
                "total_pages": total_pages or 0,
                "pages_processed": 0,
                "quality_rating": "poor"
            }

        # Basic text statistics
        text = text.strip()
        char_count = len(text)
        word_count = len(text.split())

        # Quality indicators
        quality_score = 0

        # 1. Text length (30 points max)
        if char_count > 1000:
            quality_score += 30
        elif char_count > 500:
            quality_score += 20
        elif char_count > 100:
            quality_score += 10
        elif char_count > 0:
            quality_score += 5

        # 2. Word density (20 points max)
        if word_count > 200:
            quality_score += 20
        elif word_count > 100:
            quality_score += 15
        elif word_count > 50:
            quality_score += 10
        elif word_count > 10:
            quality_score += 5

        # 3. Structure indicators (25 points max)
        structure_score = 0

        # Check for common document structures
        if re.search(r'\n\s*\n', text):  # Paragraph breaks
            structure_score += 5
        if re.search(r'[.!?]\s+[A-Z]', text):  # Sentence structure
            structure_score += 5
        if re.search(r'^\s*\d+\.?\s+', text, re.MULTILINE):  # Numbered lists
            structure_score += 3
        if re.search(r'^\s*[•\-\*]\s+', text, re.MULTILINE):  # Bullet points
            structure_score += 3
        if re.search(r'===.*===', text):  # Section headers
            structure_score += 4
        if re.search(r'Title:|H\d+:', text):  # HTML headers
            structure_score += 5

        quality_score += min(structure_score, 25)

        # 4. Content quality (15 points max)
        content_score = 0

        # Check for readable content vs garbage
        alpha_ratio = len(re.findall(r'[a-zA-Z]', text)) / max(char_count, 1)
        if alpha_ratio > 0.7:
            content_score += 8
        elif alpha_ratio > 0.5:
            content_score += 5
        elif alpha_ratio > 0.3:
            content_score += 2

        # Check for meaningful words (not just random characters)
        common_words = ['the', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by']
        common_word_count = sum(1 for word in common_words if word.lower() in text.lower())
        if common_word_count >= 5:
            content_score += 7
        elif common_word_count >= 2:
            content_score += 3

        quality_score += min(content_score, 15)

        # 5. Error indicators (-10 points max)
        error_penalty = 0

        # Check for extraction artifacts
        if re.search(r'[\x00-\x08\x0B\x0C\x0E-\x1F\x7F-\x9F]', text):  # Control characters
            error_penalty += 5
        if len(re.findall(r'[^\w\s\.\,\!\?\-\(\)\[\]\{\}\'\"\\/:;]', text)) > char_count * 0.1:
            error_penalty += 3
        if text.count('?') > char_count * 0.05:  # Too many question marks (encoding issues)
            error_penalty += 2

        quality_score -= min(error_penalty, 10)

        # 6. Type-specific adjustments (10 points max)
        type_bonus = 0

        if file_type.lower() == 'pdf':
            if '|' in text:  # Table structure preserved
                type_bonus += 3
            if re.search(r'Page \d+', text):  # Page markers
                type_bonus += 2
        elif file_type.lower() in ['docx', 'doc']:
            if '|' in text:  # Table structure
                type_bonus += 3
            if re.search(r'===.*===', text):  # Section breaks
                type_bonus += 2
        elif file_type.lower() in ['html', 'htm']:
            if 'Title:' in text:  # Title extracted
                type_bonus += 3
            if re.search(r'H\d+:', text):  # Headers
                type_bonus += 3
            if '•' in text:  # Lists
                type_bonus += 2
        elif file_type.lower() == 'csv':
            if '|' in text:  # Column separation maintained
                type_bonus += 5

        quality_score += min(type_bonus, 10)

        # Normalize score to 0-100
        final_score = max(0, min(100, quality_score))

        # Determine quality rating
        if final_score >= 90:
            quality_rating = "excellent"
        elif final_score >= 70:
            quality_rating = "good"
        else:
            quality_rating = "poor"

        # Calculate pages processed
        pages_processed = total_pages or 1
        if total_pages and total_pages > 1:
            estimated_pages = max(1, min(total_pages, char_count // 2000))
            pages_processed = estimated_pages

        # Calculate extraction success rate
        if total_pages and total_pages > 0:
            extraction_success_rate = min(100, (pages_processed / total_pages) * 100 * (final_score / 100))
        else:
            extraction_success_rate = final_score

        return {
            "extraction_success_rate": round(extraction_success_rate, 1),
            "total_pages": total_pages or 1,
            "pages_processed": pages_processed,
            "quality_rating": quality_rating
        }

    def analyze_detailed(self, text: str, file_type: str, total_pages: Optional[int] = None) -> Dict[str, Any]:
        """Enhanced analysis with detailed content structure analysis."""
        if not text or not text.strip():
            return {
                "extraction_success_rate": 0,
                "total_pages": total_pages or 0,
                "pages_processed": 0,
                "quality_rating": "poor",
                "detailed_analysis": {
                    "structural_elements": {},
                    "content_metrics": {},
                    "encoding_issues": [],
                    "text_density": {}
                }
            }

        # Get basic analysis first
        basic_analysis = self.analyze_basic(text, file_type, total_pages)

        # Add detailed analysis
        detailed_analysis = {
            "structural_elements": self._analyze_structure(text),
            "content_metrics": self._analyze_content_metrics(text),
            "encoding_issues": self._detect_encoding_issues(text),
            "text_density": self._analyze_text_density(text)
        }

        # Merge results
        result = {**basic_analysis, "detailed_analysis": detailed_analysis}
        return result

    def _analyze_structure(self, text: str) -> Dict[str, Any]:
        """Analyze document structural elements."""
        structure = {
            "paragraphs": 0,
            "sentences": 0,
            "lists": {"numbered": 0, "bulleted": 0},
            "headers": 0,
            "tables": {"detected": False, "estimated_rows": 0, "estimated_columns": 0},
            "sections": 0
        }

        paragraphs = re.split(r'\n\s*\n', text)
        structure["paragraphs"] = len([p for p in paragraphs if p.strip()])

        sentences = re.findall(r'[.!?]+(?:\s+|$)', text)
        structure["sentences"] = len(sentences)

        numbered_lists = re.findall(r'^\s*\d+\.?\s+', text, re.MULTILINE)
        structure["lists"]["numbered"] = len(numbered_lists)

        bulleted_lists = re.findall(r'^\s*[•\-\*]\s+', text, re.MULTILINE)
        structure["lists"]["bulleted"] = len(bulleted_lists)

        headers = (
            len(re.findall(r'===.*===', text)) +
            len(re.findall(r'Title:|H\d+:', text))
        )
        structure["headers"] = headers

        lines = text.split('\n')
        table_lines = [line for line in lines if '|' in line and line.count('|') >= 2]
        if table_lines:
            structure["tables"]["detected"] = True
            structure["tables"]["estimated_rows"] = len(table_lines)
            column_counts = [line.count('|') + 1 for line in table_lines]
            structure["tables"]["estimated_columns"] = max(set(column_counts), key=column_counts.count)

        return structure

    def _analyze_content_metrics(self, text: str) -> Dict[str, Any]:
        """Analyze content quality metrics."""
        total_chars = len(text)
        words = text.split()

        metrics = {
            "character_distribution": {
                "alphabetic_ratio": sum(1 for c in text if c.isalpha()) / max(total_chars, 1),
                "numeric_ratio": sum(1 for c in text if c.isdigit()) / max(total_chars, 1),
                "whitespace_ratio": sum(1 for c in text if c.isspace()) / max(total_chars, 1)
            }
        }

        if words:
            word_lengths = [len(word.strip('.,!?;:()[]{}"\'-')) for word in words]
            metrics["word_length_stats"] = {
                "average_length": sum(word_lengths) / len(word_lengths),
                "max_length": max(word_lengths),
                "min_length": min(word_lengths)
            }

        return metrics

    def _detect_encoding_issues(self, text: str) -> List[Dict[str, Any]]:
        """Detect potential encoding or extraction issues."""
        issues = []

        replacement_chars = text.count('�')
        if replacement_chars > 0:
            issues.append({
                "type": "encoding_errors",
                "severity": "medium",
                "count": replacement_chars,
                "description": f"Found {replacement_chars} Unicode replacement characters"
            })

        question_ratio = text.count('?') / max(len(text), 1)
        if question_ratio > 0.05:
            issues.append({
                "type": "excessive_question_marks",
                "severity": "medium",
                "ratio": question_ratio,
                "description": f"Unusually high ratio of question marks ({question_ratio:.2%})"
            })

        return issues

    def _analyze_text_density(self, text: str) -> Dict[str, Any]:
        """Analyze text density and distribution."""
        lines = text.split('\n')
        total_lines = len(lines)

        if total_lines == 0:
            return {"error": "No lines to analyze"}

        line_lengths = [len(line) for line in lines]
        non_empty_lines = [len(line) for line in lines if line.strip()]

        density = {
            "total_lines": total_lines,
            "empty_lines": total_lines - len(non_empty_lines),
            "empty_line_ratio": (total_lines - len(non_empty_lines)) / max(total_lines, 1),
            "avg_line_length": sum(line_lengths) / max(total_lines, 1),
            "avg_non_empty_line_length": sum(non_empty_lines) / max(len(non_empty_lines), 1)
        }

        return density