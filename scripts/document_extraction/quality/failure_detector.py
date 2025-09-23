"""
Document extraction failure detection and analysis.
"""

import re
import math
from typing import Dict, Any, List, Optional, Tuple
from collections import Counter
from enum import Enum


class FailureType(Enum):
    """Types of extraction failures."""
    ENCODING_ERROR = "encoding_error"
    OCR_FAILURE = "ocr_failure"
    STRUCTURE_LOSS = "structure_loss"
    CONTENT_GAPS = "content_gaps"
    TABLE_CORRUPTION = "table_corruption"
    FORMATTING_LOSS = "formatting_loss"
    LANGUAGE_INCONSISTENCY = "language_inconsistency"
    INCOMPLETE_EXTRACTION = "incomplete_extraction"


class SeverityLevel(Enum):
    """Severity levels for failures."""
    CRITICAL = "critical"
    HIGH = "high"
    MEDIUM = "medium"
    LOW = "low"


class FailureDetector:
    """Detect and analyze document extraction failures."""

    def __init__(self):
        self.encoding_patterns = self._compile_encoding_patterns()
        self.corruption_patterns = self._compile_corruption_patterns()
        self.quality_thresholds = self._load_quality_thresholds()

    def detect_extraction_failures(self, text: str, original_file_info: Dict[str, Any],
                                 extracted_structure: Optional[Dict[str, Any]] = None) -> List[Dict[str, Any]]:
        """
        Main failure detection function.

        Args:
            text: Extracted text content
            original_file_info: Original file metadata
            extracted_structure: Structure analysis results

        Returns:
            List of detected failures with detailed information
        """
        failures = []

        # Detect encoding issues
        encoding_failures = self.detect_encoding_issues(text)
        failures.extend(encoding_failures)

        # Detect OCR-related failures
        ocr_failures = self.detect_ocr_failures(text, original_file_info.get('file_type', 'unknown'))
        failures.extend(ocr_failures)

        # Detect content gaps
        content_gaps = self.detect_content_gaps(text, extracted_structure)
        failures.extend(content_gaps)

        # Detect structure loss
        if extracted_structure:
            structure_failures = self.detect_structure_loss(original_file_info, extracted_structure)
            failures.extend(structure_failures)

        # Detect table corruption
        table_failures = self.detect_table_corruption(text)
        failures.extend(table_failures)

        # Detect incomplete extraction
        incomplete_failures = self.detect_incomplete_extraction(text, original_file_info)
        failures.extend(incomplete_failures)

        # Add unique IDs and sort by severity
        for i, failure in enumerate(failures):
            failure['failure_id'] = f"{failure['type'].upper()}_{i+1:03d}"

        return sorted(failures, key=lambda x: self._severity_weight(x['severity']))

    def detect_encoding_issues(self, text: str) -> List[Dict[str, Any]]:
        """Detect encoding-related problems in text."""
        issues = []

        # Check for common encoding corruption patterns
        for pattern_name, pattern in self.encoding_patterns.items():
            matches = list(pattern.finditer(text))
            for match in matches:
                position = match.start()
                context = self._extract_context(text, position)

                issues.append({
                    'type': FailureType.ENCODING_ERROR.value,
                    'severity': SeverityLevel.HIGH.value,
                    'pattern': pattern_name,
                    'position': position,
                    'affected_content': match.group(),
                    'context': context,
                    'description': f"Encoding issue detected: {pattern_name}",
                    'confidence': self._calculate_encoding_confidence(match.group(), pattern_name)
                })

        # Check for suspicious character sequences
        suspicious_sequences = self._find_suspicious_sequences(text)
        for seq in suspicious_sequences:
            issues.append({
                'type': FailureType.ENCODING_ERROR.value,
                'severity': SeverityLevel.MEDIUM.value,
                'pattern': 'suspicious_sequence',
                'position': seq['position'],
                'affected_content': seq['content'],
                'context': seq['context'],
                'description': "Suspicious character sequence that may indicate encoding issues",
                'confidence': seq['confidence']
            })

        return issues

    def detect_ocr_failures(self, text: str, file_type: str) -> List[Dict[str, Any]]:
        """Detect OCR-related extraction failures."""
        issues = []

        # Only check for OCR issues in PDF files (likely scanned)
        if file_type.lower() != 'pdf':
            return issues

        # Check for OCR corruption patterns
        ocr_indicators = [
            (r'[Il1|]{3,}', 'vertical_line_confusion'),
            (r'[O0]{4,}', 'zero_letter_confusion'),
            (r'rn[^a-z]', 'rn_m_confusion'),
            (r'[^a-z]cl[^a-z]', 'cl_d_confusion'),
            (r'\b[a-z]{1,2}[0-9]+[a-z]*\b', 'letter_number_mix'),
            (r'[^\s]{20,}', 'no_spaces_long_string')
        ]

        for pattern, issue_type in ocr_indicators:
            matches = list(re.finditer(pattern, text, re.IGNORECASE))
            for match in matches:
                position = match.start()
                context = self._extract_context(text, position)

                issues.append({
                    'type': FailureType.OCR_FAILURE.value,
                    'severity': SeverityLevel.MEDIUM.value,
                    'pattern': issue_type,
                    'position': position,
                    'affected_content': match.group(),
                    'context': context,
                    'description': f"OCR issue detected: {issue_type}",
                    'confidence': self._calculate_ocr_confidence(match.group(), issue_type)
                })

        return issues

    def detect_structure_loss(self, original_file_info: Dict[str, Any],
                            extracted_structure: Dict[str, Any]) -> List[Dict[str, Any]]:
        """Detect loss of document structure during extraction."""
        issues = []

        # Check if structure analysis found minimal structure
        if extracted_structure:
            total_sections = extracted_structure.get('document_hierarchy', {}).get('total_sections', 0)
            headers_found = len(extracted_structure.get('headers_detected', []))

            # Expected structure based on file type and size
            expected_structure = self._estimate_expected_structure(original_file_info)

            if total_sections < expected_structure['min_sections']:
                issues.append({
                    'type': FailureType.STRUCTURE_LOSS.value,
                    'severity': SeverityLevel.HIGH.value,
                    'pattern': 'insufficient_sections',
                    'position': 0,
                    'affected_content': f"Only {total_sections} sections found",
                    'context': f"Expected at least {expected_structure['min_sections']} sections",
                    'description': "Document structure not properly extracted",
                    'confidence': 0.8,
                    'metrics': {
                        'sections_found': total_sections,
                        'headers_found': headers_found,
                        'expected_minimum': expected_structure['min_sections']
                    }
                })

        return issues

    def detect_content_gaps(self, text: str, extracted_structure: Optional[Dict[str, Any]] = None) -> List[Dict[str, Any]]:
        """Detect gaps or missing content in extracted text."""
        issues = []

        # Check for unusually short sections
        if extracted_structure and 'sections_detected' in extracted_structure:
            sections = extracted_structure['sections_detected']
            if isinstance(sections, list):
                for section in sections:
                    word_count = section.get('word_count', 0)
                    if word_count < 10 and section.get('level', 1) <= 2:  # Main sections should have content
                        issues.append({
                            'type': FailureType.CONTENT_GAPS.value,
                            'severity': SeverityLevel.MEDIUM.value,
                            'pattern': 'short_section',
                            'position': section.get('start_position', 0),
                            'affected_content': section.get('title', 'Unknown section'),
                            'context': f"Section has only {word_count} words",
                            'description': f"Section '{section.get('title')}' appears incomplete",
                            'confidence': 0.7,
                            'metrics': {
                                'word_count': word_count,
                                'section_level': section.get('level', 1)
                            }
                        })

        # Check for large gaps in text (multiple consecutive newlines)
        gap_pattern = re.compile(r'\n\s*\n\s*\n\s*\n+')
        for match in gap_pattern.finditer(text):
            gap_length = len(match.group())
            if gap_length > 20:  # Very large gap
                position = match.start()
                context = self._extract_context(text, position, window=100)

                issues.append({
                    'type': FailureType.CONTENT_GAPS.value,
                    'severity': SeverityLevel.LOW.value,
                    'pattern': 'large_whitespace_gap',
                    'position': position,
                    'affected_content': f"Gap of {gap_length} characters",
                    'context': context,
                    'description': "Large whitespace gap may indicate missing content",
                    'confidence': 0.5
                })

        return issues

    def detect_table_corruption(self, text: str) -> List[Dict[str, Any]]:
        """Detect corrupted or poorly extracted tables."""
        issues = []

        # Look for table-like patterns that may be corrupted
        potential_tables = re.finditer(r'(\|[^|\n]*\|[^|\n]*\||\t[^\t\n]*\t[^\t\n]*\t)', text)

        for match in potential_tables:
            position = match.start()
            table_text = match.group()
            context = self._extract_context(text, position)

            # Check for corruption indicators
            corruption_indicators = [
                len(re.findall(r'\|', table_text)) < 3,  # Too few separators
                len(table_text) > 200 and '\n' not in table_text,  # Very long line without breaks
                re.search(r'[|]{3,}', table_text),  # Multiple consecutive separators
            ]

            if any(corruption_indicators):
                issues.append({
                    'type': FailureType.TABLE_CORRUPTION.value,
                    'severity': SeverityLevel.MEDIUM.value,
                    'pattern': 'corrupted_table',
                    'position': position,
                    'affected_content': table_text[:100] + ('...' if len(table_text) > 100 else ''),
                    'context': context,
                    'description': "Table structure appears corrupted or poorly extracted",
                    'confidence': 0.6
                })

        return issues

    def detect_incomplete_extraction(self, text: str, original_file_info: Dict[str, Any]) -> List[Dict[str, Any]]:
        """Detect if extraction is incomplete compared to original file."""
        issues = []

        # Check if text is suspiciously short for file size
        file_size = original_file_info.get('file_size', 0)
        text_length = len(text.strip())

        if file_size > 0:
            # Rough estimate: expect at least 1 character per 100 bytes for most document types
            expected_min_chars = max(100, file_size // 100)

            if text_length < expected_min_chars * 0.1:  # Less than 10% of expected
                issues.append({
                    'type': FailureType.INCOMPLETE_EXTRACTION.value,
                    'severity': SeverityLevel.CRITICAL.value,
                    'pattern': 'severely_incomplete',
                    'position': 0,
                    'affected_content': f"Only {text_length} characters extracted",
                    'context': f"File size: {file_size} bytes",
                    'description': "Extraction appears severely incomplete",
                    'confidence': 0.9,
                    'metrics': {
                        'extracted_chars': text_length,
                        'file_size_bytes': file_size,
                        'extraction_ratio': text_length / file_size if file_size > 0 else 0
                    }
                })
            elif text_length < expected_min_chars * 0.5:  # Less than 50% of expected
                issues.append({
                    'type': FailureType.INCOMPLETE_EXTRACTION.value,
                    'severity': SeverityLevel.HIGH.value,
                    'pattern': 'potentially_incomplete',
                    'position': 0,
                    'affected_content': f"{text_length} characters extracted",
                    'context': f"File size: {file_size} bytes",
                    'description': "Extraction may be incomplete",
                    'confidence': 0.7,
                    'metrics': {
                        'extracted_chars': text_length,
                        'file_size_bytes': file_size,
                        'extraction_ratio': text_length / file_size if file_size > 0 else 0
                    }
                })

        return issues

    def _compile_encoding_patterns(self) -> Dict[str, re.Pattern]:
        """Compile regex patterns for encoding issue detection."""
        return {
            'utf8_corruption': re.compile(r'�+'),
            'latin1_in_utf8': re.compile(r'[àáâãäåæçèéêëìíîïñòóôõöøùúûüý]{2,}'),
            'windows1252_quotes': re.compile(r'[""]'),
            'escaped_unicode': re.compile(r'\\u[0-9a-fA-F]{4}'),
            'html_entities': re.compile(r'&[a-zA-Z][a-zA-Z0-9]*;|&#[0-9]+;|&#x[0-9a-fA-F]+;'),
            'mojibake': re.compile(r'[^\x00-\x7F\u00C0-\u024F\u1E00-\u1EFF\u0100-\u017F\u0180-\u024F]+')
        }

    def _compile_corruption_patterns(self) -> Dict[str, re.Pattern]:
        """Compile patterns for content corruption detection."""
        return {
            'repeated_chars': re.compile(r'(.)\1{5,}'),
            'garbled_text': re.compile(r'[^\w\s\.,!?;:()\[\]{}"\'-]{3,}'),
            'mixed_scripts': re.compile(r'[a-zA-Z][^a-zA-Z\s\.,!?;:()\[\]{}"\'-]*[а-яё][^а-яё\s\.,!?;:()\[\]{}"\'-]*[a-zA-Z]'),
        }

    def _load_quality_thresholds(self) -> Dict[str, Any]:
        """Load quality assessment thresholds."""
        return {
            'min_word_length': 2,
            'max_word_length': 50,
            'min_sentence_length': 10,
            'max_sentence_length': 500,
            'min_paragraph_words': 5,
            'suspicious_char_ratio': 0.1,
            'min_extraction_ratio': 0.01
        }

    def _extract_context(self, text: str, position: int, window: int = 50) -> str:
        """Extract context around a position in text."""
        start = max(0, position - window)
        end = min(len(text), position + window)
        return text[start:end].strip()

    def _find_suspicious_sequences(self, text: str) -> List[Dict[str, Any]]:
        """Find suspicious character sequences that may indicate encoding issues."""
        sequences = []

        # Look for unusual character patterns
        patterns = [
            (r'[^\x20-\x7E\u00A0-\u024F\u1E00-\u1EFF]{3,}', 'non_standard_chars'),
            (r'[A-Za-z]{1}[0-9]{2,}[A-Za-z]{1}', 'mixed_alphanumeric'),
            (r'[\.\,\;\:]{3,}', 'repeated_punctuation')
        ]

        for pattern, seq_type in patterns:
            for match in re.finditer(pattern, text):
                position = match.start()
                content = match.group()
                context = self._extract_context(text, position)

                sequences.append({
                    'position': position,
                    'content': content,
                    'context': context,
                    'type': seq_type,
                    'confidence': self._calculate_sequence_confidence(content, seq_type)
                })

        return sequences

    def _calculate_encoding_confidence(self, content: str, pattern_name: str) -> float:
        """Calculate confidence score for encoding issue detection."""
        base_confidence = {
            'utf8_corruption': 0.95,
            'latin1_in_utf8': 0.7,
            'windows1252_quotes': 0.6,
            'escaped_unicode': 0.8,
            'html_entities': 0.85,
            'mojibake': 0.75
        }.get(pattern_name, 0.5)

        # Adjust based on content length and frequency
        length_factor = min(1.0, len(content) / 10)
        return min(0.95, base_confidence * (0.5 + 0.5 * length_factor))

    def _calculate_ocr_confidence(self, content: str, issue_type: str) -> float:
        """Calculate confidence score for OCR issue detection."""
        base_confidence = {
            'vertical_line_confusion': 0.8,
            'zero_letter_confusion': 0.7,
            'rn_m_confusion': 0.75,
            'cl_d_confusion': 0.7,
            'letter_number_mix': 0.6,
            'no_spaces_long_string': 0.8
        }.get(issue_type, 0.5)

        return base_confidence

    def _calculate_sequence_confidence(self, content: str, seq_type: str) -> float:
        """Calculate confidence for suspicious sequence detection."""
        base_confidence = {
            'non_standard_chars': 0.6,
            'mixed_alphanumeric': 0.5,
            'repeated_punctuation': 0.7
        }.get(seq_type, 0.4)

        return base_confidence

    def _estimate_expected_structure(self, file_info: Dict[str, Any]) -> Dict[str, int]:
        """Estimate expected document structure based on file characteristics."""
        file_size = file_info.get('file_size', 0)
        file_type = file_info.get('file_type', 'unknown').lower()

        # Base estimates by file size (very rough heuristics)
        if file_size < 10000:  # < 10KB
            min_sections = 1
        elif file_size < 50000:  # < 50KB
            min_sections = 2
        elif file_size < 200000:  # < 200KB
            min_sections = 3
        else:  # >= 200KB
            min_sections = 5

        # Adjust by file type
        if file_type == 'pdf':
            min_sections = max(2, min_sections)
        elif file_type in ['docx', 'doc']:
            min_sections = max(1, min_sections)

        return {
            'min_sections': min_sections,
            'min_headers': min_sections
        }

    def _severity_weight(self, severity: str) -> int:
        """Return numeric weight for severity ordering."""
        weights = {
            'critical': 0,
            'high': 1,
            'medium': 2,
            'low': 3
        }
        return weights.get(severity.lower(), 4)