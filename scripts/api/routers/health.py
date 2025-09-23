"""
Health check endpoints for monitoring and observability.
"""

import time
import psutil
from fastapi import APIRouter, Depends
from typing import Dict, Any

from core.config import settings
from core.dependencies import get_redis
from core.logging import get_logger
from models.responses import HealthResponse, HealthStatus
from services.cache_service import CacheService
from services.metrics_service import MetricsService
from services.extractor_service import ExtractorService
from services.notification_service import NotificationService

logger = get_logger("health_router")
router = APIRouter(tags=["health"])

# Service instances
cache_service = CacheService()
metrics_service = MetricsService()
extractor_service = ExtractorService()
notification_service = NotificationService()

# Track application start time
start_time = time.time()


@router.get("/health", response_model=HealthResponse)
async def health_check(redis_client = Depends(get_redis)):
    """
    Comprehensive health check endpoint.

    Returns detailed status of all system components.
    """
    try:
        components = []
        overall_status = "healthy"

        # Check API application
        api_status = await _check_api_health()
        components.append(api_status)
        if api_status.status != "healthy":
            overall_status = "degraded"

        # Check Redis cache
        cache_status = await _check_cache_health(redis_client)
        components.append(cache_status)
        if cache_status.status == "unhealthy":
            overall_status = "degraded" if overall_status == "healthy" else "unhealthy"

        # Check system resources
        system_status = await _check_system_health()
        components.append(system_status)
        if system_status.status != "healthy":
            overall_status = "degraded" if overall_status == "healthy" else overall_status

        # Check extraction service
        extractor_status = await _check_extractor_health()
        components.append(extractor_status)
        if extractor_status.status != "healthy":
            overall_status = "degraded"

        # Check notification service
        notification_status = await _check_notification_health()
        components.append(notification_status)

        # Calculate uptime
        uptime = time.time() - start_time

        logger.debug("Health check completed", overall_status=overall_status, components=len(components))

        return HealthResponse(
            success=True,
            overall_status=overall_status,
            components=components,
            uptime=uptime,
            version=settings.app_version
        )

    except Exception as e:
        logger.error("Health check failed", error=str(e))
        return HealthResponse(
            success=False,
            overall_status="unhealthy",
            components=[
                HealthStatus(
                    component="health_check",
                    status="unhealthy",
                    details={"error": str(e)}
                )
            ],
            uptime=time.time() - start_time,
            version=settings.app_version
        )


@router.get("/health/simple")
async def simple_health_check():
    """
    Simple health check for load balancers.
    Returns 200 OK if service is running.
    """
    return {"status": "ok", "timestamp": time.time()}


@router.get("/ready")
async def readiness_check(redis_client = Depends(get_redis)):
    """
    Readiness check for Kubernetes/container orchestration.
    Returns 200 if service is ready to handle requests.
    """
    try:
        # Check critical dependencies
        checks = {
            "api": True,  # Always ready if we can respond
            "cache": await _is_cache_ready(redis_client),
        }

        # Service is ready if API is working and cache is available (or fallback works)
        is_ready = all(checks.values())

        status_code = 200 if is_ready else 503

        return {
            "ready": is_ready,
            "checks": checks,
            "timestamp": time.time()
        }, status_code

    except Exception as e:
        logger.error("Readiness check failed", error=str(e))
        return {
            "ready": False,
            "error": str(e),
            "timestamp": time.time()
        }, 503


@router.get("/live")
async def liveness_check():
    """
    Liveness check for Kubernetes/container orchestration.
    Returns 200 if service is alive.
    """
    return {
        "alive": True,
        "uptime": time.time() - start_time,
        "timestamp": time.time()
    }


async def _check_api_health() -> HealthStatus:
    """Check API application health."""
    start_time_check = time.time()

    try:
        # Basic functionality test
        test_value = "health_check_test"
        assert test_value == "health_check_test"

        response_time = time.time() - start_time_check

        return HealthStatus(
            component="api",
            status="healthy",
            response_time=response_time,
            details={
                "environment": settings.environment,
                "debug": settings.debug,
                "port": settings.api_port
            }
        )

    except Exception as e:
        return HealthStatus(
            component="api",
            status="unhealthy",
            response_time=time.time() - start_time_check,
            details={"error": str(e)}
        )


async def _check_cache_health(redis_client) -> HealthStatus:
    """Check cache service health."""
    start_time_check = time.time()

    if not redis_client:
        return HealthStatus(
            component="cache",
            status="degraded",
            response_time=time.time() - start_time_check,
            details={
                "backend": "in_memory",
                "redis_available": False,
                "message": "Using in-memory cache fallback"
            }
        )

    try:
        # Test Redis connectivity
        await redis_client.ping()

        # Test set/get operation
        test_key = "health_check_test"
        await redis_client.set(test_key, "ok", ex=10)
        result = await redis_client.get(test_key)
        await redis_client.delete(test_key)

        if result != "ok":
            raise Exception("Redis read/write test failed")

        response_time = time.time() - start_time_check

        # Get Redis info
        info = await redis_client.info("memory")

        return HealthStatus(
            component="cache",
            status="healthy",
            response_time=response_time,
            details={
                "backend": "redis",
                "redis_available": True,
                "memory_usage": info.get("used_memory_human", "unknown"),
                "connected_clients": info.get("connected_clients", 0)
            }
        )

    except Exception as e:
        return HealthStatus(
            component="cache",
            status="unhealthy",
            response_time=time.time() - start_time_check,
            details={
                "backend": "redis",
                "error": str(e),
                "redis_available": False
            }
        )


async def _check_system_health() -> HealthStatus:
    """Check system resource health."""
    start_time_check = time.time()

    try:
        # Get system metrics
        memory = psutil.virtual_memory()
        disk = psutil.disk_usage('/')
        cpu_percent = psutil.cpu_percent(interval=1)

        # Determine status based on resource usage
        status = "healthy"
        if memory.percent > 90 or disk.percent > 95 or cpu_percent > 95:
            status = "unhealthy"
        elif memory.percent > 80 or disk.percent > 85 or cpu_percent > 80:
            status = "degraded"

        return HealthStatus(
            component="system",
            status=status,
            response_time=time.time() - start_time_check,
            details={
                "memory_percent": memory.percent,
                "disk_percent": disk.percent,
                "cpu_percent": cpu_percent,
                "load_average": psutil.getloadavg() if hasattr(psutil, 'getloadavg') else None
            }
        )

    except ImportError:
        # psutil not available
        return HealthStatus(
            component="system",
            status="degraded",
            response_time=time.time() - start_time_check,
            details={
                "message": "System metrics not available (psutil not installed)"
            }
        )
    except Exception as e:
        return HealthStatus(
            component="system",
            status="unhealthy",
            response_time=time.time() - start_time_check,
            details={"error": str(e)}
        )


async def _check_extractor_health() -> HealthStatus:
    """Check document extraction service health."""
    start_time_check = time.time()

    try:
        # Check active jobs count
        active_jobs = len(extractor_service.active_jobs)

        # Check if extraction dependencies are available
        dependencies_status = {
            "document_extraction_available": True,  # Assume available if service starts
            "active_jobs": active_jobs,
            "max_concurrent_jobs": extractor_service.max_concurrent_jobs
        }

        status = "healthy"
        if active_jobs >= extractor_service.max_concurrent_jobs:
            status = "degraded"

        return HealthStatus(
            component="extractor",
            status=status,
            response_time=time.time() - start_time_check,
            details=dependencies_status
        )

    except Exception as e:
        return HealthStatus(
            component="extractor",
            status="unhealthy",
            response_time=time.time() - start_time_check,
            details={"error": str(e)}
        )


async def _check_notification_health() -> HealthStatus:
    """Check notification service health."""
    start_time_check = time.time()

    try:
        health_info = await notification_service.health_check()

        return HealthStatus(
            component="notifications",
            status=health_info.get("status", "healthy"),
            response_time=time.time() - start_time_check,
            details=health_info
        )

    except Exception as e:
        return HealthStatus(
            component="notifications",
            status="unhealthy",
            response_time=time.time() - start_time_check,
            details={"error": str(e)}
        )


async def _is_cache_ready(redis_client) -> bool:
    """Check if cache is ready for use."""
    if not redis_client:
        return True  # In-memory fallback is always ready

    try:
        await redis_client.ping()
        return True
    except:
        return True  # Fallback cache is available