"""
Language detection for extracted text content.
"""

import re
from typing import Dict, Any, Optional


def detect_language(text_sample: str, max_sample_size: int = 1000) -> Dict[str, Any]:
    """
    Detect the language of the given text sample.

    Args:
        text_sample: Text to analyze for language detection
        max_sample_size: Maximum number of characters to analyze

    Returns:
        Dictionary with language code, confidence, and detection method
    """
    if not text_sample or not text_sample.strip():
        return {
            "language": "unknown",
            "confidence": 0.0,
            "method": "no_text"
        }

    # Clean and limit sample size
    clean_sample = _clean_text_sample(text_sample[:max_sample_size])

    if len(clean_sample) < 10:
        return {
            "language": "unknown",
            "confidence": 0.0,
            "method": "insufficient_text"
        }

    # Try advanced detection first
    try:
        from langdetect import detect, detect_langs

        # Get primary language
        detected_lang = detect(clean_sample)

        # Get confidence scores
        lang_probs = detect_langs(clean_sample)
        confidence = 0.0

        for lang_prob in lang_probs:
            if lang_prob.lang == detected_lang:
                confidence = lang_prob.prob
                break

        # Convert to more specific codes if possible
        language_code = _normalize_language_code(detected_lang, clean_sample)

        # Only return if confidence is above threshold
        if confidence >= 0.7:
            return {
                "language": language_code,
                "confidence": confidence,
                "method": "langdetect"
            }
        else:
            # Fall back to pattern matching
            return _fallback_language_detection(clean_sample)

    except ImportError:
        # langdetect not available, use fallback
        return _fallback_language_detection(clean_sample)
    except Exception:
        # langdetect failed, use fallback
        return _fallback_language_detection(clean_sample)


def _clean_text_sample(text: str) -> str:
    """Clean text sample for better language detection."""
    # Remove URLs
    text = re.sub(r'https?://[^\s]+', '', text)

    # Remove email addresses
    text = re.sub(r'\S+@\S+\.\S+', '', text)

    # Remove excessive whitespace and newlines
    text = re.sub(r'\s+', ' ', text)

    # Remove numbers and special characters that don't help with language detection
    text = re.sub(r'[0-9]+', '', text)

    # Keep only letters, spaces, and basic punctuation
    text = re.sub(r'[^\w\s\.\,\!\?\;\:\-\(\)]', ' ', text)

    return text.strip()


def _normalize_language_code(detected_lang: str, sample_text: str) -> str:
    """Convert basic language codes to more specific regional variants."""

    # Portuguese detection with regional variants
    if detected_lang == 'pt':
        # Look for Brazilian Portuguese indicators
        brazilian_indicators = [
            'você', 'vocês', 'ção', 'brasileir', 'brasil', 'reais',
            'cpf', 'cnpj', 'cep', 'açúcar', 'coração', 'não'
        ]

        portuguese_indicators = [
            'vós', 'estás', 'português', 'portugal', 'euros',
            'açúcar', 'coração'
        ]

        sample_lower = sample_text.lower()
        brazilian_score = sum(1 for indicator in brazilian_indicators if indicator in sample_lower)
        portuguese_score = sum(1 for indicator in portuguese_indicators if indicator in sample_lower)

        if brazilian_score > portuguese_score:
            return 'pt-BR'
        elif portuguese_score > 0:
            return 'pt-PT'
        else:
            return 'pt-BR'  # Default to Brazilian

    # Spanish detection with regional variants
    elif detected_lang == 'es':
        # Look for regional indicators
        latin_american_indicators = [
            'ustedes', 'plata', 'computadora', 'carro', 'chévere'
        ]

        spanish_indicators = [
            'vosotros', 'ordenador', 'coche', 'vale', 'guay'
        ]

        sample_lower = sample_text.lower()
        latin_score = sum(1 for indicator in latin_american_indicators if indicator in sample_lower)
        spanish_score = sum(1 for indicator in spanish_indicators if indicator in sample_lower)

        if spanish_score > latin_score:
            return 'es-ES'
        else:
            return 'es-MX'  # Default to Mexican Spanish

    # English variants
    elif detected_lang == 'en':
        # Look for British vs American indicators
        british_indicators = [
            'colour', 'flavour', 'centre', 'theatre', 'realise', 'organise',
            'whilst', 'amongst', 'pounds', 'quid'
        ]

        american_indicators = [
            'color', 'flavor', 'center', 'theater', 'realize', 'organize',
            'while', 'among', 'dollars', 'bucks'
        ]

        sample_lower = sample_text.lower()
        british_score = sum(1 for indicator in british_indicators if indicator in sample_lower)
        american_score = sum(1 for indicator in american_indicators if indicator in sample_lower)

        if british_score > american_score:
            return 'en-GB'
        else:
            return 'en-US'  # Default to American English

    # For other languages, return as-is with generic regional code
    else:
        return detected_lang


def _fallback_language_detection(text: str) -> Dict[str, Any]:
    """Fallback language detection using character patterns and common words."""

    text_lower = text.lower()

    # Language patterns and indicators
    language_patterns = {
        'pt-BR': {
            'patterns': [
                r'\bda\b', r'\bdo\b', r'\bpara\b', r'\bcom\b', r'\bem\b',
                r'\bque\b', r'\buma\b', r'\buma\b', r'\bção\b', r'\bão\b'
            ],
            'words': [
                'você', 'não', 'muito', 'fazer', 'brasil', 'português',
                'então', 'também', 'porque', 'quando', 'onde', 'como'
            ],
            'chars': 'ãçõá'
        },
        'en-US': {
            'patterns': [
                r'\bthe\b', r'\band\b', r'\bfor\b', r'\bwith\b', r'\bin\b',
                r'\bthat\b', r'\bis\b', r'\bto\b', r'\bof\b', r'\ba\b'
            ],
            'words': [
                'english', 'america', 'united', 'states', 'dollar',
                'you', 'your', 'have', 'will', 'can', 'would'
            ],
            'chars': ''
        },
        'es-MX': {
            'patterns': [
                r'\bel\b', r'\bla\b', r'\bde\b', r'\by\b', r'\ben\b',
                r'\bque\b', r'\bcon\b', r'\bpor\b', r'\bun\b', r'\buna\b'
            ],
            'words': [
                'español', 'mexicano', 'méxico', 'peso', 'usted',
                'muy', 'hacer', 'tener', 'estar', 'cuando', 'donde'
            ],
            'chars': 'ñáéíóú'
        },
        'fr': {
            'patterns': [
                r'\ble\b', r'\bla\b', r'\bde\b', r'\bet\b', r'\bdu\b',
                r'\bun\b', r'\bune\b', r'\bpour\b', r'\bdans\b', r'\bque\b'
            ],
            'words': [
                'français', 'france', 'euro', 'vous', 'très',
                'faire', 'avoir', 'être', 'quand', 'comment'
            ],
            'chars': 'àâéèêëîïôùûüÿç'
        },
        'de': {
            'patterns': [
                r'\bder\b', r'\bdie\b', r'\bdas\b', r'\bund\b', r'\bin\b',
                r'\bzu\b', r'\bden\b', r'\bvon\b', r'\bmit\b', r'\bauf\b'
            ],
            'words': [
                'deutsch', 'deutschland', 'euro', 'sie', 'sehr',
                'machen', 'haben', 'sein', 'wann', 'wie'
            ],
            'chars': 'äöüß'
        },
        'it': {
            'patterns': [
                r'\bil\b', r'\bla\b', r'\bdi\b', r'\be\b', r'\bin\b',
                r'\bcon\b', r'\bper\b', r'\bun\b', r'\buna\b', r'\bdel\b'
            ],
            'words': [
                'italiano', 'italia', 'euro', 'molto', 'fare',
                'avere', 'essere', 'quando', 'come', 'dove'
            ],
            'chars': 'àèéìíîòóù'
        }
    }

    # Score each language
    scores = {}

    for lang, patterns in language_patterns.items():
        score = 0

        # Pattern matching
        for pattern in patterns['patterns']:
            matches = len(re.findall(pattern, text_lower))
            score += matches * 2  # Patterns are weighted more

        # Word matching
        for word in patterns['words']:
            if word in text_lower:
                score += 1

        # Special character frequency
        if patterns['chars']:
            char_count = sum(1 for char in text_lower if char in patterns['chars'])
            score += char_count * 0.5

        scores[lang] = score

    # Find the best match
    if not scores or max(scores.values()) == 0:
        return {
            "language": "unknown",
            "confidence": 0.0,
            "method": "fallback_failed"
        }

    best_lang = max(scores, key=scores.get)
    best_score = scores[best_lang]

    # Calculate confidence based on score and text length
    text_length = len(text.split())
    confidence = min(0.9, (best_score / max(text_length * 0.1, 10)))  # Cap at 90% for fallback

    # Only return if confidence is reasonable
    if confidence >= 0.3:
        return {
            "language": best_lang,
            "confidence": confidence,
            "method": "fallback_patterns"
        }
    else:
        return {
            "language": "unknown",
            "confidence": 0.0,
            "method": "fallback_low_confidence"
        }