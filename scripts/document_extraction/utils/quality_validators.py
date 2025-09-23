"""
Quality validators for document sections and content integrity.
"""

import re
import unicodedata
from typing import Dict, Any, List, Optional, Tuple, Set
from collections import Counter
import math


def validate_section_quality(section_text: str, expected_metrics: Optional[Dict[str, Any]] = None) -> Dict[str, Any]:
    """
    Validate quality of a document section.

    Args:
        section_text: Text content of the section
        expected_metrics: Optional expected quality metrics

    Returns:
        Quality validation results
    """
    if not section_text or not section_text.strip():
        return {
            "is_valid": False,
            "quality_score": 0.0,
            "issues": ["Empty section content"],
            "metrics": {}
        }

    issues = []
    quality_factors = {}

    # Basic text metrics
    word_count = len(section_text.split())
    sentence_count = len([s for s in re.split(r'[.!?]+', section_text) if s.strip()])
    char_count = len(section_text)

    quality_factors["word_count"] = word_count
    quality_factors["sentence_count"] = sentence_count
    quality_factors["avg_sentence_length"] = word_count / max(1, sentence_count)

    # Check for minimum content requirements
    if word_count < 5:
        issues.append("Section too short (less than 5 words)")
        quality_factors["length_penalty"] = 0.3
    elif word_count < 15:
        issues.append("Section very short (less than 15 words)")
        quality_factors["length_penalty"] = 0.1
    else:
        quality_factors["length_penalty"] = 0.0

    # Check text coherence
    coherence_score = _calculate_text_coherence(section_text)
    quality_factors["coherence_score"] = coherence_score
    if coherence_score < 0.3:
        issues.append("Low text coherence detected")

    # Check for encoding issues
    encoding_issues = detect_corrupted_characters(section_text)
    if encoding_issues["has_issues"]:
        issues.extend(encoding_issues["issues"])
        quality_factors["encoding_penalty"] = encoding_issues["severity_score"]
    else:
        quality_factors["encoding_penalty"] = 0.0

    # Check language consistency
    lang_consistency = check_language_consistency(section_text)
    quality_factors["language_consistency"] = lang_consistency["consistency_score"]
    if not lang_consistency["is_consistent"]:
        issues.extend(lang_consistency["issues"])

    # Calculate overall quality score
    quality_score = _calculate_section_quality_score(quality_factors)

    # Compare against expected metrics if provided
    if expected_metrics:
        comparison_issues = _compare_against_expected(quality_factors, expected_metrics)
        issues.extend(comparison_issues)

    return {
        "is_valid": len(issues) == 0 or quality_score > 0.6,
        "quality_score": quality_score,
        "issues": issues,
        "metrics": quality_factors,
        "recommendations": _generate_quality_recommendations(issues, quality_factors)
    }


def detect_corrupted_characters(text: str) -> Dict[str, Any]:
    """
    Detect corrupted or malformed characters in text.

    Args:
        text: Text to analyze

    Returns:
        Dictionary with corruption analysis results
    """
    issues = []
    corruption_patterns = []

    # Check for replacement characters
    replacement_chars = text.count('�')
    if replacement_chars > 0:
        issues.append(f"Found {replacement_chars} replacement characters (�)")
        corruption_patterns.append(("replacement_character", replacement_chars))

    # Check for common encoding corruption patterns
    patterns = {
        "latin1_in_utf8": re.compile(r'[àáâãäåæçèéêëìíîïñòóôõöøùúûüý]{3,}'),
        "windows1252_quotes": re.compile(r'[""]'),
        "html_entities": re.compile(r'&[a-zA-Z][a-zA-Z0-9]*;|&#[0-9]+;'),
        "escaped_unicode": re.compile(r'\\u[0-9a-fA-F]{4}'),
        "control_chars": re.compile(r'[\x00-\x08\x0B-\x0C\x0E-\x1F\x7F]')
    }

    for pattern_name, pattern in patterns.items():
        matches = list(pattern.finditer(text))
        if matches:
            issues.append(f"Found {len(matches)} instances of {pattern_name}")
            corruption_patterns.append((pattern_name, len(matches)))

    # Check for unusual character sequences
    unusual_sequences = _find_unusual_character_sequences(text)
    if unusual_sequences:
        issues.append(f"Found {len(unusual_sequences)} unusual character sequences")
        corruption_patterns.extend(unusual_sequences)

    # Calculate severity score
    severity_score = _calculate_corruption_severity(corruption_patterns)

    return {
        "has_issues": len(issues) > 0,
        "issues": issues,
        "corruption_patterns": corruption_patterns,
        "severity_score": severity_score,
        "affected_char_count": sum(count for _, count in corruption_patterns),
        "total_char_count": len(text)
    }


def validate_table_integrity(table_data: Any) -> Dict[str, Any]:
    """
    Validate integrity of extracted table data.

    Args:
        table_data: Table data in various formats (list of lists, dict, string)

    Returns:
        Validation results for table integrity
    """
    if table_data is None:
        return {
            "is_valid": False,
            "issues": ["Table data is None"],
            "structure": {}
        }

    issues = []
    structure_info = {}

    # Handle different table data formats
    if isinstance(table_data, str):
        # Parse string representation of table
        table_analysis = _analyze_string_table(table_data)
        structure_info = table_analysis["structure"]
        issues.extend(table_analysis["issues"])

    elif isinstance(table_data, list):
        # Handle list of rows
        structure_info = _analyze_list_table(table_data)
        if structure_info["row_count"] == 0:
            issues.append("Empty table")
        elif structure_info["inconsistent_columns"]:
            issues.append("Inconsistent column counts across rows")

    elif isinstance(table_data, dict):
        # Handle dictionary representation
        structure_info = _analyze_dict_table(table_data)
        if not structure_info["has_data"]:
            issues.append("Dictionary contains no table data")

    else:
        issues.append(f"Unsupported table data type: {type(table_data)}")

    # Validate table structure
    if structure_info.get("column_count", 0) < 2:
        issues.append("Table should have at least 2 columns")

    if structure_info.get("row_count", 0) < 2:
        issues.append("Table should have at least 2 rows")

    # Check for data quality issues
    if structure_info.get("empty_cell_ratio", 0) > 0.5:
        issues.append("More than 50% of cells are empty")

    quality_score = _calculate_table_quality_score(structure_info, issues)

    return {
        "is_valid": len(issues) == 0 and quality_score > 0.5,
        "quality_score": quality_score,
        "issues": issues,
        "structure": structure_info,
        "recommendations": _generate_table_recommendations(issues, structure_info)
    }


def check_language_consistency(text: str, expected_language: Optional[str] = None) -> Dict[str, Any]:
    """
    Check language consistency in text.

    Args:
        text: Text to analyze
        expected_language: Expected language code (optional)

    Returns:
        Language consistency analysis
    """
    if not text.strip():
        return {
            "is_consistent": False,
            "consistency_score": 0.0,
            "issues": ["Empty text"],
            "detected_languages": []
        }

    # Detect languages in text
    language_analysis = _detect_text_languages(text)
    detected_langs = language_analysis["languages"]
    dominant_lang = language_analysis["dominant_language"]

    issues = []
    consistency_score = 1.0

    # Check for multiple languages
    if len(detected_langs) > 1:
        minor_langs = [lang for lang, ratio in detected_langs.items() if ratio < 0.1]
        if len(detected_langs) - len(minor_langs) > 1:
            issues.append("Multiple significant languages detected in text")
            consistency_score *= 0.7

    # Check against expected language
    if expected_language and dominant_lang:
        if not dominant_lang.startswith(expected_language.lower()):
            issues.append(f"Detected language ({dominant_lang}) differs from expected ({expected_language})")
            consistency_score *= 0.5

    # Check for script mixing (e.g., Latin + Cyrillic)
    script_analysis = _analyze_text_scripts(text)
    if len(script_analysis["scripts"]) > 2:  # Allow for basic punctuation/numbers
        issues.append("Multiple scripts detected in text")
        consistency_score *= 0.8

    return {
        "is_consistent": len(issues) == 0,
        "consistency_score": consistency_score,
        "issues": issues,
        "detected_languages": detected_langs,
        "dominant_language": dominant_lang,
        "scripts_detected": script_analysis["scripts"]
    }


def _calculate_text_coherence(text: str) -> float:
    """Calculate text coherence score based on various factors."""
    if not text.strip():
        return 0.0

    coherence_factors = []

    # Sentence structure coherence
    sentences = [s.strip() for s in re.split(r'[.!?]+', text) if s.strip()]
    if sentences:
        avg_sentence_length = sum(len(s.split()) for s in sentences) / len(sentences)
        # Optimal sentence length is around 15-20 words
        sentence_length_score = max(0, 1 - abs(avg_sentence_length - 17.5) / 17.5)
        coherence_factors.append(sentence_length_score)

    # Word repetition patterns (some repetition is good, too much is bad)
    words = re.findall(r'\b\w+\b', text.lower())
    if words:
        word_freq = Counter(words)
        repetition_score = min(1.0, len(set(words)) / len(words) + 0.1)
        coherence_factors.append(repetition_score)

    # Punctuation density
    punct_chars = len(re.findall(r'[.!?,;:]', text))
    total_chars = len(text)
    if total_chars > 0:
        punct_density = punct_chars / total_chars
        # Optimal punctuation density is around 5-15%
        punct_score = max(0, 1 - abs(punct_density - 0.1) / 0.1)
        coherence_factors.append(punct_score)

    return sum(coherence_factors) / len(coherence_factors) if coherence_factors else 0.5


def _find_unusual_character_sequences(text: str) -> List[Tuple[str, int]]:
    """Find unusual character sequences that might indicate corruption."""
    unusual_patterns = []

    # Look for sequences of identical characters (might indicate corruption)
    repeated_char_pattern = re.compile(r'(.)\1{4,}')
    for match in repeated_char_pattern.finditer(text):
        if match.group(1) not in ' \n\t-_=':  # Ignore common repeated characters
            unusual_patterns.append(("repeated_characters", len(match.group())))

    # Look for mixed scripts in short sequences
    mixed_script_pattern = re.compile(r'[a-zA-Z][^\w\s]{1,3}[а-яё]|[а-яё][^\w\s]{1,3}[a-zA-Z]')
    mixed_matches = list(mixed_script_pattern.finditer(text))
    if mixed_matches:
        unusual_patterns.append(("mixed_scripts", len(mixed_matches)))

    # Look for excessive special characters
    special_char_sequences = re.compile(r'[^\w\s]{5,}')
    special_matches = list(special_char_sequences.finditer(text))
    if special_matches:
        unusual_patterns.append(("excessive_special_chars", len(special_matches)))

    return unusual_patterns


def _calculate_corruption_severity(corruption_patterns: List[Tuple[str, int]]) -> float:
    """Calculate severity score for corruption patterns."""
    if not corruption_patterns:
        return 0.0

    severity_weights = {
        "replacement_character": 0.8,
        "control_chars": 0.9,
        "latin1_in_utf8": 0.6,
        "windows1252_quotes": 0.3,
        "html_entities": 0.4,
        "escaped_unicode": 0.5,
        "repeated_characters": 0.7,
        "mixed_scripts": 0.6,
        "excessive_special_chars": 0.5
    }

    total_severity = 0.0
    for pattern_type, count in corruption_patterns:
        weight = severity_weights.get(pattern_type, 0.4)
        # Logarithmic scaling for count impact
        count_factor = math.log10(count + 1) / 2  # Scale to roughly 0-1
        total_severity += weight * count_factor

    return min(1.0, total_severity)


def _calculate_section_quality_score(quality_factors: Dict[str, Any]) -> float:
    """Calculate overall quality score for a section."""
    base_score = 0.8

    # Apply penalties
    base_score -= quality_factors.get("length_penalty", 0)
    base_score -= quality_factors.get("encoding_penalty", 0)

    # Apply positive factors
    coherence_score = quality_factors.get("coherence_score", 0.5)
    base_score = (base_score * 0.7) + (coherence_score * 0.3)

    # Language consistency factor
    lang_consistency = quality_factors.get("language_consistency", 1.0)
    base_score *= lang_consistency

    return max(0.0, min(1.0, base_score))


def _compare_against_expected(metrics: Dict[str, Any], expected: Dict[str, Any]) -> List[str]:
    """Compare metrics against expected values."""
    issues = []

    if "min_word_count" in expected:
        actual_words = metrics.get("word_count", 0)
        if actual_words < expected["min_word_count"]:
            issues.append(f"Word count ({actual_words}) below expected minimum ({expected['min_word_count']})")

    if "min_sentences" in expected:
        actual_sentences = metrics.get("sentence_count", 0)
        if actual_sentences < expected["min_sentences"]:
            issues.append(f"Sentence count ({actual_sentences}) below expected minimum ({expected['min_sentences']})")

    if "max_encoding_errors" in expected:
        encoding_penalty = metrics.get("encoding_penalty", 0)
        if encoding_penalty > expected["max_encoding_errors"]:
            issues.append("Encoding errors exceed expected maximum")

    return issues


def _generate_quality_recommendations(issues: List[str], metrics: Dict[str, Any]) -> List[str]:
    """Generate recommendations based on quality issues."""
    recommendations = []

    if any("short" in issue.lower() for issue in issues):
        recommendations.append("Consider combining with adjacent sections or verify complete extraction")

    if any("encoding" in issue.lower() for issue in issues):
        recommendations.append("Reprocess document with correct character encoding")

    if any("coherence" in issue.lower() for issue in issues):
        recommendations.append("Review extraction method - text may be fragmented or corrupted")

    if any("language" in issue.lower() for issue in issues):
        recommendations.append("Verify document language settings and extraction parameters")

    return recommendations


def _analyze_string_table(table_string: str) -> Dict[str, Any]:
    """Analyze table represented as string."""
    issues = []
    structure = {}

    lines = table_string.strip().split('\n')
    structure["row_count"] = len(lines)

    if not lines:
        issues.append("Empty table string")
        return {"structure": structure, "issues": issues}

    # Try to detect separator
    separators = ['\t', '|', ',', ';']
    best_separator = None
    max_columns = 0

    for sep in separators:
        columns = len(lines[0].split(sep))
        if columns > max_columns:
            max_columns = columns
            best_separator = sep

    structure["separator"] = best_separator
    structure["column_count"] = max_columns

    # Check consistency across rows
    column_counts = []
    empty_cells = 0
    total_cells = 0

    for line in lines:
        if best_separator:
            cells = line.split(best_separator)
            column_counts.append(len(cells))
            total_cells += len(cells)
            empty_cells += sum(1 for cell in cells if not cell.strip())

    structure["inconsistent_columns"] = len(set(column_counts)) > 1
    structure["empty_cell_ratio"] = empty_cells / max(1, total_cells)

    return {"structure": structure, "issues": issues}


def _analyze_list_table(table_list: List) -> Dict[str, Any]:
    """Analyze table represented as list of rows."""
    structure = {
        "row_count": len(table_list),
        "column_count": 0,
        "inconsistent_columns": False,
        "empty_cell_ratio": 0.0
    }

    if not table_list:
        return structure

    # Analyze column structure
    column_counts = []
    empty_cells = 0
    total_cells = 0

    for row in table_list:
        if isinstance(row, (list, tuple)):
            column_counts.append(len(row))
            total_cells += len(row)
            empty_cells += sum(1 for cell in row if not str(cell).strip())
        else:
            # Single value row
            column_counts.append(1)
            total_cells += 1
            if not str(row).strip():
                empty_cells += 1

    if column_counts:
        structure["column_count"] = max(column_counts)
        structure["inconsistent_columns"] = len(set(column_counts)) > 1
        structure["empty_cell_ratio"] = empty_cells / total_cells

    return structure


def _analyze_dict_table(table_dict: Dict) -> Dict[str, Any]:
    """Analyze table represented as dictionary."""
    structure = {
        "has_data": False,
        "row_count": 0,
        "column_count": 0
    }

    if "rows" in table_dict and isinstance(table_dict["rows"], list):
        structure["has_data"] = True
        structure["row_count"] = len(table_dict["rows"])
        if table_dict["rows"]:
            first_row = table_dict["rows"][0]
            if isinstance(first_row, (list, tuple)):
                structure["column_count"] = len(first_row)
            elif isinstance(first_row, dict):
                structure["column_count"] = len(first_row)

    elif "columns" in table_dict:
        structure["has_data"] = True
        structure["column_count"] = len(table_dict["columns"])

    return structure


def _calculate_table_quality_score(structure: Dict[str, Any], issues: List[str]) -> float:
    """Calculate quality score for table."""
    base_score = 0.8

    # Penalty for issues
    issue_penalty = len(issues) * 0.1
    base_score -= issue_penalty

    # Bonus for good structure
    if structure.get("row_count", 0) >= 2:
        base_score += 0.1

    if structure.get("column_count", 0) >= 2:
        base_score += 0.1

    # Penalty for empty cells
    empty_ratio = structure.get("empty_cell_ratio", 0)
    base_score -= empty_ratio * 0.3

    return max(0.0, min(1.0, base_score))


def _generate_table_recommendations(issues: List[str], structure: Dict[str, Any]) -> List[str]:
    """Generate recommendations for table quality issues."""
    recommendations = []

    if "Empty table" in issues:
        recommendations.append("Verify table extraction method - table may not be detected properly")

    if "Inconsistent column counts" in issues:
        recommendations.append("Check for merged cells or complex table structure")

    if structure.get("empty_cell_ratio", 0) > 0.3:
        recommendations.append("High number of empty cells - verify table boundaries and structure")

    if structure.get("column_count", 0) < 2:
        recommendations.append("Single column detected - verify this is actually tabular data")

    return recommendations


def _detect_text_languages(text: str) -> Dict[str, Any]:
    """Detect languages in text using character frequency analysis."""
    # Simplified language detection based on character patterns
    # In production, would use proper language detection library

    char_counts = Counter(text.lower())
    total_chars = sum(char_counts.values())

    language_indicators = {
        "en": {"chars": set("abcdefghijklmnopqrstuvwxyz"), "weight": 0},
        "pt": {"chars": set("abcdefghijklmnopqrstuvwxyzãâáàêéèíîóôõúûç"), "weight": 0},
        "es": {"chars": set("abcdefghijklmnopqrstuvwxyzáéíóúüñ"), "weight": 0},
        "fr": {"chars": set("abcdefghijklmnopqrstuvwxyzàâäæçéèêëïîôöùûüÿ"), "weight": 0}
    }

    # Calculate weights for each language
    for char, count in char_counts.items():
        for lang, info in language_indicators.items():
            if char in info["chars"]:
                info["weight"] += count

    # Normalize weights
    for lang in language_indicators:
        if total_chars > 0:
            language_indicators[lang]["weight"] /= total_chars

    # Find dominant language
    dominant = max(language_indicators.items(), key=lambda x: x[1]["weight"])

    return {
        "languages": {lang: info["weight"] for lang, info in language_indicators.items() if info["weight"] > 0.01},
        "dominant_language": dominant[0] if dominant[1]["weight"] > 0.1 else None
    }


def _analyze_text_scripts(text: str) -> Dict[str, Any]:
    """Analyze scripts used in text."""
    scripts = set()

    for char in text:
        try:
            script = unicodedata.name(char).split()[0]
            scripts.add(script)
        except ValueError:
            # Character doesn't have a Unicode name
            continue

    # Simplify script names
    simplified_scripts = set()
    for script in scripts:
        if "LATIN" in script:
            simplified_scripts.add("Latin")
        elif "CYRILLIC" in script:
            simplified_scripts.add("Cyrillic")
        elif "DIGIT" in script:
            simplified_scripts.add("Digits")
        elif any(punct in script for punct in ["COMMA", "PERIOD", "HYPHEN", "SPACE"]):
            simplified_scripts.add("Punctuation")
        else:
            simplified_scripts.add("Other")

    return {"scripts": list(simplified_scripts)}