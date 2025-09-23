"""
Structured logging configuration for enterprise applications.
"""

import structlog
import logging
import sys
from typing import Any, Dict
from .config import settings


def configure_logging() -> None:
    """Configure structured logging for the application."""

    # Configure stdlib logging
    logging.basicConfig(
        format="%(message)s",
        stream=sys.stdout,
        level=getattr(logging, settings.log_level.upper()),
    )

    # Configure structlog
    structlog.configure(
        processors=[
            structlog.contextvars.merge_contextvars,
            structlog.processors.add_log_level,
            structlog.processors.StackInfoRenderer(),
            structlog.dev.set_exc_info,
            structlog.processors.TimeStamper(fmt="iso"),
            structlog.processors.JSONRenderer() if settings.log_format == "json"
            else structlog.dev.ConsoleRenderer(colors=True)
        ],
        wrapper_class=structlog.make_filtering_bound_logger(
            getattr(logging, settings.log_level.upper())
        ),
        logger_factory=structlog.WriteLoggerFactory(),
        cache_logger_on_first_use=True,
    )


def get_logger(name: str = None) -> structlog.BoundLogger:
    """Get a structured logger instance."""
    return structlog.get_logger(name or __name__)


class RequestLoggingMiddleware:
    """Middleware for logging HTTP requests with correlation IDs."""

    def __init__(self, app):
        self.app = app
        self.logger = get_logger("request")

    async def __call__(self, scope, receive, send):
        if scope["type"] != "http":
            await self.app(scope, receive, send)
            return

        # Extract request info
        request_id = None
        api_key = None

        # Get headers
        headers = dict(scope.get("headers", []))
        for name, value in headers.items():
            name_str = name.decode()
            if name_str.lower() == "x-request-id":
                request_id = value.decode()
            elif name_str.lower() == "x-api-key":
                api_key = value.decode()[:10] + "..." if len(value.decode()) > 10 else value.decode()

        # Log request start
        self.logger.info(
            "Request started",
            method=scope["method"],
            path=scope["path"],
            request_id=request_id,
            api_key=api_key,
            client_host=scope.get("client", ["unknown"])[0]
        )

        # Process request
        await self.app(scope, receive, send)