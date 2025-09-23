"""
Core extraction service that orchestrates document processing.
"""

import asyncio
import tempfile
import os
from typing import Optional, List, Dict, Any
from pathlib import Path
import aiofiles
import httpx
from datetime import datetime

from core.logging import get_logger
from models.enums import FileType, ExtractionStatus, JobType
from models.responses import ExtractionResult, QualityMetrics, ErrorDetail, ErrorCode

# Import extractors from document_extraction module
import sys
sys.path.append(str(Path(__file__).parent.parent.parent / "document_extraction"))

from main_extractor import extract_document

logger = get_logger("extractor_service")


class ExtractionJob:
    """Represents a single extraction job."""

    def __init__(self, job_id: str, job_type: JobType, api_key: str):
        self.job_id = job_id
        self.job_type = job_type
        self.api_key = api_key
        self.status = ExtractionStatus.PENDING
        self.created_at = datetime.utcnow()
        self.updated_at = self.created_at
        self.result: Optional[ExtractionResult] = None
        self.error: Optional[ErrorDetail] = None
        self.metadata: Dict[str, Any] = {}

    def update_status(self, status: ExtractionStatus):
        """Update job status."""
        self.status = status
        self.updated_at = datetime.utcnow()


class ExtractorService:
    """Service for managing document extraction operations."""

    def __init__(self):
        self.active_jobs: Dict[str, ExtractionJob] = {}
        self.max_concurrent_jobs = 10
        self._semaphore = asyncio.Semaphore(self.max_concurrent_jobs)

    async def extract_file(
        self,
        file_content: bytes,
        filename: str,
        api_key: str,
        job_id: Optional[str] = None,
        metadata: Optional[Dict[str, Any]] = None
    ) -> ExtractionResult:
        """Extract text from uploaded file."""

        if not job_id:
            job_id = self._generate_job_id()

        job = ExtractionJob(job_id, JobType.SINGLE_FILE, api_key)
        job.metadata = metadata or {}
        self.active_jobs[job_id] = job

        try:
            job.update_status(ExtractionStatus.PROCESSING)

            # Save file temporarily
            async with self._semaphore:
                with tempfile.NamedTemporaryFile(delete=False, suffix=Path(filename).suffix) as temp_file:
                    temp_file.write(file_content)
                    temp_path = temp_file.name

                try:
                    # Extract using the main extractor
                    start_time = datetime.utcnow()
                    extraction_result = await asyncio.get_event_loop().run_in_executor(
                        None, extract_document, temp_path
                    )
                    processing_time = (datetime.utcnow() - start_time).total_seconds()

                    if extraction_result["success"]:
                        # Convert to our response model
                        quality_metrics = QualityMetrics(
                            extraction_success_rate=extraction_result["quality_metrics"]["extraction_success_rate"],
                            total_pages=extraction_result["quality_metrics"]["total_pages"],
                            pages_processed=extraction_result["quality_metrics"]["pages_processed"],
                            quality_rating=extraction_result["quality_metrics"]["quality_rating"],
                            text_length=len(extraction_result["extracted_text"]),
                            word_count=len(extraction_result["extracted_text"].split())
                        )

                        result = ExtractionResult(
                            file_type=FileType(extraction_result["file_type"]),
                            extracted_text=extraction_result["extracted_text"],
                            quality_metrics=quality_metrics,
                            processing_time=processing_time,
                            file_size=len(file_content),
                            filename=filename
                        )

                        job.result = result
                        job.update_status(ExtractionStatus.COMPLETED)

                        logger.info(
                            "File extraction completed",
                            job_id=job_id,
                            filename=filename,
                            file_type=result.file_type,
                            text_length=result.quality_metrics.text_length,
                            processing_time=processing_time
                        )

                        return result

                    else:
                        # Handle extraction failure
                        error = ErrorDetail(
                            code=ErrorCode.EXTRACTION_FAILED,
                            message=extraction_result.get("error", "Unknown extraction error"),
                            details={"filename": filename}
                        )
                        job.error = error
                        job.update_status(ExtractionStatus.FAILED)

                        logger.error(
                            "File extraction failed",
                            job_id=job_id,
                            filename=filename,
                            error=error.message
                        )

                        raise Exception(error.message)

                finally:
                    # Clean up temporary file
                    try:
                        os.unlink(temp_path)
                    except:
                        pass

        except Exception as e:
            error = ErrorDetail(
                code=ErrorCode.INTERNAL_ERROR,
                message=f"Extraction failed: {str(e)}",
                details={"filename": filename}
            )
            job.error = error
            job.update_status(ExtractionStatus.FAILED)

            logger.error(
                "File extraction error",
                job_id=job_id,
                filename=filename,
                error=str(e)
            )

            raise

        finally:
            # Keep job in memory for status queries
            pass

    async def extract_from_url(
        self,
        url: str,
        api_key: str,
        download_timeout: int = 30,
        max_file_size: Optional[int] = None,
        job_id: Optional[str] = None,
        metadata: Optional[Dict[str, Any]] = None
    ) -> ExtractionResult:
        """Download file from URL and extract text."""

        if not job_id:
            job_id = self._generate_job_id()

        job = ExtractionJob(job_id, JobType.URL_DOWNLOAD, api_key)
        job.metadata = metadata or {}
        job.metadata["source_url"] = url
        self.active_jobs[job_id] = job

        try:
            job.update_status(ExtractionStatus.PROCESSING)

            # Download file
            async with httpx.AsyncClient(timeout=download_timeout) as client:
                logger.info("Downloading file from URL", job_id=job_id, url=url)

                response = await client.get(url)
                response.raise_for_status()

                file_content = response.content
                content_length = len(file_content)

                # Check file size limit
                if max_file_size and content_length > max_file_size:
                    raise Exception(f"File size ({content_length} bytes) exceeds limit ({max_file_size} bytes)")

                # Try to determine filename from URL or Content-Disposition header
                filename = self._get_filename_from_url_or_headers(url, response.headers)

                logger.info(
                    "File downloaded successfully",
                    job_id=job_id,
                    filename=filename,
                    file_size=content_length
                )

                # Extract text
                return await self.extract_file(file_content, filename, api_key, job_id, job.metadata)

        except httpx.HTTPError as e:
            error = ErrorDetail(
                code=ErrorCode.DOWNLOAD_FAILED,
                message=f"Failed to download file: {str(e)}",
                details={"url": url}
            )
            job.error = error
            job.update_status(ExtractionStatus.FAILED)

            logger.error("URL download failed", job_id=job_id, url=url, error=str(e))
            raise Exception(error.message)

        except Exception as e:
            error = ErrorDetail(
                code=ErrorCode.INTERNAL_ERROR,
                message=f"URL extraction failed: {str(e)}",
                details={"url": url}
            )
            job.error = error
            job.update_status(ExtractionStatus.FAILED)

            logger.error("URL extraction error", job_id=job_id, url=url, error=str(e))
            raise

    def get_job_status(self, job_id: str) -> Optional[ExtractionJob]:
        """Get status of a specific job."""
        return self.active_jobs.get(job_id)

    def _generate_job_id(self) -> str:
        """Generate unique job ID."""
        import uuid
        return f"job_{uuid.uuid4().hex[:16]}"

    def _get_filename_from_url_or_headers(self, url: str, headers: Dict[str, str]) -> str:
        """Extract filename from URL or HTTP headers."""
        # Try Content-Disposition header first
        content_disposition = headers.get("content-disposition", "")
        if "filename=" in content_disposition:
            try:
                filename = content_disposition.split("filename=")[1].strip('"\'')
                if filename:
                    return filename
            except:
                pass

        # Fall back to URL path
        try:
            path = Path(url).name
            if path and "." in path:
                return path
        except:
            pass

        # Default filename
        return "downloaded_file.pdf"

    async def cleanup_old_jobs(self, max_age_hours: int = 24):
        """Clean up old completed jobs."""
        cutoff_time = datetime.utcnow().timestamp() - (max_age_hours * 3600)

        jobs_to_remove = []
        for job_id, job in self.active_jobs.items():
            if (job.status in [ExtractionStatus.COMPLETED, ExtractionStatus.FAILED] and
                job.updated_at.timestamp() < cutoff_time):
                jobs_to_remove.append(job_id)

        for job_id in jobs_to_remove:
            del self.active_jobs[job_id]

        if jobs_to_remove:
            logger.info("Cleaned up old jobs", count=len(jobs_to_remove))