"""
Enterprise Document Extraction API - FastAPI Application

High-performance, scalable API for document text extraction with enterprise features:
- API key authentication and rate limiting
- Redis caching with in-memory fallback
- Comprehensive metrics and monitoring
- Structured logging and request tracing
- Webhook notifications
- Batch processing support
"""

import sys
import os
sys.path.append(os.path.dirname(__file__))

import time
from contextlib import asynccontextmanager
from fastapi import FastAPI, Request, HTTPException, status
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import JSONResponse, PlainTextResponse
from fastapi.exceptions import RequestValidationError
from slowapi import _rate_limit_exceeded_handler
from slowapi.errors import RateLimitExceeded
import uvicorn

# Core imports
from core.config import settings
from core.logging import configure_logging, get_logger
from core.dependencies import get_container
from core.security import SecurityHeaders

# Middleware imports
from middleware.auth import AuthMiddleware
from middleware.rate_limiting import RateLimitMiddleware, create_rate_limiter
from middleware.request_id import RequestIDMiddleware

# Router imports
from routers import extraction, batch, health, admin

# Service imports
from services.cache_service import CacheService
from services.metrics_service import MetricsService
from services.notification_service import NotificationService

# Configure structured logging
configure_logging()
logger = get_logger("main")


@asynccontextmanager
async def lifespan(app: FastAPI):
    """Application lifespan manager."""
    # Startup
    logger.info(
        "Starting Enterprise Document Extraction API",
        version=settings.app_version,
        environment=settings.environment,
        debug=settings.debug
    )

    # Initialize dependency container
    container = get_container()

    try:
        # Initialize Redis connection
        redis_client = await container.get_redis_client()

        # Initialize services
        cache_service = CacheService(redis_client)
        metrics_service = MetricsService()
        notification_service = NotificationService()

        # Register services in container
        container.register_service("cache", cache_service)
        container.register_service("metrics", metrics_service)
        container.register_service("notifications", notification_service)

        # Initialize rate limiter
        rate_limiter = create_rate_limiter(redis_client)
        container.register_service("rate_limiter", rate_limiter)

        logger.info("Application services initialized successfully")

        # Start background tasks
        if settings.enable_metrics:
            # Update system metrics periodically (would use proper scheduler in production)
            pass

        yield

    except Exception as e:
        logger.error("Failed to initialize application services", error=str(e))
        raise

    finally:
        # Shutdown
        logger.info("Shutting down Enterprise Document Extraction API")

        try:
            # Close Redis connection
            await container.close_redis_client()

            # Process any pending notifications
            if container.get_service("notifications"):
                await container.get_service("notifications").process_pending_notifications()

            logger.info("Application shutdown completed successfully")

        except Exception as e:
            logger.error("Error during application shutdown", error=str(e))


# Create FastAPI application
app = FastAPI(
    title=settings.app_name,
    version=settings.app_version,
    description="""
Enterprise-grade document extraction API with advanced features:

## Features
* **Multi-format support**: PDF, Office documents, text files, web documents
* **High performance**: Async processing with configurable concurrency
* **Enterprise security**: API key authentication, rate limiting, input validation
* **Scalability**: Redis caching, connection pooling, horizontal scaling ready
* **Observability**: Structured logging, metrics, health checks, request tracing
* **Reliability**: Error handling, retries, webhook notifications

## Supported Formats
* **PDF**: Text extraction from non-scanned PDFs
* **Office**: DOCX, XLSX, PPTX documents
* **Text**: TXT, CSV, RTF files
* **Web**: HTML, XML documents

## Authentication
All endpoints (except health checks) require API key authentication:
- Header: `Authorization: Bearer YOUR_API_KEY`
- Header: `X-API-Key: YOUR_API_KEY`

## Rate Limits
- 100 requests per minute per API key
- Burst limit: 20 requests
- Rate limit headers included in responses

## Caching
- Automatic caching of extraction results
- Redis backend with in-memory fallback
- Configurable TTL and cache strategies
    """,
    docs_url=settings.docs_url,
    redoc_url=settings.redoc_url,
    openapi_url=settings.openapi_url,
    lifespan=lifespan
)

# Add security headers middleware
app.add_middleware(SecurityHeaders)

# Add CORS middleware
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"] if settings.debug else [],
    allow_credentials=True,
    allow_methods=["GET", "POST", "PUT", "DELETE", "OPTIONS"],
    allow_headers=["*"],
    expose_headers=["x-request-id", "x-ratelimit-limit", "x-ratelimit-remaining", "x-ratelimit-reset"]
)

# Add request ID middleware
app.add_middleware(RequestIDMiddleware)

# Add authentication middleware
app.add_middleware(AuthMiddleware)

# Add rate limiting middleware
@app.on_event("startup")
async def setup_rate_limiting():
    """Setup rate limiting after startup."""
    container = get_container()
    rate_limiter = container.get_service("rate_limiter")
    if rate_limiter:
        app.add_middleware(RateLimitMiddleware, rate_limiter)


# Global exception handlers
@app.exception_handler(RequestValidationError)
async def validation_exception_handler(request: Request, exc: RequestValidationError):
    """Handle Pydantic validation errors."""
    logger.warning(
        "Request validation failed",
        path=request.url.path,
        method=request.method,
        errors=exc.errors()
    )

    return JSONResponse(
        status_code=422,
        content={
            "error": {
                "code": "VALIDATION_ERROR",
                "message": "Request validation failed",
                "details": exc.errors()
            },
            "success": False,
            "timestamp": time.time()
        }
    )


@app.exception_handler(RateLimitExceeded)
async def rate_limit_handler(request: Request, exc: RateLimitExceeded):
    """Handle rate limit exceeded errors."""
    logger.warning(
        "Rate limit exceeded",
        path=request.url.path,
        method=request.method,
        client=request.client.host if request.client else "unknown"
    )

    return JSONResponse(
        status_code=429,
        content={
            "error": {
                "code": "RATE_LIMIT_EXCEEDED",
                "message": str(exc.detail),
                "details": {
                    "retry_after": 60,
                    "hint": "Reduce request frequency or upgrade your plan"
                }
            },
            "success": False,
            "timestamp": time.time()
        },
        headers={"Retry-After": "60"}
    )


@app.exception_handler(500)
async def internal_server_error_handler(request: Request, exc: HTTPException):
    """Handle internal server errors."""
    logger.error(
        "Internal server error",
        path=request.url.path,
        method=request.method,
        error=str(exc.detail) if hasattr(exc, 'detail') else str(exc)
    )

    return JSONResponse(
        status_code=500,
        content={
            "error": {
                "code": "INTERNAL_ERROR",
                "message": "Internal server error occurred",
                "details": {
                    "hint": "Please try again later or contact support if the issue persists"
                }
            },
            "success": False,
            "timestamp": time.time()
        }
    )


# Metrics endpoint
@app.get("/metrics", response_class=PlainTextResponse)
async def metrics_endpoint():
    """
    Prometheus-compatible metrics endpoint.

    Returns metrics in Prometheus exposition format.
    """
    try:
        container = get_container()
        metrics_service = container.get_service("metrics")

        if not metrics_service:
            return PlainTextResponse("# Metrics service not available\n", status_code=503)

        metrics_data = metrics_service.get_prometheus_metrics()
        return PlainTextResponse(metrics_data, media_type="text/plain")

    except Exception as e:
        logger.error("Failed to generate metrics", error=str(e))
        return PlainTextResponse(f"# Error generating metrics: {str(e)}\n", status_code=500)


# Request logging middleware
@app.middleware("http")
async def log_requests(request: Request, call_next):
    """Log HTTP requests and responses."""
    start_time = time.time()

    # Skip logging for health checks and metrics
    if request.url.path in ["/health", "/metrics", "/health/simple"]:
        response = await call_next(request)
        return response

    # Get client info
    client_ip = request.client.host if request.client else "unknown"
    user_agent = request.headers.get("user-agent", "unknown")

    # Log request start
    logger.info(
        "Request started",
        method=request.method,
        path=request.url.path,
        client_ip=client_ip,
        user_agent=user_agent
    )

    try:
        # Process request
        response = await call_next(request)

        # Calculate duration
        duration = time.time() - start_time

        # Record metrics
        container = get_container()
        metrics_service = container.get_service("metrics")
        if metrics_service:
            metrics_service.record_request_metrics(
                request.method,
                request.url.path,
                response.status_code,
                duration
            )

        # Log response
        logger.info(
            "Request completed",
            method=request.method,
            path=request.url.path,
            status_code=response.status_code,
            duration=round(duration, 3),
            client_ip=client_ip
        )

        return response

    except Exception as e:
        duration = time.time() - start_time

        logger.error(
            "Request failed",
            method=request.method,
            path=request.url.path,
            duration=round(duration, 3),
            error=str(e),
            client_ip=client_ip
        )

        # Record error metrics
        container = get_container()
        metrics_service = container.get_service("metrics")
        if metrics_service:
            metrics_service.record_request_metrics(
                request.method,
                request.url.path,
                500,
                duration
            )

        raise


# Include routers
app.include_router(health.router)  # No prefix, for /health
app.include_router(extraction.router, prefix=settings.api_prefix)
app.include_router(batch.router, prefix=settings.api_prefix)
app.include_router(admin.router, prefix=settings.api_prefix)


# Root endpoint
@app.get("/")
async def root():
    """API root endpoint with basic information."""
    return {
        "name": settings.app_name,
        "version": settings.app_version,
        "environment": settings.environment,
        "status": "operational",
        "documentation": f"{settings.docs_url}",
        "health_check": "/health",
        "metrics": "/metrics"
    }


if __name__ == "__main__":
    # Development server
    uvicorn.run(
        "api.main:app",
        host="0.0.0.0",
        port=settings.api_port,
        reload=settings.debug,
        workers=1 if settings.debug else settings.worker_processes,
        log_config=None,  # Use our custom logging
        access_log=False   # Handle logging in middleware
    )