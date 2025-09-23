"""
Error localization and context mapping for document extraction issues.
"""

import re
import math
from typing import Dict, Any, List, Optional, Tuple
from dataclasses import dataclass


@dataclass
class ErrorLocation:
    """Precise error location information."""
    character_position: int
    line_number: int
    column_number: int
    page_estimate: int
    section_name: Optional[str]
    context_before: str
    context_after: str
    confidence: float


@dataclass
class DocumentMapping:
    """Mapping between extracted text and original document."""
    total_pages: int
    chars_per_page: float
    line_breaks: List[int]
    section_boundaries: List[Dict[str, Any]]
    estimated_structure: Dict[str, Any]


def localize_error_position(text: str, error_pattern: str,
                           pattern_type: str = "regex") -> List[ErrorLocation]:
    """
    Localize exact position of errors in text.

    Args:
        text: Full extracted text
        error_pattern: Pattern to search for (regex or literal string)
        pattern_type: Type of pattern ("regex" or "literal")

    Returns:
        List of error locations found
    """
    locations = []

    try:
        if pattern_type == "regex":
            pattern = re.compile(error_pattern, re.IGNORECASE | re.MULTILINE)
            matches = list(pattern.finditer(text))
        else:
            # Literal string search
            matches = []
            start = 0
            while True:
                pos = text.find(error_pattern, start)
                if pos == -1:
                    break
                # Create a match-like object
                match_obj = type('Match', (), {
                    'start': lambda: pos,
                    'end': lambda: pos + len(error_pattern),
                    'group': lambda: error_pattern
                })()
                matches.append(match_obj)
                start = pos + 1

        for match in matches:
            char_pos = match.start()
            line_num, col_num = _get_line_column(text, char_pos)
            page_est = _estimate_page_number(char_pos, text)

            context_before, context_after = _extract_error_context(text, char_pos)
            section_name = _identify_section_at_position(text, char_pos)

            # Calculate confidence based on pattern specificity
            confidence = _calculate_localization_confidence(match.group(), pattern_type)

            location = ErrorLocation(
                character_position=char_pos,
                line_number=line_num,
                column_number=col_num,
                page_estimate=page_est,
                section_name=section_name,
                context_before=context_before,
                context_after=context_after,
                confidence=confidence
            )

            locations.append(location)

    except re.error as e:
        # Invalid regex pattern
        raise ValueError(f"Invalid regex pattern '{error_pattern}': {e}")

    return locations


def map_to_original_document(position: int, file_type: str,
                           document_info: Optional[Dict[str, Any]] = None) -> Dict[str, Any]:
    """
    Map text position to original document coordinates.

    Args:
        position: Character position in extracted text
        file_type: Type of original document
        document_info: Additional document metadata

    Returns:
        Original document coordinates
    """
    mapping = {}

    if file_type.lower() == 'pdf':
        mapping = _map_to_pdf_coordinates(position, document_info)
    elif file_type.lower() in ['docx', 'doc']:
        mapping = _map_to_word_coordinates(position, document_info)
    elif file_type.lower() in ['html', 'htm']:
        mapping = _map_to_html_coordinates(position, document_info)
    elif file_type.lower() == 'txt':
        mapping = _map_to_text_coordinates(position, document_info)
    else:
        # Generic mapping
        mapping = _map_to_generic_coordinates(position, document_info)

    return mapping


def generate_error_context(text: str, error_position: int,
                         context_size: int = 100) -> Dict[str, Any]:
    """
    Generate rich context around an error position.

    Args:
        text: Full text content
        error_position: Position of the error
        context_size: Size of context window (characters)

    Returns:
        Rich context information
    """
    # Extract basic context
    start_pos = max(0, error_position - context_size)
    end_pos = min(len(text), error_position + context_size)

    context_text = text[start_pos:end_pos]

    # Find sentence boundaries
    sentences = _extract_sentence_context(text, error_position)

    # Find paragraph boundaries
    paragraph_info = _extract_paragraph_context(text, error_position)

    # Analyze surrounding structure
    structure_info = _analyze_surrounding_structure(text, error_position)

    return {
        "position": error_position,
        "context_window": {
            "start": start_pos,
            "end": end_pos,
            "text": context_text,
            "size": len(context_text)
        },
        "sentence_context": sentences,
        "paragraph_context": paragraph_info,
        "structure_context": structure_info,
        "visual_markers": _generate_visual_markers(context_text, error_position - start_pos),
        "readability_impact": _assess_readability_impact(context_text, error_position - start_pos)
    }


def batch_localize_errors(text: str, error_patterns: List[Dict[str, Any]]) -> Dict[str, Any]:
    """
    Localize multiple error patterns in batch for efficiency.

    Args:
        text: Text to analyze
        error_patterns: List of error patterns to search for

    Returns:
        Batch localization results
    """
    all_locations = {}
    processing_stats = {
        "total_patterns": len(error_patterns),
        "successful_patterns": 0,
        "total_matches": 0,
        "processing_time": 0
    }

    for i, pattern_info in enumerate(error_patterns):
        pattern = pattern_info.get("pattern", "")
        pattern_type = pattern_info.get("type", "regex")
        pattern_id = pattern_info.get("id", f"pattern_{i}")

        try:
            locations = localize_error_position(text, pattern, pattern_type)
            all_locations[pattern_id] = {
                "pattern": pattern,
                "type": pattern_type,
                "locations": locations,
                "match_count": len(locations)
            }
            processing_stats["successful_patterns"] += 1
            processing_stats["total_matches"] += len(locations)

        except Exception as e:
            all_locations[pattern_id] = {
                "pattern": pattern,
                "type": pattern_type,
                "error": str(e),
                "locations": [],
                "match_count": 0
            }

    return {
        "results": all_locations,
        "statistics": processing_stats,
        "summary": _generate_localization_summary(all_locations)
    }


def create_document_mapping(text: str, file_info: Dict[str, Any],
                          structure_info: Optional[Dict[str, Any]] = None) -> DocumentMapping:
    """
    Create mapping between extracted text and original document.

    Args:
        text: Extracted text
        file_info: Original file information
        structure_info: Document structure information

    Returns:
        Document mapping object
    """
    # Calculate basic metrics
    total_chars = len(text)
    line_breaks = _find_line_breaks(text)

    # Estimate pages
    file_size = file_info.get("file_size", 0)
    if file_size > 0:
        # Rough estimation based on file size and extracted text
        estimated_pages = max(1, file_size // 2000)  # ~2KB per page
    else:
        # Fallback estimation
        estimated_pages = max(1, total_chars // 2000)

    chars_per_page = total_chars / estimated_pages if estimated_pages > 0 else total_chars

    # Extract section boundaries from structure if available
    section_boundaries = []
    if structure_info and "sections_detected" in structure_info:
        sections = structure_info["sections_detected"]
        if isinstance(sections, list):
            for section in sections:
                section_boundaries.append({
                    "title": section.get("title", ""),
                    "start": section.get("start_position", 0),
                    "end": section.get("end_position", 0),
                    "level": section.get("level", 1)
                })

    # Create estimated structure
    estimated_structure = _create_estimated_structure(text, line_breaks, section_boundaries)

    return DocumentMapping(
        total_pages=estimated_pages,
        chars_per_page=chars_per_page,
        line_breaks=line_breaks,
        section_boundaries=section_boundaries,
        estimated_structure=estimated_structure
    )


def _get_line_column(text: str, position: int) -> Tuple[int, int]:
    """Get line and column number for a character position."""
    if position >= len(text):
        position = len(text) - 1
    if position < 0:
        position = 0

    # Count newlines up to position
    text_up_to_pos = text[:position]
    line_number = text_up_to_pos.count('\n') + 1

    # Find column number (position since last newline)
    last_newline = text_up_to_pos.rfind('\n')
    column_number = position - last_newline if last_newline != -1 else position + 1

    return line_number, column_number


def _estimate_page_number(position: int, text: str) -> int:
    """Estimate page number based on character position."""
    # Simple estimation: ~2000 characters per page
    chars_per_page = 2000
    return max(1, (position // chars_per_page) + 1)


def _extract_error_context(text: str, position: int, window: int = 50) -> Tuple[str, str]:
    """Extract context before and after error position."""
    start = max(0, position - window)
    end = min(len(text), position + window)

    context_before = text[start:position] if position > start else ""
    context_after = text[position:end] if position < end else ""

    return context_before, context_after


def _identify_section_at_position(text: str, position: int) -> Optional[str]:
    """Identify which section contains the given position."""
    # Look backward for section headers
    text_before = text[:position]

    # Common section header patterns
    header_patterns = [
        r'\n\s*([A-Z][A-Z\s]{2,50})\s*\n',  # ALL CAPS headers
        r'\n\s*(\d+\.?\s+[A-Z][^.\n]{3,50})\s*\n',  # Numbered headers
        r'\n\s*(#{1,6}\s+[^\n]+)\s*\n',  # Markdown headers
    ]

    last_header = None
    last_position = -1

    for pattern in header_patterns:
        for match in re.finditer(pattern, text_before):
            if match.end() > last_position:
                last_position = match.end()
                last_header = match.group(1).strip()

    return last_header


def _calculate_localization_confidence(matched_text: str, pattern_type: str) -> float:
    """Calculate confidence score for error localization."""
    base_confidence = 0.8 if pattern_type == "literal" else 0.7

    # Adjust based on match characteristics
    if len(matched_text) > 10:
        base_confidence += 0.1  # Longer matches are more reliable

    if re.search(r'^[a-zA-Z0-9\s]+$', matched_text):
        base_confidence += 0.05  # Standard characters are more reliable

    return min(0.95, base_confidence)


def _map_to_pdf_coordinates(position: int, doc_info: Optional[Dict[str, Any]]) -> Dict[str, Any]:
    """Map position to PDF page and approximate location."""
    # Rough estimation for PDF
    chars_per_page = 2000  # Typical characters per page
    page_number = max(1, (position // chars_per_page) + 1)
    position_on_page = position % chars_per_page

    # Estimate position on page (top/middle/bottom)
    if position_on_page < chars_per_page * 0.33:
        page_region = "top"
    elif position_on_page < chars_per_page * 0.67:
        page_region = "middle"
    else:
        page_region = "bottom"

    return {
        "document_type": "pdf",
        "page_number": page_number,
        "position_on_page": position_on_page,
        "page_region": page_region,
        "confidence": 0.6
    }


def _map_to_word_coordinates(position: int, doc_info: Optional[Dict[str, Any]]) -> Dict[str, Any]:
    """Map position to Word document paragraph/section."""
    # Estimate paragraph based on line breaks
    paragraph_estimate = position // 500  # Rough paragraph size

    return {
        "document_type": "word",
        "paragraph_estimate": paragraph_estimate + 1,
        "character_in_paragraph": position % 500,
        "confidence": 0.5
    }


def _map_to_html_coordinates(position: int, doc_info: Optional[Dict[str, Any]]) -> Dict[str, Any]:
    """Map position to HTML element location."""
    return {
        "document_type": "html",
        "character_position": position,
        "estimated_element": "body",
        "confidence": 0.4
    }


def _map_to_text_coordinates(position: int, doc_info: Optional[Dict[str, Any]]) -> Dict[str, Any]:
    """Map position to plain text coordinates."""
    line_estimate = position // 80  # Assuming ~80 chars per line
    char_in_line = position % 80

    return {
        "document_type": "text",
        "line_estimate": line_estimate + 1,
        "character_in_line": char_in_line + 1,
        "confidence": 0.8
    }


def _map_to_generic_coordinates(position: int, doc_info: Optional[Dict[str, Any]]) -> Dict[str, Any]:
    """Generic coordinate mapping."""
    return {
        "document_type": "unknown",
        "character_position": position,
        "confidence": 0.3
    }


def _extract_sentence_context(text: str, position: int) -> Dict[str, Any]:
    """Extract sentence-level context around position."""
    # Find sentence boundaries
    sentence_endings = [m.end() for m in re.finditer(r'[.!?]\s+', text)]

    # Find current sentence
    current_sentence_start = 0
    current_sentence_end = len(text)

    for end_pos in sentence_endings:
        if end_pos <= position:
            current_sentence_start = end_pos
        elif current_sentence_end == len(text):
            current_sentence_end = end_pos
            break

    sentence_text = text[current_sentence_start:current_sentence_end].strip()
    position_in_sentence = position - current_sentence_start

    return {
        "sentence": sentence_text,
        "position_in_sentence": position_in_sentence,
        "sentence_length": len(sentence_text),
        "sentence_start": current_sentence_start,
        "sentence_end": current_sentence_end
    }


def _extract_paragraph_context(text: str, position: int) -> Dict[str, Any]:
    """Extract paragraph-level context around position."""
    # Find paragraph boundaries (double newlines)
    paragraphs = re.split(r'\n\s*\n', text)

    char_count = 0
    current_paragraph = ""
    paragraph_index = 0
    position_in_paragraph = 0

    for i, paragraph in enumerate(paragraphs):
        if char_count + len(paragraph) >= position:
            current_paragraph = paragraph
            paragraph_index = i
            position_in_paragraph = position - char_count
            break
        char_count += len(paragraph) + 2  # Account for paragraph breaks

    return {
        "paragraph": current_paragraph.strip(),
        "paragraph_index": paragraph_index,
        "position_in_paragraph": position_in_paragraph,
        "paragraph_length": len(current_paragraph)
    }


def _analyze_surrounding_structure(text: str, position: int) -> Dict[str, Any]:
    """Analyze structural elements around position."""
    window_size = 200
    start = max(0, position - window_size)
    end = min(len(text), position + window_size)
    context = text[start:end]

    # Look for structural elements
    structure_elements = {
        "headers": len(re.findall(r'\n\s*([A-Z][A-Z\s]{2,50})\s*\n', context)),
        "lists": len(re.findall(r'\n\s*[-*•]\s+', context)),
        "numbers": len(re.findall(r'\n\s*\d+\.\s+', context)),
        "tables": len(re.findall(r'\|[^|\n]*\|', context)),
        "quotes": len(re.findall(r'["""]', context))
    }

    return {
        "elements_found": structure_elements,
        "dominant_structure": max(structure_elements.items(), key=lambda x: x[1])[0] if any(structure_elements.values()) else "plain_text",
        "structure_density": sum(structure_elements.values()) / len(context) if context else 0
    }


def _generate_visual_markers(context: str, error_pos: int) -> Dict[str, Any]:
    """Generate visual markers for error position in context."""
    if error_pos < 0 or error_pos >= len(context):
        return {"marked_text": context, "marker_position": -1}

    # Insert visual marker
    marked_text = context[:error_pos] + "<<<ERROR>>>" + context[error_pos:]

    # Generate pointer line
    pointer_line = " " * error_pos + "^"

    return {
        "marked_text": marked_text,
        "pointer_line": pointer_line,
        "marker_position": error_pos,
        "context_length": len(context)
    }


def _assess_readability_impact(context: str, error_pos: int) -> Dict[str, Any]:
    """Assess how the error impacts readability."""
    if not context:
        return {"impact": "none", "score": 0}

    # Check if error is in a critical position
    critical_positions = {
        "sentence_start": error_pos < 20,
        "word_boundary": context[max(0, error_pos-1):error_pos+2].isspace() if error_pos > 0 and error_pos < len(context) else False,
        "punctuation_area": any(p in context[max(0, error_pos-2):error_pos+3] for p in '.!?,;:')
    }

    impact_score = sum(critical_positions.values()) / len(critical_positions)

    if impact_score > 0.6:
        impact_level = "high"
    elif impact_score > 0.3:
        impact_level = "medium"
    else:
        impact_level = "low"

    return {
        "impact": impact_level,
        "score": impact_score,
        "factors": critical_positions
    }


def _find_line_breaks(text: str) -> List[int]:
    """Find all line break positions in text."""
    return [m.start() for m in re.finditer(r'\n', text)]


def _create_estimated_structure(text: str, line_breaks: List[int],
                              section_boundaries: List[Dict[str, Any]]) -> Dict[str, Any]:
    """Create estimated document structure."""
    return {
        "total_lines": len(line_breaks) + 1,
        "total_sections": len(section_boundaries),
        "avg_section_length": len(text) // max(1, len(section_boundaries)),
        "has_clear_structure": len(section_boundaries) > 0,
        "structure_indicators": {
            "numbered_sections": sum(1 for s in section_boundaries if re.search(r'^\d+', s.get("title", ""))),
            "hierarchical_levels": len(set(s.get("level", 1) for s in section_boundaries))
        }
    }


def _generate_localization_summary(results: Dict[str, Any]) -> Dict[str, Any]:
    """Generate summary of localization results."""
    total_matches = sum(r["match_count"] for r in results.values() if "match_count" in r)
    patterns_with_matches = sum(1 for r in results.values() if r.get("match_count", 0) > 0)

    return {
        "total_patterns_processed": len(results),
        "patterns_with_matches": patterns_with_matches,
        "total_error_locations": total_matches,
        "average_matches_per_pattern": total_matches / len(results) if results else 0,
        "most_common_patterns": sorted(
            [(k, v["match_count"]) for k, v in results.items() if "match_count" in v],
            key=lambda x: x[1], reverse=True
        )[:5]
    }