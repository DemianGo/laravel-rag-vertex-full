"""
Pydantic response models for API endpoints.
"""

from typing import Optional, List, Dict, Any, Union
from pydantic import BaseModel, Field
from datetime import datetime
from models.enums import FileType, ExtractionStatus, QualityRating, JobType, ErrorCode


class QualityMetrics(BaseModel):
    """Quality metrics for extracted content."""

    extraction_success_rate: float = Field(
        description="Percentage of successful extraction"
    )
    total_pages: int = Field(
        description="Total number of pages in document"
    )
    pages_processed: int = Field(
        description="Number of pages successfully processed"
    )
    quality_rating: QualityRating = Field(
        description="Overall quality rating of extraction"
    )
    text_length: int = Field(
        description="Length of extracted text in characters"
    )
    word_count: int = Field(
        description="Number of words extracted"
    )


class ErrorDetail(BaseModel):
    """Detailed error information."""

    code: ErrorCode = Field(description="Standardized error code")
    message: str = Field(description="Human-readable error message")
    details: Optional[Dict[str, Any]] = Field(
        default=None,
        description="Additional error context"
    )
    timestamp: datetime = Field(
        default_factory=datetime.utcnow,
        description="Error occurrence timestamp"
    )


class BaseResponse(BaseModel):
    """Base response model with common fields."""

    success: bool = Field(description="Whether the operation was successful")
    timestamp: datetime = Field(
        default_factory=datetime.utcnow,
        description="Response timestamp"
    )
    request_id: Optional[str] = Field(
        default=None,
        description="Unique request identifier"
    )


class ExtractionResult(BaseModel):
    """Single extraction result."""

    file_type: FileType = Field(description="Detected file type")
    extracted_text: str = Field(description="Extracted text content")
    quality_metrics: QualityMetrics = Field(description="Quality assessment")
    processing_time: float = Field(description="Processing time in seconds")
    file_size: int = Field(description="Original file size in bytes")
    filename: Optional[str] = Field(
        default=None,
        description="Original filename if available"
    )


class SingleFileResponse(BaseResponse):
    """Response for single file extraction."""

    result: Optional[ExtractionResult] = Field(
        default=None,
        description="Extraction result if successful"
    )
    error: Optional[ErrorDetail] = Field(
        default=None,
        description="Error details if failed"
    )


class JobInfo(BaseModel):
    """Information about an extraction job."""

    job_id: str = Field(description="Unique job identifier")
    job_type: JobType = Field(description="Type of extraction job")
    status: ExtractionStatus = Field(description="Current job status")
    created_at: datetime = Field(description="Job creation timestamp")
    updated_at: datetime = Field(description="Last update timestamp")
    api_key_id: str = Field(description="API key identifier (masked)")
    metadata: Optional[Dict[str, Any]] = Field(
        default=None,
        description="Job metadata"
    )


class JobStatusResponse(BaseResponse):
    """Response for job status queries."""

    job_info: JobInfo = Field(description="Job information")
    result: Optional[ExtractionResult] = Field(
        default=None,
        description="Extraction result if completed and requested"
    )
    error: Optional[ErrorDetail] = Field(
        default=None,
        description="Error details if failed"
    )
    progress: Optional[float] = Field(
        default=None,
        ge=0.0,
        le=1.0,
        description="Job progress (0.0 to 1.0) if available"
    )


class BatchResult(BaseModel):
    """Result of batch processing."""

    total_files: int = Field(description="Total number of files processed")
    successful_extractions: int = Field(description="Number of successful extractions")
    failed_extractions: int = Field(description="Number of failed extractions")
    results: List[Union[ExtractionResult, ErrorDetail]] = Field(
        description="Individual extraction results or errors"
    )
    processing_time: float = Field(description="Total processing time in seconds")


class BatchResponse(BaseResponse):
    """Response for batch extraction."""

    job_id: str = Field(description="Batch job identifier")
    result: Optional[BatchResult] = Field(
        default=None,
        description="Batch processing result if completed"
    )
    error: Optional[ErrorDetail] = Field(
        default=None,
        description="Error details if batch job failed"
    )


class FormatInfo(BaseModel):
    """Information about a supported file format."""

    file_type: FileType = Field(description="File type identifier")
    mime_types: List[str] = Field(description="Supported MIME types")
    extensions: List[str] = Field(description="Supported file extensions")
    max_file_size: int = Field(description="Maximum file size in bytes")
    extraction_features: List[str] = Field(description="Supported extraction features")
    limitations: Optional[List[str]] = Field(
        default=None,
        description="Known limitations"
    )
    examples: Optional[List[str]] = Field(
        default=None,
        description="Usage examples"
    )


class FormatsResponse(BaseResponse):
    """Response for supported formats query."""

    formats: List[FormatInfo] = Field(description="Supported file formats")


class HealthStatus(BaseModel):
    """Health check status."""

    component: str = Field(description="Component name")
    status: str = Field(description="Health status (healthy/unhealthy/degraded)")
    details: Optional[Dict[str, Any]] = Field(
        default=None,
        description="Additional health details"
    )
    response_time: Optional[float] = Field(
        default=None,
        description="Component response time in seconds"
    )


class HealthResponse(BaseResponse):
    """Health check response."""

    overall_status: str = Field(description="Overall system health status")
    components: List[HealthStatus] = Field(description="Individual component statuses")
    uptime: float = Field(description="System uptime in seconds")
    version: str = Field(description="API version")


class MetricPoint(BaseModel):
    """Single metric data point."""

    timestamp: datetime = Field(description="Metric timestamp")
    value: float = Field(description="Metric value")
    labels: Optional[Dict[str, str]] = Field(
        default=None,
        description="Metric labels"
    )


class MetricsResponse(BaseResponse):
    """Response for metrics queries."""

    metrics: Dict[str, List[MetricPoint]] = Field(description="Metric data points")
    summary: Dict[str, float] = Field(description="Metric summary statistics")


class AdminResponse(BaseResponse):
    """Response for administrative operations."""

    operation: str = Field(description="Performed operation")
    affected_items: int = Field(description="Number of affected items")
    details: Optional[Dict[str, Any]] = Field(
        default=None,
        description="Operation details"
    )