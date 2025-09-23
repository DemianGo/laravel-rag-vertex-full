"""
Security module with API key authentication and authorization.
"""

from fastapi import HTTPException, status, Depends, Request
from fastapi.security import HTTPBearer, HTTPAuthorizationCredentials
from typing import Optional
import hashlib
import hmac
from .config import settings
from .logging import get_logger

logger = get_logger("security")
security = HTTPBearer()


class APIKeyAuth:
    """API Key authentication handler."""

    def __init__(self):
        self.valid_keys = set(settings.api_keys)

    async def __call__(self, credentials: HTTPAuthorizationCredentials = Depends(security)) -> str:
        """Validate API key from Authorization header."""
        api_key = credentials.credentials

        if not self.is_valid_key(api_key):
            logger.warning("Invalid API key attempted", api_key_prefix=api_key[:10])
            raise HTTPException(
                status_code=status.HTTP_401_UNAUTHORIZED,
                detail="Invalid API key",
                headers={"WWW-Authenticate": "Bearer"},
            )

        logger.debug("API key authenticated", api_key_prefix=api_key[:10])
        return api_key

    def is_valid_key(self, api_key: str) -> bool:
        """Check if API key is valid."""
        return api_key in self.valid_keys

    def get_key_info(self, api_key: str) -> dict:
        """Get information about an API key."""
        if not self.is_valid_key(api_key):
            return {"valid": False}

        # In a real implementation, this would fetch from database
        return {
            "valid": True,
            "key_id": hashlib.sha256(api_key.encode()).hexdigest()[:16],
            "permissions": ["read", "write"],  # Default permissions
        }


# Global instance
api_key_auth = APIKeyAuth()


def get_current_api_key(api_key: str = Depends(api_key_auth)) -> str:
    """Dependency to get current authenticated API key."""
    return api_key


def verify_signature(payload: bytes, signature: str, secret: str) -> bool:
    """Verify HMAC signature for webhooks."""
    expected_signature = hmac.new(
        secret.encode(),
        payload,
        hashlib.sha256
    ).hexdigest()
    return hmac.compare_digest(f"sha256={expected_signature}", signature)


class SecurityHeaders:
    """Middleware to add security headers."""

    def __init__(self, app):
        self.app = app

    async def __call__(self, scope, receive, send):
        if scope["type"] != "http":
            await self.app(scope, receive, send)
            return

        async def send_with_headers(message):
            if message["type"] == "http.response.start":
                headers = list(message.get("headers", []))

                # Add security headers
                security_headers = [
                    (b"x-content-type-options", b"nosniff"),
                    (b"x-frame-options", b"DENY"),
                    (b"x-xss-protection", b"1; mode=block"),
                    (b"strict-transport-security", b"max-age=31536000; includeSubDomains"),
                    (b"referrer-policy", b"strict-origin-when-cross-origin"),
                ]

                headers.extend(security_headers)
                message["headers"] = headers

            await send(message)

        await self.app(scope, receive, send_with_headers)