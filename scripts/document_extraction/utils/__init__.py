"""
Utility functions for document extraction.
"""

from .detector import detect_document_type
from .language_detector import detect_language

__all__ = ["detect_document_type", "detect_language"]