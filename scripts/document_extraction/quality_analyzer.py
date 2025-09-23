"""
Quality analyzer for extracted text content.
"""

import re
from typing import Dict, Any, Optional


def analyze_quality(text: str, file_type: str, total_pages: Optional[int] = None) -> Dict[str, Any]:
    """
    Analyze the quality of extracted text.

    Args:
        text: The extracted text to analyze
        file_type: The type of file (pdf, docx, etc.)
        total_pages: Total number of pages (if applicable)

    Returns:
        Dictionary containing quality metrics
    """

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
    line_count = len([line for line in text.split('\n') if line.strip()])

    # Quality indicators
    quality_score = 0
    max_score = 100

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
    if len(re.findall(r'[^\w\s\.\,\!\?\-\(\)\[\]\{\}\'\"\\/:;]', text)) > char_count * 0.1:  # Too many special chars
        error_penalty += 3
    if text.count('?') > char_count * 0.05:  # Too many question marks (encoding issues)
        error_penalty += 2

    quality_score -= min(error_penalty, 10)

    # 6. Type-specific adjustments (10 points max)
    type_bonus = 0

    if file_type.lower() == 'pdf':
        # PDF-specific quality checks
        if '|' in text:  # Table structure preserved
            type_bonus += 3
        if re.search(r'Page \d+', text):  # Page markers
            type_bonus += 2
    elif file_type.lower() in ['docx', 'doc']:
        # Word document specific
        if '|' in text:  # Table structure
            type_bonus += 3
        if re.search(r'===.*===', text):  # Section breaks
            type_bonus += 2
    elif file_type.lower() in ['html', 'htm']:
        # HTML specific
        if 'Title:' in text:  # Title extracted
            type_bonus += 3
        if re.search(r'H\d+:', text):  # Headers
            type_bonus += 3
        if '•' in text:  # Lists
            type_bonus += 2
    elif file_type.lower() == 'csv':
        # CSV specific
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

    # Calculate pages processed (heuristic for multi-page documents)
    pages_processed = total_pages or 1
    if total_pages and total_pages > 1:
        # Estimate based on content - very rough heuristic
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


if __name__ == "__main__":
    # Test the quality analyzer
    test_cases = [
        {
            "text": "This is a well-structured document with multiple paragraphs.\n\nIt contains proper sentences and formatting. The content is readable and meaningful.",
            "file_type": "pdf",
            "total_pages": 2
        },
        {
            "text": "abc123!@#$%^&*()_+",
            "file_type": "txt",
            "total_pages": 1
        },
        {
            "text": "",
            "file_type": "docx",
            "total_pages": 1
        }
    ]

    for i, test in enumerate(test_cases):
        print(f"\nTest case {i + 1}:")
        print(f"Text: {test['text'][:50]}...")
        result = analyze_quality(test['text'], test['file_type'], test['total_pages'])
        print(f"Quality metrics: {result}")