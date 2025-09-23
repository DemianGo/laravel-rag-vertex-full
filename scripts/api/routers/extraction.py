"""
Document extraction API endpoints.
"""

from fastapi import APIRouter, UploadFile, File, Depends, HTTPException, status, BackgroundTasks, Request
from typing import Optional, Dict, Any
import time

from core.config import settings
from core.security import get_current_api_key
from core.dependencies import get_redis
from core.logging import get_logger
from models.requests import SingleFileExtractionRequest, URLExtractionRequest, JobStatusRequest
from models.responses import SingleFileResponse, JobStatusResponse, ExtractionResult, ErrorDetail, JobInfo
from models.enums import ErrorCode, ExtractionStatus, JobType
from services.extractor_service import ExtractorService
from services.cache_service import CacheService
from services.metrics_service import MetricsService
from services.notification_service import NotificationService
from utils.file_utils import file_validator, get_file_info
from utils.validators import validate_url, validate_webhook_url, ValidationError
from middleware.request_id import get_request_id

logger = get_logger("extraction_router")
router = APIRouter(prefix="/extract", tags=["extraction"])

# Service instances (would be injected in production)
extractor_service = ExtractorService()
cache_service = CacheService()
metrics_service = MetricsService()
notification_service = NotificationService()


@router.post("/file", response_model=SingleFileResponse)
async def extract_from_file(
    request: Request,
    background_tasks: BackgroundTasks,
    file: UploadFile = File(...),
    extract_tables: bool = True,
    extract_images: bool = False,
    quality_threshold: float = 0.7,
    webhook_url: Optional[str] = None,
    api_key: str = Depends(get_current_api_key),
    redis_client = Depends(get_redis)
):
    """
    Extract text from uploaded document.

    Supports: PDF, DOCX, XLSX, PPTX, TXT, CSV, RTF, HTML, XML
    """
    start_time = time.time()

    try:
        # Validate webhook URL if provided
        if webhook_url:
            try:
                validate_webhook_url(webhook_url)
            except ValidationError as e:
                raise HTTPException(
                    status_code=status.HTTP_400_BAD_REQUEST,
                    detail={
                        "error": {
                            "code": ErrorCode.INVALID_URL,
                            "message": e.message,
                            "field": e.field
                        }
                    }
                )

        # Read file content
        file_content = await file.read()

        # Validate file
        is_valid, error_msg, file_type = file_validator.validate_file(file_content, file.filename)
        if not is_valid:
            metrics_service.record_extraction_metrics(
                file_type="unknown" if not file_type else file_type.value,
                success=False,
                duration=time.time() - start_time,
                file_size=len(file_content)
            )

            raise HTTPException(
                status_code=status.HTTP_400_BAD_REQUEST,
                detail={
                    "error": {
                        "code": ErrorCode.INVALID_FILE_TYPE,
                        "message": error_msg,
                        "details": {"filename": file.filename}
                    }
                }
            )

        # Cache check
        cache_key = cache_service.generate_cache_key("extraction", api_key, file.filename, len(file_content))
        cached_result = await cache_service.get(cache_key)

        if cached_result and settings.enable_metrics:
            metrics_service.increment_counter("cache_hits_total")
            logger.info("Serving cached extraction result", filename=file.filename, cache_key=cache_key)

            return SingleFileResponse(
                success=True,
                result=ExtractionResult(**cached_result),
                request_id=get_request_id(request)
            )

        # Extract text
        try:
            result = await extractor_service.extract_file(
                file_content=file_content,
                filename=file.filename,
                api_key=api_key,
                metadata={
                    "extract_tables": extract_tables,
                    "extract_images": extract_images,
                    "quality_threshold": quality_threshold,
                    "webhook_url": webhook_url
                }
            )

            # Cache result
            await cache_service.set(cache_key, result.dict())

            # Record metrics
            metrics_service.record_extraction_metrics(
                file_type=result.file_type.value,
                success=True,
                duration=result.processing_time,
                file_size=result.file_size
            )

            # Send webhook notification if requested
            if webhook_url and settings.enable_webhooks:
                background_tasks.add_task(
                    notification_service.notify_job_completed,
                    webhook_url,
                    f"file_{int(time.time())}",
                    result.dict()
                )

            logger.info(
                "File extraction completed",
                filename=file.filename,
                file_type=result.file_type.value,
                text_length=result.quality_metrics.text_length,
                processing_time=result.processing_time
            )

            return SingleFileResponse(
                success=True,
                result=result,
                request_id=get_request_id(request)
            )

        except Exception as e:
            duration = time.time() - start_time
            metrics_service.record_extraction_metrics(
                file_type=file_type.value if file_type else "unknown",
                success=False,
                duration=duration,
                file_size=len(file_content)
            )

            # Send webhook notification for failure if requested
            if webhook_url and settings.enable_webhooks:
                error_details = {
                    "code": ErrorCode.EXTRACTION_FAILED,
                    "message": str(e),
                    "filename": file.filename
                }
                background_tasks.add_task(
                    notification_service.notify_job_failed,
                    webhook_url,
                    f"file_{int(time.time())}",
                    error_details
                )

            logger.error(
                "File extraction failed",
                filename=file.filename,
                error=str(e),
                duration=duration
            )

            raise HTTPException(
                status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
                detail={
                    "error": {
                        "code": ErrorCode.EXTRACTION_FAILED,
                        "message": f"Extraction failed: {str(e)}",
                        "details": {"filename": file.filename}
                    }
                }
            )

    except HTTPException:
        raise
    except Exception as e:
        logger.error("Unexpected error in file extraction", error=str(e))
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail={
                "error": {
                    "code": ErrorCode.INTERNAL_ERROR,
                    "message": "Internal server error",
                    "details": {"hint": "Please try again or contact support"}
                }
            }
        )


@router.post("/url", response_model=SingleFileResponse)
async def extract_from_url(
    http_request: Request,
    background_tasks: BackgroundTasks,
    request: URLExtractionRequest,
    api_key: str = Depends(get_current_api_key),
    redis_client = Depends(get_redis)
):
    """
    Download file from URL and extract text.

    Only HTTPS URLs are allowed for security.
    """
    start_time = time.time()

    try:
        # Validate URL
        try:
            validate_url(str(request.url), ['https'])
        except ValidationError as e:
            raise HTTPException(
                status_code=status.HTTP_400_BAD_REQUEST,
                detail={
                    "error": {
                        "code": ErrorCode.INVALID_URL,
                        "message": e.message,
                        "details": {"url": str(request.url)}
                    }
                }
            )

        # Validate webhook URL if provided
        if request.webhook_url:
            try:
                validate_webhook_url(str(request.webhook_url))
            except ValidationError as e:
                raise HTTPException(
                    status_code=status.HTTP_400_BAD_REQUEST,
                    detail={
                        "error": {
                            "code": ErrorCode.INVALID_URL,
                            "message": e.message,
                            "field": e.field
                        }
                    }
                )

        # Cache check
        cache_key = cache_service.generate_cache_key("url_extraction", api_key, str(request.url))
        cached_result = await cache_service.get(cache_key)

        if cached_result:
            metrics_service.increment_counter("cache_hits_total")
            logger.info("Serving cached URL extraction result", url=str(request.url))

            return SingleFileResponse(
                success=True,
                result=ExtractionResult(**cached_result),
                request_id=get_request_id(http_request)
            )

        # Extract from URL
        try:
            result = await extractor_service.extract_from_url(
                url=str(request.url),
                api_key=api_key,
                download_timeout=request.download_timeout,
                max_file_size=request.max_file_size,
                metadata=request.metadata
            )

            # Cache result
            await cache_service.set(cache_key, result.dict())

            # Record metrics
            metrics_service.record_extraction_metrics(
                file_type=result.file_type.value,
                success=True,
                duration=result.processing_time,
                file_size=result.file_size
            )

            # Send webhook notification if requested
            if request.webhook_url and settings.enable_webhooks:
                background_tasks.add_task(
                    notification_service.notify_job_completed,
                    str(request.webhook_url),
                    f"url_{int(time.time())}",
                    result.dict()
                )

            logger.info(
                "URL extraction completed",
                url=str(request.url),
                file_type=result.file_type.value,
                text_length=result.quality_metrics.text_length,
                processing_time=result.processing_time
            )

            return SingleFileResponse(
                success=True,
                result=result,
                request_id=get_request_id(http_request)
            )

        except Exception as e:
            duration = time.time() - start_time

            # Send webhook notification for failure if requested
            if request.webhook_url and settings.enable_webhooks:
                error_details = {
                    "code": ErrorCode.EXTRACTION_FAILED,
                    "message": str(e),
                    "url": str(request.url)
                }
                background_tasks.add_task(
                    notification_service.notify_job_failed,
                    str(request.webhook_url),
                    f"url_{int(time.time())}",
                    error_details
                )

            logger.error(
                "URL extraction failed",
                url=str(request.url),
                error=str(e),
                duration=duration
            )

            # Determine appropriate error code
            error_code = ErrorCode.DOWNLOAD_FAILED if "download" in str(e).lower() else ErrorCode.EXTRACTION_FAILED

            raise HTTPException(
                status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
                detail={
                    "error": {
                        "code": error_code,
                        "message": str(e),
                        "details": {"url": str(request.url)}
                    }
                }
            )

    except HTTPException:
        raise
    except Exception as e:
        logger.error("Unexpected error in URL extraction", error=str(e))
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail={
                "error": {
                    "code": ErrorCode.INTERNAL_ERROR,
                    "message": "Internal server error",
                    "details": {"hint": "Please try again or contact support"}
                }
            }
        )


@router.get("/status/{job_id}", response_model=JobStatusResponse)
async def get_job_status(
    request: Request,
    job_id: str,
    include_result: bool = False,
    api_key: str = Depends(get_current_api_key)
):
    """
    Get status of an extraction job.

    Returns job information and optionally the extraction result.
    """
    try:
        job = extractor_service.get_job_status(job_id)

        if not job:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail={
                    "error": {
                        "code": ErrorCode.FILE_NOT_FOUND,
                        "message": f"Job {job_id} not found",
                        "details": {"job_id": job_id}
                    }
                }
            )

        # Check if job belongs to the same API key
        if job.api_key != api_key:
            raise HTTPException(
                status_code=status.HTTP_403_FORBIDDEN,
                detail={
                    "error": {
                        "code": ErrorCode.INVALID_API_KEY,
                        "message": "Job does not belong to your API key",
                        "details": {"job_id": job_id}
                    }
                }
            )

        # Build job info
        job_info = JobInfo(
            job_id=job.job_id,
            job_type=job.job_type,
            status=job.status,
            created_at=job.created_at,
            updated_at=job.updated_at,
            api_key_id=job.api_key[:10] + "...",  # Masked API key
            metadata=job.metadata
        )

        response = JobStatusResponse(
            success=True,
            job_info=job_info,
            request_id=get_request_id(request)
        )

        # Include result if requested and available
        if include_result and job.result:
            response.result = job.result

        # Include error if job failed
        if job.error:
            response.error = job.error

        logger.debug("Job status retrieved", job_id=job_id, status=job.status.value)

        return response

    except HTTPException:
        raise
    except Exception as e:
        logger.error("Error retrieving job status", job_id=job_id, error=str(e))
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail={
                "error": {
                    "code": ErrorCode.INTERNAL_ERROR,
                    "message": "Failed to retrieve job status",
                    "details": {"job_id": job_id}
                }
            }
        )