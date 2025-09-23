"""
Custom validation utilities for API requests.
"""

import re
from typing import Any, Dict, List, Optional, Union
from urllib.parse import urlparse
from pydantic import validator

from core.logging import get_logger

logger = get_logger("validators")


class ValidationError(Exception):
    """Custom validation error."""

    def __init__(self, field: str, message: str, value: Any = None):
        self.field = field
        self.message = message
        self.value = value
        super().__init__(f"Validation error in field '{field}': {message}")


def validate_url(url: str, allowed_schemes: List[str] = None) -> bool:
    """Validate URL format and scheme."""
    if allowed_schemes is None:
        allowed_schemes = ['http', 'https']

    try:
        parsed = urlparse(url)

        # Check scheme
        if parsed.scheme not in allowed_schemes:
            raise ValidationError("url", f"URL scheme must be one of {allowed_schemes}")

        # Check if host exists
        if not parsed.netloc:
            raise ValidationError("url", "URL must include a valid host")

        # Basic domain validation
        domain_pattern = re.compile(
            r'^(?:[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?\.)*[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?$'
        )

        host = parsed.netloc.split(':')[0]  # Remove port if present
        if not domain_pattern.match(host) and not _is_valid_ip(host):
            raise ValidationError("url", "URL contains invalid domain or IP address")

        return True

    except ValidationError:
        raise
    except Exception as e:
        raise ValidationError("url", f"Invalid URL format: {str(e)}")


def _is_valid_ip(ip: str) -> bool:
    """Check if string is a valid IP address."""
    try:
        import ipaddress
        ipaddress.ip_address(ip)
        return True
    except:
        return False


def validate_file_size(size: int, max_size: int = None, min_size: int = 1) -> bool:
    """Validate file size constraints."""
    if max_size is None:
        from core.config import settings
        max_size = settings.max_file_size

    if size < min_size:
        raise ValidationError("file_size", f"File size must be at least {min_size} bytes")

    if size > max_size:
        raise ValidationError("file_size", f"File size cannot exceed {max_size} bytes ({size} bytes provided)")

    return True


def validate_filename(filename: str, max_length: int = 255) -> bool:
    """Validate filename format and security."""
    if not filename:
        raise ValidationError("filename", "Filename cannot be empty")

    if len(filename) > max_length:
        raise ValidationError("filename", f"Filename too long (max {max_length} characters)")

    # Check for directory traversal attempts
    dangerous_patterns = ['../', '..\\', '/', '\\']
    for pattern in dangerous_patterns:
        if pattern in filename:
            raise ValidationError("filename", "Filename contains invalid path characters")

    # Check for reserved names (Windows)
    reserved_names = [
        'CON', 'PRN', 'AUX', 'NUL',
        'COM1', 'COM2', 'COM3', 'COM4', 'COM5', 'COM6', 'COM7', 'COM8', 'COM9',
        'LPT1', 'LPT2', 'LPT3', 'LPT4', 'LPT5', 'LPT6', 'LPT7', 'LPT8', 'LPT9'
    ]

    base_name = filename.split('.')[0].upper()
    if base_name in reserved_names:
        raise ValidationError("filename", f"Filename '{filename}' is reserved")

    # Check for valid characters
    if re.search(r'[<>:"|?*\x00-\x1f]', filename):
        raise ValidationError("filename", "Filename contains invalid characters")

    return True


def validate_api_key(api_key: str, min_length: int = 10, max_length: int = 128) -> bool:
    """Validate API key format."""
    if not api_key:
        raise ValidationError("api_key", "API key cannot be empty")

    if len(api_key) < min_length:
        raise ValidationError("api_key", f"API key must be at least {min_length} characters")

    if len(api_key) > max_length:
        raise ValidationError("api_key", f"API key cannot exceed {max_length} characters")

    # Check for valid characters (alphanumeric, hyphens, underscores)
    if not re.match(r'^[a-zA-Z0-9\-_]+$', api_key):
        raise ValidationError("api_key", "API key contains invalid characters")

    return True


def validate_metadata(metadata: Dict[str, Any], max_keys: int = 50, max_value_length: int = 1000) -> bool:
    """Validate metadata dictionary."""
    if not isinstance(metadata, dict):
        raise ValidationError("metadata", "Metadata must be a dictionary")

    if len(metadata) > max_keys:
        raise ValidationError("metadata", f"Metadata cannot have more than {max_keys} keys")

    for key, value in metadata.items():
        # Validate key
        if not isinstance(key, str):
            raise ValidationError("metadata", f"Metadata key '{key}' must be a string")

        if len(key) > 100:
            raise ValidationError("metadata", f"Metadata key '{key}' is too long (max 100 characters)")

        if not re.match(r'^[a-zA-Z0-9_\-\.]+$', key):
            raise ValidationError("metadata", f"Metadata key '{key}' contains invalid characters")

        # Validate value
        if isinstance(value, str) and len(value) > max_value_length:
            raise ValidationError("metadata", f"Metadata value for key '{key}' is too long")

        # Only allow basic types
        if not isinstance(value, (str, int, float, bool, type(None))):
            raise ValidationError("metadata", f"Metadata value for key '{key}' has unsupported type")

    return True


def validate_batch_request(files: List[Any], max_files: int = 20) -> bool:
    """Validate batch processing request."""
    if not files:
        raise ValidationError("files", "Batch request must contain at least one file")

    if len(files) > max_files:
        raise ValidationError("files", f"Batch request cannot contain more than {max_files} files")

    return True


def validate_webhook_url(url: str) -> bool:
    """Validate webhook URL with additional security checks."""
    # Basic URL validation
    validate_url(url, ['https'])  # Only allow HTTPS for webhooks

    parsed = urlparse(url)

    # Block private/local IP ranges
    host = parsed.netloc.split(':')[0]
    if _is_private_ip(host):
        raise ValidationError("webhook_url", "Webhook URLs cannot point to private IP addresses")

    # Block localhost
    if host.lower() in ['localhost', '127.0.0.1', '::1']:
        raise ValidationError("webhook_url", "Webhook URLs cannot point to localhost")

    return True


def _is_private_ip(ip: str) -> bool:
    """Check if IP address is in private range."""
    try:
        import ipaddress
        ip_obj = ipaddress.ip_address(ip)
        return ip_obj.is_private
    except:
        return False


def validate_time_window(seconds: int, min_window: int = 60, max_window: int = 86400) -> bool:
    """Validate time window parameter."""
    if seconds < min_window:
        raise ValidationError("time_window", f"Time window must be at least {min_window} seconds")

    if seconds > max_window:
        raise ValidationError("time_window", f"Time window cannot exceed {max_window} seconds")

    return True


def validate_pagination(offset: int = 0, limit: int = 50, max_limit: int = 1000) -> bool:
    """Validate pagination parameters."""
    if offset < 0:
        raise ValidationError("offset", "Offset cannot be negative")

    if limit < 1:
        raise ValidationError("limit", "Limit must be at least 1")

    if limit > max_limit:
        raise ValidationError("limit", f"Limit cannot exceed {max_limit}")

    return True


class RequestValidator:
    """Comprehensive request validator."""

    def __init__(self):
        self.errors: List[Dict[str, str]] = []

    def validate(self, field: str, value: Any, validator_func, *args, **kwargs) -> 'RequestValidator':
        """Add validation check to the validator."""
        try:
            validator_func(value, *args, **kwargs)
        except ValidationError as e:
            self.errors.append({
                "field": field,
                "message": e.message,
                "value": str(value) if value is not None else None
            })
        except Exception as e:
            self.errors.append({
                "field": field,
                "message": f"Validation failed: {str(e)}",
                "value": str(value) if value is not None else None
            })

        return self

    def is_valid(self) -> bool:
        """Check if all validations passed."""
        return len(self.errors) == 0

    def get_errors(self) -> List[Dict[str, str]]:
        """Get list of validation errors."""
        return self.errors.copy()

    def clear(self) -> 'RequestValidator':
        """Clear validation errors."""
        self.errors.clear()
        return self


# Convenience function to create validator
def create_validator() -> RequestValidator:
    """Create new request validator instance."""
    return RequestValidator()