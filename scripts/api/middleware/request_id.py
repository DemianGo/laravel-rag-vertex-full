"""
Request ID middleware for tracking requests across the system.
"""

import uuid
from fastapi import Request

from core.logging import get_logger

logger = get_logger("request_id")


class RequestIDMiddleware:
    """Middleware to add unique request IDs for tracing."""

    def __init__(self, app):
        self.app = app

    async def __call__(self, scope, receive, send):
        if scope["type"] != "http":
            await self.app(scope, receive, send)
            return

        # Generate or extract request ID
        request_id = self._get_or_generate_request_id(scope)

        # Add to scope state
        scope["state"] = getattr(scope, "state", {})
        scope["state"]["request_id"] = request_id

        # Add to logging context
        import structlog
        structlog.contextvars.bind_contextvars(request_id=request_id)

        async def send_with_request_id(message):
            """Add request ID to response headers."""
            if message["type"] == "http.response.start":
                headers = list(message.get("headers", []))
                headers.append((b"x-request-id", request_id.encode()))
                message["headers"] = headers

            await send(message)

        await self.app(scope, receive, send_with_request_id)

        # Clean up logging context
        try:
            structlog.contextvars.unbind_contextvars("request_id")
        except:
            pass

    def _get_or_generate_request_id(self, scope) -> str:
        """Get request ID from headers or generate new one."""
        headers = dict(scope.get("headers", []))

        # Try to get from headers
        for header_name in [b"x-request-id", b"x-correlation-id", b"x-trace-id"]:
            if header_name in headers:
                request_id = headers[header_name].decode()
                if request_id:
                    logger.debug("Using existing request ID", request_id=request_id)
                    return request_id

        # Generate new request ID
        request_id = self._generate_request_id()
        logger.debug("Generated new request ID", request_id=request_id)
        return request_id

    def _generate_request_id(self) -> str:
        """Generate a new unique request ID."""
        return f"req_{uuid.uuid4().hex[:16]}"


def get_request_id(request: Request) -> str:
    """Get request ID from request state."""
    return getattr(request.state, "request_id", "unknown")


def get_correlation_id(request: Request) -> str:
    """Alias for get_request_id for compatibility."""
    return get_request_id(request)