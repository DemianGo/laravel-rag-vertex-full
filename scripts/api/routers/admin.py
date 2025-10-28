"""
Administrative endpoints for system management.
"""

from fastapi import APIRouter, Depends, HTTPException, status
from typing import Dict, Any, Optional

from core.config import settings
from core.security import get_current_api_key
from core.dependencies import get_redis
from core.logging import get_logger
from models.requests import AdminCacheRequest, MetricsRequest, FormatInfoRequest
from models.responses import AdminResponse, MetricsResponse, FormatsResponse, FormatInfo
from models.enums import FileType, ErrorCode
from services.cache_service import CacheService
from services.metrics_service import MetricsService
from services.extractor_service import ExtractorService
try:
    from utils.file_utils import file_validator
except ImportError:
    # Fallback if utils module not found
    def file_validator(file_path: str) -> bool:
        return True

logger = get_logger("admin_router")
router = APIRouter(prefix="/admin", tags=["administration"])

# Service instances
cache_service = CacheService()
metrics_service = MetricsService()
extractor_service = ExtractorService()


@router.post("/cache/clear", response_model=AdminResponse)
async def clear_cache(
    request: AdminCacheRequest = AdminCacheRequest(),
    api_key: str = Depends(get_current_api_key),
    redis_client = Depends(get_redis)
):
    """
    Clear cache entries matching the specified pattern.

    Requires administrative API key.
    """
    try:
        # Initialize cache service with Redis client
        cache_service.redis_client = redis_client

        # Clear cache entries
        cleared_count = await cache_service.clear_pattern(request.pattern)

        logger.info(
            "Cache cleared by admin",
            api_key_prefix=api_key[:10],
            pattern=request.pattern,
            cleared_count=cleared_count,
            force=request.force
        )

        return AdminResponse(
            success=True,
            operation="cache_clear",
            affected_items=cleared_count,
            details={
                "pattern": request.pattern,
                "force": request.force,
                "cache_stats": cache_service.get_stats()
            }
        )

    except Exception as e:
        logger.error(
            "Cache clear failed",
            api_key_prefix=api_key[:10],
            pattern=request.pattern,
            error=str(e)
        )

        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail={
                "error": {
                    "code": ErrorCode.INTERNAL_ERROR,
                    "message": f"Cache clear failed: {str(e)}",
                    "details": {"pattern": request.pattern}
                }
            }
        )


@router.get("/jobs/cleanup", response_model=AdminResponse)
async def cleanup_old_jobs(
    max_age_hours: int = 24,
    api_key: str = Depends(get_current_api_key)
):
    """
    Clean up old completed/failed jobs from memory.

    Removes jobs older than specified hours.
    """
    try:
        initial_count = len(extractor_service.active_jobs)

        await extractor_service.cleanup_old_jobs(max_age_hours)

        final_count = len(extractor_service.active_jobs)
        cleaned_count = initial_count - final_count

        logger.info(
            "Job cleanup completed",
            api_key_prefix=api_key[:10],
            max_age_hours=max_age_hours,
            cleaned_count=cleaned_count,
            remaining_jobs=final_count
        )

        return AdminResponse(
            success=True,
            operation="job_cleanup",
            affected_items=cleaned_count,
            details={
                "max_age_hours": max_age_hours,
                "initial_jobs": initial_count,
                "remaining_jobs": final_count
            }
        )

    except Exception as e:
        logger.error(
            "Job cleanup failed",
            api_key_prefix=api_key[:10],
            error=str(e)
        )

        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail={
                "error": {
                    "code": ErrorCode.INTERNAL_ERROR,
                    "message": f"Job cleanup failed: {str(e)}"
                }
            }
        )


@router.get("/stats", response_model=Dict[str, Any])
async def get_system_stats(
    api_key: str = Depends(get_current_api_key),
    redis_client = Depends(get_redis)
):
    """
    Get comprehensive system statistics.

    Returns cache stats, job stats, and system metrics.
    """
    try:
        # Initialize cache service with Redis client
        cache_service.redis_client = redis_client

        # Collect statistics
        cache_stats = cache_service.get_stats()
        cache_health = await cache_service.health_check()
        metrics_summary = metrics_service.get_summary_stats()

        # Job statistics
        job_stats = {
            "total_jobs": len(extractor_service.active_jobs),
            "jobs_by_status": {},
            "jobs_by_type": {}
        }

        for job in extractor_service.active_jobs.values():
            # Count by status
            status_key = job.status.value
            job_stats["jobs_by_status"][status_key] = job_stats["jobs_by_status"].get(status_key, 0) + 1

            # Count by type
            type_key = job.job_type.value
            job_stats["jobs_by_type"][type_key] = job_stats["jobs_by_type"].get(type_key, 0) + 1

        stats = {
            "cache": {
                "stats": cache_stats,
                "health": cache_health
            },
            "jobs": job_stats,
            "metrics": metrics_summary,
            "system": {
                "environment": settings.environment,
                "debug": settings.debug,
                "version": settings.app_version
            }
        }

        logger.debug(
            "System stats retrieved",
            api_key_prefix=api_key[:10],
            total_jobs=job_stats["total_jobs"]
        )

        return stats

    except Exception as e:
        logger.error(
            "Failed to retrieve system stats",
            api_key_prefix=api_key[:10],
            error=str(e)
        )

        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail={
                "error": {
                    "code": ErrorCode.INTERNAL_ERROR,
                    "message": f"Failed to retrieve system stats: {str(e)}"
                }
            }
        )


@router.get("/formats", response_model=FormatsResponse)
async def get_supported_formats(
    request: FormatInfoRequest = FormatInfoRequest(),
    api_key: str = Depends(get_current_api_key)
):
    """
    Get information about supported file formats.

    Returns detailed format specifications and limitations.
    """
    try:
        supported_formats = file_validator.get_supported_formats()
        format_info_list = []

        for format_data in supported_formats:
            file_type = FileType(format_data["file_type"])

            # Skip if specific file type requested and doesn't match
            if request.file_type and file_type != request.file_type:
                continue

            # Build format info
            format_info = FormatInfo(
                file_type=file_type,
                mime_types=[format_data["mime_type"]],
                extensions=format_data["extensions"],
                max_file_size=format_data["max_size"],
                extraction_features=_get_extraction_features(file_type)
            )

            # Add limitations if requested
            if request.include_limitations:
                format_info.limitations = _get_format_limitations(file_type)

            # Add examples if requested
            if request.include_examples:
                format_info.examples = _get_format_examples(file_type)

            format_info_list.append(format_info)

        logger.debug(
            "Format information retrieved",
            api_key_prefix=api_key[:10],
            requested_type=request.file_type.value if request.file_type else "all",
            formats_count=len(format_info_list)
        )

        return FormatsResponse(
            success=True,
            formats=format_info_list
        )

    except Exception as e:
        logger.error(
            "Failed to retrieve format information",
            api_key_prefix=api_key[:10],
            error=str(e)
        )

        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail={
                "error": {
                    "code": ErrorCode.INTERNAL_ERROR,
                    "message": f"Failed to retrieve format information: {str(e)}"
                }
            }
        )


def _get_extraction_features(file_type: FileType) -> list:
    """Get supported extraction features for file type."""
    features_map = {
        FileType.PDF: ["text", "tables", "metadata", "page_numbers"],
        FileType.DOCX: ["text", "tables", "headers", "formatting"],
        FileType.XLSX: ["text", "tables", "sheets", "formulas"],
        FileType.PPTX: ["text", "slides", "notes", "formatting"],
        FileType.TXT: ["text", "encoding_detection"],
        FileType.CSV: ["text", "tables", "delimiter_detection"],
        FileType.RTF: ["text", "basic_formatting"],
        FileType.HTML: ["text", "structure", "tables", "lists"],
        FileType.XML: ["text", "structure", "attributes"]
    }
    return features_map.get(file_type, ["text"])


def _get_format_limitations(file_type: FileType) -> list:
    """Get known limitations for file type."""
    limitations_map = {
        FileType.PDF: [
            "Scanned PDFs require OCR (not supported)",
            "Complex layouts may affect text order",
            "Password-protected files not supported"
        ],
        FileType.DOCX: [
            "Embedded objects not extracted",
            "Complex formatting may be lost"
        ],
        FileType.XLSX: [
            "Charts and images not extracted",
            "Macros and formulas not processed"
        ],
        FileType.RTF: [
            "Basic RTF parser, complex formatting may be lost"
        ],
        FileType.HTML: [
            "JavaScript content not executed",
            "Dynamic content not captured"
        ]
    }
    return limitations_map.get(file_type, [])


def _get_format_examples(file_type: FileType) -> list:
    """Get usage examples for file type."""
    examples_map = {
        FileType.PDF: [
            "Research papers and documents",
            "Reports and presentations",
            "Forms and contracts"
        ],
        FileType.DOCX: [
            "Microsoft Word documents",
            "Business reports",
            "Academic papers"
        ],
        FileType.XLSX: [
            "Spreadsheets and data tables",
            "Financial reports",
            "Data exports"
        ],
        FileType.TXT: [
            "Plain text files",
            "Log files",
            "Configuration files"
        ]
    }
    return examples_map.get(file_type, [])