"""
Authentication middleware for API key validation.
"""

from fastapi import Request, HTTPException, status
from typing import Optional, Tuple

from core.config import settings
from core.logging import get_logger
from core.security import api_key_auth

logger = get_logger("auth_middleware")


class AuthMiddleware:
    """Authentication middleware for API key validation."""

    def __init__(self, app):
        self.app = app
        self.excluded_paths = {
            "/health",
            "/health/simple",
            "/metrics",
            "/docs",
            "/redoc",
            "/openapi.json",
            "/favicon.ico",
            "/api/rag/python-health",  # RAG health check doesn't need auth
            "/auth/register",  # Public registration endpoint
            "/auth/login"  # Public login endpoint
        }

    async def __call__(self, scope, receive, send):
        if scope["type"] != "http":
            await self.app(scope, receive, send)
            return

        request = Request(scope, receive)

        # Skip authentication for OPTIONS requests (CORS preflight)
        if request.method == "OPTIONS":
            await self.app(scope, receive, send)
            return

        # Skip authentication for excluded paths
        if request.url.path in self.excluded_paths:
            await self.app(scope, receive, send)
            return

        # Extract and validate API key
        api_key, error_response = self._extract_and_validate_api_key(request)

        if error_response:
            await self._send_error_response(send, error_response)
            return

        # Add API key to request state
        scope["state"] = getattr(scope, "state", {})
        scope["state"]["api_key"] = api_key
        scope["state"]["api_key_info"] = api_key_auth.get_key_info(api_key)

        # Log authentication
        logger.debug(
            "Request authenticated",
            api_key_prefix=api_key[:10] + "..." if len(api_key) > 10 else api_key,
            path=request.url.path,
            method=request.method
        )

        await self.app(scope, receive, send)

    def _extract_and_validate_api_key(self, request: Request) -> Tuple[Optional[str], Optional[dict]]:
        """Extract and validate API key from request."""

        # Try Authorization header first (Bearer token)
        auth_header = request.headers.get("authorization", "")
        api_key = None

        if auth_header.startswith("Bearer "):
            api_key = auth_header[7:]
        elif auth_header:
            # Try direct API key in Authorization header
            api_key = auth_header

        # Try X-API-Key header
        if not api_key:
            api_key = request.headers.get("x-api-key")

        # Try query parameter (less secure, mainly for development)
        if not api_key and settings.environment == "development":
            api_key = request.query_params.get("api_key")

        # Validate API key presence
        if not api_key:
            return None, {
                "error": {
                    "code": "MISSING_API_KEY",
                    "message": "API key required. Provide it in Authorization header (Bearer token) or X-API-Key header.",
                    "details": {
                        "supported_headers": ["Authorization: Bearer <api_key>", "X-API-Key: <api_key>"],
                        "example": "Authorization: Bearer your-api-key-here"
                    }
                }
            }

        # Validate API key
        if not api_key_auth.is_valid_key(api_key):
            logger.warning(
                "Invalid API key attempted",
                api_key_prefix=api_key[:10] + "..." if len(api_key) > 10 else api_key,
                client_ip=request.client.host if request.client else "unknown",
                path=request.url.path
            )

            return None, {
                "error": {
                    "code": "INVALID_API_KEY",
                    "message": "Invalid API key provided.",
                    "details": {
                        "hint": "Check your API key and try again. Contact support if the issue persists."
                    }
                }
            }

        return api_key, None

    async def _send_error_response(self, send, error_response: dict):
        """Send authentication error response."""
        import json

        await send({
            "type": "http.response.start",
            "status": 401,
            "headers": [
                (b"content-type", b"application/json"),
                (b"www-authenticate", b"Bearer"),
                (b"x-error-code", error_response["error"]["code"].encode()),
            ]
        })

        body = json.dumps(error_response, indent=2).encode()
        await send({
            "type": "http.response.body",
            "body": body
        })


class OptionalAuthMiddleware:
    """Optional authentication middleware that doesn't require API key."""

    def __init__(self, app):
        self.app = app

    async def __call__(self, scope, receive, send):
        if scope["type"] != "http":
            await self.app(scope, receive, send)
            return

        request = Request(scope, receive)

        # Try to extract API key if present
        auth_header = request.headers.get("authorization", "")
        api_key = None

        if auth_header.startswith("Bearer "):
            api_key = auth_header[7:]
        elif request.headers.get("x-api-key"):
            api_key = request.headers.get("x-api-key")

        # Add to request state if valid
        scope["state"] = getattr(scope, "state", {})
        if api_key and api_key_auth.is_valid_key(api_key):
            scope["state"]["api_key"] = api_key
            scope["state"]["api_key_info"] = api_key_auth.get_key_info(api_key)
            scope["state"]["authenticated"] = True
        else:
            scope["state"]["api_key"] = None
            scope["state"]["api_key_info"] = {"valid": False}
            scope["state"]["authenticated"] = False

        await self.app(scope, receive, send)


def get_api_key_from_request(request: Request) -> Optional[str]:
    """Extract API key from request state."""
    return getattr(request.state, "api_key", None)


def get_api_key_info_from_request(request: Request) -> dict:
    """Extract API key info from request state."""
    return getattr(request.state, "api_key_info", {"valid": False})


def is_authenticated(request: Request) -> bool:
    """Check if request is authenticated."""
    return getattr(request.state, "authenticated", False)