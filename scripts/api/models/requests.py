"""
Pydantic request models for API endpoints.
"""

from typing import Optional, List, Dict, Any
from pydantic import BaseModel, Field, validator, HttpUrl
from models.enums import FileType, CacheStrategy, JobType


class ExtractionRequest(BaseModel):
    """Base extraction request model."""

    cache_strategy: CacheStrategy = Field(
        default=CacheStrategy.SHORT_TERM,
        description="Caching strategy for the extraction result"
    )

    webhook_url: Optional[HttpUrl] = Field(
        default=None,
        description="Webhook URL for job completion notification"
    )

    metadata: Optional[Dict[str, Any]] = Field(
        default_factory=dict,
        description="Additional metadata for the extraction job"
    )


class SingleFileExtractionRequest(ExtractionRequest):
    """Request model for single file extraction."""

    extract_tables: bool = Field(
        default=True,
        description="Whether to extract table content"
    )

    extract_images: bool = Field(
        default=False,
        description="Whether to extract image descriptions (future feature)"
    )

    quality_threshold: float = Field(
        default=0.7,
        ge=0.0,
        le=1.0,
        description="Minimum quality threshold for extraction"
    )


class BatchExtractionRequest(ExtractionRequest):
    """Request model for batch file extraction."""

    max_parallel_jobs: int = Field(
        default=5,
        ge=1,
        le=20,
        description="Maximum number of parallel extraction jobs"
    )

    fail_fast: bool = Field(
        default=False,
        description="Whether to stop batch processing on first failure"
    )

    merge_results: bool = Field(
        default=False,
        description="Whether to merge all results into single response"
    )


class URLExtractionRequest(ExtractionRequest):
    """Request model for URL-based extraction."""

    url: HttpUrl = Field(
        description="URL of the document to download and extract"
    )

    download_timeout: int = Field(
        default=30,
        ge=5,
        le=300,
        description="Timeout for downloading the file (seconds)"
    )

    follow_redirects: bool = Field(
        default=True,
        description="Whether to follow HTTP redirects"
    )

    max_file_size: Optional[int] = Field(
        default=None,
        ge=1024,
        description="Maximum file size to download (bytes)"
    )

    @validator('url')
    def validate_url_scheme(cls, v):
        """Validate URL scheme is HTTP or HTTPS."""
        if v.scheme not in ['http', 'https']:
            raise ValueError('URL must use HTTP or HTTPS scheme')
        return v


class JobStatusRequest(BaseModel):
    """Request model for job status queries."""

    include_result: bool = Field(
        default=False,
        description="Whether to include extraction result in response"
    )

    include_metadata: bool = Field(
        default=True,
        description="Whether to include job metadata in response"
    )


class AdminCacheRequest(BaseModel):
    """Request model for cache administration."""

    pattern: Optional[str] = Field(
        default="*",
        description="Cache key pattern to clear (Redis pattern syntax)"
    )

    force: bool = Field(
        default=False,
        description="Force clear even if keys are recently accessed"
    )


class MetricsRequest(BaseModel):
    """Request model for metrics queries."""

    time_window: int = Field(
        default=3600,
        ge=60,
        le=86400,
        description="Time window for metrics in seconds"
    )

    granularity: str = Field(
        default="minute",
        pattern="^(second|minute|hour|day)$",
        description="Granularity of metrics aggregation"
    )

    include_percentiles: bool = Field(
        default=True,
        description="Whether to include percentile metrics"
    )


class FormatInfoRequest(BaseModel):
    """Request model for format information queries."""

    file_type: Optional[FileType] = Field(
        default=None,
        description="Specific file type to get information about"
    )

    include_limitations: bool = Field(
        default=True,
        description="Whether to include format limitations"
    )

    include_examples: bool = Field(
        default=False,
        description="Whether to include usage examples"
    )