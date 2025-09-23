"""
File utility functions for document processing.
"""

import os
import mimetypes
import magic
from typing import Optional, Tuple, List
from pathlib import Path

from core.config import settings
from core.logging import get_logger
from models.enums import FileType

logger = get_logger("file_utils")


class FileValidator:
    """File validation utilities."""

    def __init__(self):
        self.max_file_size = settings.max_file_size
        self.allowed_mime_types = set(settings.allowed_file_types)

        # MIME type to file type mapping
        self.mime_to_filetype = {
            "application/pdf": FileType.PDF,
            "application/vnd.openxmlformats-officedocument.wordprocessingml.document": FileType.DOCX,
            "application/msword": FileType.DOC,
            "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet": FileType.XLSX,
            "application/vnd.ms-excel": FileType.XLS,
            "application/vnd.openxmlformats-officedocument.presentationml.presentation": FileType.PPTX,
            "application/vnd.ms-powerpoint": FileType.PPT,
            "text/plain": FileType.TXT,
            "text/csv": FileType.CSV,
            "application/rtf": FileType.RTF,
            "text/html": FileType.HTML,
            "application/xml": FileType.XML,
            "text/xml": FileType.XML,
        }

        # Extension to file type mapping (fallback)
        self.ext_to_filetype = {
            ".pdf": FileType.PDF,
            ".docx": FileType.DOCX,
            ".doc": FileType.DOC,
            ".xlsx": FileType.XLSX,
            ".xls": FileType.XLS,
            ".pptx": FileType.PPTX,
            ".ppt": FileType.PPT,
            ".txt": FileType.TXT,
            ".csv": FileType.CSV,
            ".rtf": FileType.RTF,
            ".html": FileType.HTML,
            ".htm": FileType.HTML,
            ".xml": FileType.XML,
        }

    def validate_file(self, file_content: bytes, filename: str) -> Tuple[bool, Optional[str], Optional[FileType]]:
        """
        Validate file content and detect file type.
        Returns (is_valid, error_message, file_type)
        """

        # Check file size
        if len(file_content) > self.max_file_size:
            return False, f"File size ({len(file_content)} bytes) exceeds maximum allowed size ({self.max_file_size} bytes)", None

        if len(file_content) == 0:
            return False, "File is empty", None

        # Detect MIME type
        mime_type, file_type = self.detect_file_type(file_content, filename)

        if not mime_type:
            return False, "Could not determine file type", None

        # Check if MIME type is allowed
        if mime_type not in self.allowed_mime_types:
            return False, f"File type '{mime_type}' is not supported", None

        logger.debug(
            "File validated successfully",
            filename=filename,
            mime_type=mime_type,
            file_type=file_type.value if file_type else None,
            size=len(file_content)
        )

        return True, None, file_type

    def detect_file_type(self, file_content: bytes, filename: str) -> Tuple[Optional[str], Optional[FileType]]:
        """
        Detect file type from content and filename.
        Returns (mime_type, file_type)
        """

        mime_type = None
        file_type = None

        # Try python-magic first (more accurate)
        try:
            mime_type = magic.from_buffer(file_content, mime=True)
            file_type = self.mime_to_filetype.get(mime_type)
        except Exception as e:
            logger.debug("python-magic detection failed", error=str(e))

        # Fallback to mimetypes module
        if not mime_type:
            try:
                mime_type, _ = mimetypes.guess_type(filename)
                if mime_type:
                    file_type = self.mime_to_filetype.get(mime_type)
            except Exception as e:
                logger.debug("mimetypes detection failed", error=str(e))

        # Last resort: use file extension
        if not file_type:
            try:
                ext = Path(filename).suffix.lower()
                file_type = self.ext_to_filetype.get(ext)
                if file_type:
                    # Guess MIME type based on file type
                    for mime, ftype in self.mime_to_filetype.items():
                        if ftype == file_type:
                            mime_type = mime
                            break
            except Exception as e:
                logger.debug("Extension-based detection failed", error=str(e))

        return mime_type, file_type

    def is_supported_extension(self, filename: str) -> bool:
        """Check if file extension is supported."""
        ext = Path(filename).suffix.lower()
        return ext in self.ext_to_filetype

    def get_supported_formats(self) -> List[dict]:
        """Get list of supported file formats."""
        formats = []

        for mime_type, file_type in self.mime_to_filetype.items():
            # Find extensions for this MIME type
            extensions = []
            for ext, ftype in self.ext_to_filetype.items():
                if ftype == file_type:
                    extensions.append(ext)

            formats.append({
                "file_type": file_type.value,
                "mime_type": mime_type,
                "extensions": extensions,
                "max_size": self.max_file_size
            })

        return formats


def sanitize_filename(filename: str) -> str:
    """Sanitize filename to prevent security issues."""
    # Remove path components
    filename = os.path.basename(filename)

    # Remove dangerous characters
    import re
    filename = re.sub(r'[^\w\-_\.]', '_', filename)

    # Ensure it's not empty and has reasonable length
    if not filename or filename.startswith('.'):
        filename = f"file_{filename}"

    if len(filename) > 255:
        name, ext = os.path.splitext(filename)
        filename = name[:250] + ext

    return filename


def get_file_info(file_content: bytes, filename: str) -> dict:
    """Get comprehensive file information."""
    validator = FileValidator()

    is_valid, error, file_type = validator.validate_file(file_content, filename)
    mime_type, detected_type = validator.detect_file_type(file_content, filename)

    return {
        "filename": sanitize_filename(filename),
        "size": len(file_content),
        "mime_type": mime_type,
        "file_type": file_type.value if file_type else None,
        "is_valid": is_valid,
        "error": error,
        "extension": Path(filename).suffix.lower(),
        "is_supported": validator.is_supported_extension(filename)
    }


def format_file_size(size_bytes: int) -> str:
    """Format file size in human-readable format."""
    if size_bytes == 0:
        return "0 B"

    size_names = ["B", "KB", "MB", "GB", "TB"]
    import math
    i = int(math.floor(math.log(size_bytes, 1024)))
    p = math.pow(1024, i)
    s = round(size_bytes / p, 2)

    return f"{s} {size_names[i]}"


def is_text_file(file_content: bytes) -> bool:
    """Check if file content appears to be text."""
    try:
        # Try to decode as UTF-8
        file_content.decode('utf-8')
        return True
    except UnicodeDecodeError:
        pass

    # Check for binary markers
    binary_markers = [b'\x00', b'\xFF\xFE', b'\xFE\xFF', b'\xEF\xBB\xBF']
    for marker in binary_markers:
        if marker in file_content[:1024]:  # Check first 1KB
            return False

    # If majority of bytes are printable ASCII, consider it text
    try:
        sample = file_content[:1024]
        printable_count = sum(1 for b in sample if 32 <= b <= 126 or b in [9, 10, 13])
        return (printable_count / len(sample)) > 0.7
    except:
        return False


# Global file validator instance
file_validator = FileValidator()