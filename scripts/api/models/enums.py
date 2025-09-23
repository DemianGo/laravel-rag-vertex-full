"""
Enumerations for API models and business logic.
"""

from enum import Enum


class FileType(str, Enum):
    """Supported file types for document extraction."""
    PDF = "pdf"
    DOCX = "docx"
    DOC = "doc"
    XLSX = "xlsx"
    XLS = "xls"
    PPTX = "pptx"
    PPT = "ppt"
    TXT = "txt"
    CSV = "csv"
    RTF = "rtf"
    HTML = "html"
    HTM = "htm"
    XML = "xml"


class ExtractionStatus(str, Enum):
    """Status of extraction job."""
    PENDING = "pending"
    PROCESSING = "processing"
    COMPLETED = "completed"
    FAILED = "failed"
    CANCELLED = "cancelled"


class QualityRating(str, Enum):
    """Quality rating for extracted text."""
    EXCELLENT = "excellent"
    GOOD = "good"
    POOR = "poor"


class JobType(str, Enum):
    """Type of extraction job."""
    SINGLE_FILE = "single_file"
    BATCH = "batch"
    URL_DOWNLOAD = "url_download"


class ErrorCode(str, Enum):
    """Standardized error codes."""
    INVALID_FILE_TYPE = "INVALID_FILE_TYPE"
    FILE_TOO_LARGE = "FILE_TOO_LARGE"
    EXTRACTION_FAILED = "EXTRACTION_FAILED"
    RATE_LIMIT_EXCEEDED = "RATE_LIMIT_EXCEEDED"
    INVALID_API_KEY = "INVALID_API_KEY"
    INTERNAL_ERROR = "INTERNAL_ERROR"
    FILE_NOT_FOUND = "FILE_NOT_FOUND"
    INVALID_URL = "INVALID_URL"
    DOWNLOAD_FAILED = "DOWNLOAD_FAILED"


class CacheStrategy(str, Enum):
    """Cache strategy options."""
    NO_CACHE = "no_cache"
    SHORT_TERM = "short_term"
    LONG_TERM = "long_term"
    PERSISTENT = "persistent"


class NotificationType(str, Enum):
    """Types of notifications/webhooks."""
    JOB_COMPLETED = "job_completed"
    JOB_FAILED = "job_failed"
    BATCH_COMPLETED = "batch_completed"
    ERROR_ALERT = "error_alert"