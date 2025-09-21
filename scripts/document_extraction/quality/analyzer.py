#!/usr/bin/env python3
"""
Document Extraction Quality Analyzer

Analyzes extraction results and provides quality metrics and recommendations.
"""

import re
from typing import Dict, List, Any


class QualityAnalyzer:
    def __init__(self):
        self.quality_thresholds = {
            'excellent': 95.0,
            'good': 80.0,
            'acceptable': 60.0,
            'poor': 0.0
        }

    def analyze(self, extraction_result: Dict[str, Any]) -> Dict[str, Any]:
        """Analyze extraction quality and provide recommendations"""
        if extraction_result['status'] != 'success':
            return self._failed_extraction_analysis(extraction_result)

        metrics = extraction_result.get('metrics', {})
        metadata = extraction_result.get('metadata', {})
        extracted_text = extraction_result.get('extracted_text', '')

        # Calculate quality metrics
        quality_metrics = self._calculate_quality_metrics(metrics, metadata, extracted_text)

        # Identify problems
        problems = self._identify_problems(metrics, metadata, extracted_text)

        # Generate recommendations
        recommendations = self._generate_recommendations(problems, metadata, metrics)

        # Determine overall quality status
        extraction_percentage = metrics.get('extraction_percentage', 0)
        quality_status = self._get_quality_status(extraction_percentage)

        return {
            'quality_metrics': quality_metrics,
            'quality_status': quality_status,
            'problems_identified': problems,
            'recommendations': recommendations
        }

    def _calculate_quality_metrics(self, metrics: Dict, metadata: Dict, extracted_text: str) -> Dict[str, Any]:
        """Calculate detailed quality metrics"""
        text_length = len(extracted_text)
        extraction_percentage = metrics.get('extraction_percentage', 0)

        # Text quality indicators
        word_count = len(extracted_text.split()) if extracted_text else 0
        line_count = len(extracted_text.splitlines()) if extracted_text else 0
        char_variety = len(set(extracted_text.lower())) if extracted_text else 0

        # Content density score (higher is better)
        content_density = (word_count / text_length * 100) if text_length > 0 else 0

        # Structure preservation score
        structure_score = self._calculate_structure_score(extracted_text, metadata)

        return {
            'extraction_percentage': extraction_percentage,
            'text_length': text_length,
            'word_count': word_count,
            'line_count': line_count,
            'character_variety': char_variety,
            'content_density': round(content_density, 2),
            'structure_preservation_score': round(structure_score, 2),
            'average_words_per_line': round(word_count / line_count, 2) if line_count > 0 else 0
        }

    def _calculate_structure_score(self, extracted_text: str, metadata: Dict) -> float:
        """Calculate how well document structure was preserved"""
        if not extracted_text:
            return 0.0

        score = 0.0
        max_score = 100.0

        doc_type = metadata.get('document_type', '')

        # Check for structural elements based on document type
        if doc_type == 'docx':
            # Check for table indicators
            if '|' in extracted_text:
                score += 20
            # Check for header/footer indicators
            if 'HEADER:' in extracted_text or 'FOOTER:' in extracted_text:
                score += 20

        elif doc_type == 'xlsx':
            # Check for sheet indicators
            if 'SHEET:' in extracted_text:
                score += 30
            # Check for tabular structure
            if '|' in extracted_text:
                score += 20

        elif doc_type == 'pptx':
            # Check for slide indicators
            if 'SLIDE' in extracted_text:
                score += 30
            # Check for notes
            if 'NOTES:' in extracted_text:
                score += 20

        elif doc_type == 'html':
            # Structure is inherently preserved in HTML extraction
            score += 40

        elif doc_type == 'xml':
            # Structure markers should be present
            if ':' in extracted_text and '[' in extracted_text:
                score += 40

        # General structure indicators
        # Paragraph breaks
        if '\n\n' in extracted_text:
            score += 15

        # Reasonable line length variation
        lines = extracted_text.splitlines()
        if lines:
            line_lengths = [len(line) for line in lines if line.strip()]
            if line_lengths:
                avg_length = sum(line_lengths) / len(line_lengths)
                length_variance = sum((l - avg_length) ** 2 for l in line_lengths) / len(line_lengths)
                if length_variance > 100:  # Good variation in line lengths
                    score += 15

        return min(score, max_score)

    def _identify_problems(self, metrics: Dict, metadata: Dict, extracted_text: str) -> List[str]:
        """Identify specific problems with the extraction"""
        problems = []

        extraction_percentage = metrics.get('extraction_percentage', 0)
        failed_elements = metrics.get('failed_elements', 0)
        text_length = len(extracted_text)

        # Low extraction percentage
        if extraction_percentage < 50:
            problems.append("very_low_extraction_rate")
        elif extraction_percentage < 80:
            problems.append("low_extraction_rate")

        # High failure rate
        if failed_elements > 0:
            total_elements = metrics.get('total_elements', 0)
            failure_rate = (failed_elements / total_elements * 100) if total_elements > 0 else 0
            if failure_rate > 20:
                problems.append("high_element_failure_rate")

        # Empty or very short extraction
        if text_length == 0:
            problems.append("no_text_extracted")
        elif text_length < 100:
            problems.append("very_short_extraction")

        # Encoding issues
        if '�' in extracted_text:
            problems.append("encoding_issues")

        # Possible OCR needed
        doc_type = metadata.get('document_type', '')
        if doc_type == 'pdf' and text_length < 50:
            problems.append("may_need_ocr")

        # Structure loss
        if doc_type in ['docx', 'xlsx', 'pptx'] and '|' not in extracted_text:
            problems.append("structure_loss")

        # Repetitive content (possible extraction errors)
        if self._has_repetitive_content(extracted_text):
            problems.append("repetitive_content")

        return problems

    def _has_repetitive_content(self, text: str) -> bool:
        """Check for suspiciously repetitive content"""
        if len(text) < 200:
            return False

        lines = text.split('\n')
        if len(lines) < 10:
            return False

        # Check for repeated lines
        line_counts = {}
        for line in lines:
            line = line.strip()
            if len(line) > 10:  # Only check substantial lines
                line_counts[line] = line_counts.get(line, 0) + 1

        # If any line appears more than 5 times, it's suspicious
        max_repetitions = max(line_counts.values()) if line_counts else 0
        return max_repetitions > 5

    def _generate_recommendations(self, problems: List[str], metadata: Dict, metrics: Dict) -> List[str]:
        """Generate actionable recommendations based on identified problems"""
        recommendations = []

        problem_to_recommendation = {
            "very_low_extraction_rate": "Consider using OCR tools or alternative extraction methods",
            "low_extraction_rate": "Review document format and try different extraction parameters",
            "high_element_failure_rate": "Check for corrupted elements or unsupported features",
            "no_text_extracted": "Verify file is not corrupted and contains readable content",
            "very_short_extraction": "Document may be mostly images - consider OCR extraction",
            "encoding_issues": "Try alternative encoding detection or manual encoding specification",
            "may_need_ocr": "PDF appears to be image-based - use OCR tools like Tesseract",
            "structure_loss": "Consider using format-specific extraction libraries for better structure preservation",
            "repetitive_content": "Review extraction logic for potential parsing errors"
        }

        for problem in problems:
            if problem in problem_to_recommendation:
                recommendations.append(problem_to_recommendation[problem])

        # General recommendations based on document type
        doc_type = metadata.get('document_type', '')
        if doc_type == 'pdf':
            recommendations.append("For better PDF extraction, ensure document is text-based, not scanned")

        # Performance recommendations
        extraction_percentage = metrics.get('extraction_percentage', 0)
        if extraction_percentage > 90:
            recommendations.append("Extraction quality is excellent - no further action needed")

        return list(set(recommendations))  # Remove duplicates

    def _get_quality_status(self, extraction_percentage: float) -> str:
        """Determine overall quality status based on extraction percentage"""
        if extraction_percentage >= self.quality_thresholds['excellent']:
            return 'EXCELLENT'
        elif extraction_percentage >= self.quality_thresholds['good']:
            return 'GOOD'
        elif extraction_percentage >= self.quality_thresholds['acceptable']:
            return 'ACCEPTABLE'
        else:
            return 'POOR'

    def _failed_extraction_analysis(self, extraction_result: Dict[str, Any]) -> Dict[str, Any]:
        """Analysis for failed extractions"""
        error_msg = extraction_result.get('error', '')

        problems = ['extraction_failed']
        recommendations = []

        # Analyze error message for specific recommendations
        if 'not installed' in error_msg.lower():
            recommendations.append("Install missing Python libraries as indicated in error message")
        elif 'file not found' in error_msg.lower():
            recommendations.append("Verify file path exists and is accessible")
        elif 'permission' in error_msg.lower():
            recommendations.append("Check file permissions and access rights")
        elif 'corrupted' in error_msg.lower() or 'invalid' in error_msg.lower():
            recommendations.append("File may be corrupted - try with a different file")
        else:
            recommendations.append("Review error message and check file format compatibility")

        return {
            'quality_metrics': {
                'extraction_percentage': 0,
                'text_length': 0,
                'word_count': 0,
                'line_count': 0,
                'character_variety': 0,
                'content_density': 0,
                'structure_preservation_score': 0,
                'average_words_per_line': 0
            },
            'quality_status': 'FAILED',
            'problems_identified': problems,
            'recommendations': recommendations
        }