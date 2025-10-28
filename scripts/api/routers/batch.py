"""
Batch processing endpoints for multiple file extraction.
"""

from fastapi import APIRouter, UploadFile, File, Depends, HTTPException, status, BackgroundTasks, Request
from typing import List, Optional
import asyncio
import time

from core.config import settings
from core.security import get_current_api_key
from core.logging import get_logger
from models.requests import BatchExtractionRequest
from models.responses import BatchResponse, BatchResult, ErrorDetail
from models.enums import ErrorCode, JobType, ExtractionStatus
from services.extractor_service import ExtractorService
from services.metrics_service import MetricsService
from services.notification_service import NotificationService
try:
    from utils.file_utils import file_validator
    from utils.validators import validate_batch_request, validate_webhook_url, ValidationError
except ImportError:
    # Fallback if utils module not found
    def file_validator(file_path: str) -> bool:
        return True
    def validate_batch_request(request_data: dict) -> bool:
        return True
    def validate_webhook_url(url: str) -> bool:
        return True
    class ValidationError(Exception):
        pass
from middleware.request_id import get_request_id

logger = get_logger("batch_router")
router = APIRouter(prefix="/batch", tags=["batch"])

# Service instances
extractor_service = ExtractorService()
metrics_service = MetricsService()
notification_service = NotificationService()


@router.post("/extract", response_model=BatchResponse)
async def batch_extract_files(
    request: Request,
    background_tasks: BackgroundTasks,
    files: List[UploadFile] = File(...),
    max_parallel_jobs: int = 5,
    fail_fast: bool = False,
    merge_results: bool = False,
    webhook_url: Optional[str] = None,
    api_key: str = Depends(get_current_api_key)
):
    """
    Extract text from multiple files simultaneously.

    Features:
    - Parallel processing with configurable concurrency
    - Fail-fast option to stop on first error
    - Result merging for combined output
    - Webhook notifications for batch completion
    """
    start_time = time.time()
    job_id = f"batch_{int(time.time())}_{len(files)}"

    try:
        # Validate batch request
        try:
            validate_batch_request(files, max_files=20)
        except ValidationError as e:
            raise HTTPException(
                status_code=status.HTTP_400_BAD_REQUEST,
                detail={
                    "error": {
                        "code": ErrorCode.INVALID_FILE_TYPE,
                        "message": e.message,
                        "field": e.field
                    }
                }
            )

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

        # Limit parallel jobs
        max_parallel_jobs = min(max_parallel_jobs, 20, len(files))

        logger.info(
            "Starting batch extraction",
            job_id=job_id,
            file_count=len(files),
            max_parallel=max_parallel_jobs,
            fail_fast=fail_fast
        )

        # Prepare tasks
        tasks = []
        file_infos = []

        for i, file in enumerate(files):
            # Read file content
            file_content = await file.read()

            # Basic file validation
            is_valid, error_msg, file_type = file_validator.validate_file(file_content, file.filename)

            file_info = {
                "index": i,
                "filename": file.filename,
                "size": len(file_content),
                "content": file_content,
                "is_valid": is_valid,
                "error": error_msg,
                "file_type": file_type
            }

            file_infos.append(file_info)

            # Skip invalid files if not in fail_fast mode
            if not is_valid:
                if fail_fast:
                    raise HTTPException(
                        status_code=status.HTTP_400_BAD_REQUEST,
                        detail={
                            "error": {
                                "code": ErrorCode.INVALID_FILE_TYPE,
                                "message": f"File '{file.filename}': {error_msg}",
                                "details": {"filename": file.filename, "index": i}
                            }
                        }
                    )
                continue

            # Create extraction task
            task = _extract_file_task(
                file_content=file_content,
                filename=file.filename,
                api_key=api_key,
                file_index=i
            )
            tasks.append(task)

        # Run batch extraction with controlled concurrency
        semaphore = asyncio.Semaphore(max_parallel_jobs)
        results = await asyncio.gather(
            *[_run_with_semaphore(semaphore, task) for task in tasks],
            return_exceptions=True
        )

        # Process results
        successful_extractions = 0
        failed_extractions = 0
        extraction_results = []
        combined_text = []

        for i, result in enumerate(results):
            file_info = file_infos[i]

            if not file_info["is_valid"]:
                # Add error for invalid file
                error_detail = ErrorDetail(
                    code=ErrorCode.INVALID_FILE_TYPE,
                    message=file_info["error"],
                    details={"filename": file_info["filename"], "index": i}
                )
                extraction_results.append(error_detail)
                failed_extractions += 1
                continue

            if isinstance(result, Exception):
                # Extraction failed
                error_detail = ErrorDetail(
                    code=ErrorCode.EXTRACTION_FAILED,
                    message=str(result),
                    details={"filename": file_info["filename"], "index": i}
                )
                extraction_results.append(error_detail)
                failed_extractions += 1

                # Stop if fail_fast is enabled
                if fail_fast:
                    logger.error(
                        "Batch extraction failed (fail_fast enabled)",
                        job_id=job_id,
                        failed_file=file_info["filename"],
                        error=str(result)
                    )
                    raise HTTPException(
                        status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
                        detail={
                            "error": {
                                "code": ErrorCode.EXTRACTION_FAILED,
                                "message": f"Batch extraction failed on file '{file_info['filename']}': {str(result)}",
                                "details": {"job_id": job_id, "failed_index": i}
                            }
                        }
                    )
            else:
                # Extraction successful
                extraction_results.append(result)
                successful_extractions += 1

                # Add to combined text if merging is enabled
                if merge_results:
                    combined_text.append(f"=== {file_info['filename']} ===\n{result.extracted_text}\n")

        # Create batch result
        processing_time = time.time() - start_time

        batch_result = BatchResult(
            total_files=len(files),
            successful_extractions=successful_extractions,
            failed_extractions=failed_extractions,
            results=extraction_results,
            processing_time=processing_time
        )

        # Add combined text if merging was requested
        if merge_results and combined_text:
            batch_result.combined_text = "\n".join(combined_text)

        # Record metrics
        metrics_service.increment_counter("batch_extractions_total", 1.0, {"files": str(len(files))})
        metrics_service.record_histogram("batch_processing_duration_seconds", processing_time)

        # Send webhook notification if requested
        if webhook_url and settings.enable_webhooks:
            background_tasks.add_task(
                notification_service.notify_batch_completed,
                webhook_url,
                job_id,
                batch_result.dict()
            )

        logger.info(
            "Batch extraction completed",
            job_id=job_id,
            total_files=len(files),
            successful=successful_extractions,
            failed=failed_extractions,
            processing_time=processing_time
        )

        return BatchResponse(
            success=True,
            job_id=job_id,
            result=batch_result,
            request_id=get_request_id(request)
        )

    except HTTPException:
        raise
    except Exception as e:
        processing_time = time.time() - start_time

        logger.error(
            "Batch extraction error",
            job_id=job_id,
            error=str(e),
            processing_time=processing_time
        )

        # Send webhook notification for batch failure
        if webhook_url and settings.enable_webhooks:
            error_details = {
                "code": ErrorCode.INTERNAL_ERROR,
                "message": str(e),
                "job_id": job_id
            }
            background_tasks.add_task(
                notification_service.notify_job_failed,
                webhook_url,
                job_id,
                error_details
            )

        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail={
                "error": {
                    "code": ErrorCode.INTERNAL_ERROR,
                    "message": f"Batch processing failed: {str(e)}",
                    "details": {"job_id": job_id}
                }
            }
        )


async def _extract_file_task(file_content: bytes, filename: str, api_key: str, file_index: int):
    """Task function for individual file extraction."""
    try:
        result = await extractor_service.extract_file(
            file_content=file_content,
            filename=filename,
            api_key=api_key,
            metadata={"batch_index": file_index}
        )

        logger.debug(
            "Batch file extraction completed",
            filename=filename,
            index=file_index,
            file_type=result.file_type.value
        )

        return result

    except Exception as e:
        logger.error(
            "Batch file extraction failed",
            filename=filename,
            index=file_index,
            error=str(e)
        )
        raise


async def _run_with_semaphore(semaphore: asyncio.Semaphore, coro):
    """Run coroutine with semaphore for concurrency control."""
    async with semaphore:
        return await coro