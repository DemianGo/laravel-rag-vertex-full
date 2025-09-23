"""
Advanced rate limiting middleware with Redis backend.
"""

import time
from typing import Dict, Optional
from fastapi import Request, HTTPException, status
from slowapi import Limiter, _rate_limit_exceeded_handler
from slowapi.util import get_remote_address
from slowapi.errors import RateLimitExceeded
import redis.asyncio as redis

from core.config import settings
from core.logging import get_logger

logger = get_logger("rate_limiting")


class EnhancedRateLimiter:
    """Enhanced rate limiter with per-API-key limits and Redis backend."""

    def __init__(self, redis_client: Optional[redis.Redis] = None):
        self.redis_client = redis_client
        self.fallback_cache: Dict[str, Dict[str, float]] = {}
        self.default_limits = {
            "requests_per_minute": settings.rate_limit_requests,
            "burst_limit": settings.rate_limit_burst
        }

    async def is_allowed(
        self,
        identifier: str,
        requests_per_minute: int = None,
        burst_limit: int = None
    ) -> tuple[bool, Dict[str, int]]:
        """
        Check if request is allowed based on rate limits.
        Returns (allowed, info_dict)
        """
        if requests_per_minute is None:
            requests_per_minute = self.default_limits["requests_per_minute"]
        if burst_limit is None:
            burst_limit = self.default_limits["burst_limit"]

        current_time = time.time()
        window_start = int(current_time / 60) * 60  # Start of current minute

        # Try Redis first
        if self.redis_client:
            try:
                return await self._check_redis_limits(
                    identifier, window_start, requests_per_minute, burst_limit
                )
            except Exception as e:
                logger.warning("Redis rate limiting failed, using fallback", error=str(e))

        # Fallback to in-memory
        return self._check_memory_limits(
            identifier, current_time, requests_per_minute, burst_limit
        )

    async def _check_redis_limits(
        self,
        identifier: str,
        window_start: int,
        requests_per_minute: int,
        burst_limit: int
    ) -> tuple[bool, Dict[str, int]]:
        """Check rate limits using Redis."""

        pipe = self.redis_client.pipeline()

        # Keys for current and next minute windows
        current_key = f"rate_limit:{identifier}:{window_start}"
        burst_key = f"rate_limit_burst:{identifier}"

        # Get current counts
        pipe.get(current_key)
        pipe.get(burst_key)
        results = await pipe.execute()

        current_requests = int(results[0] or 0)
        burst_requests = int(results[1] or 0)

        # Check limits
        if current_requests >= requests_per_minute:
            logger.warning(
                "Rate limit exceeded (per minute)",
                identifier=identifier,
                current=current_requests,
                limit=requests_per_minute
            )
            return False, {
                "requests": current_requests,
                "limit": requests_per_minute,
                "reset_time": window_start + 60,
                "burst_requests": burst_requests,
                "burst_limit": burst_limit
            }

        if burst_requests >= burst_limit:
            logger.warning(
                "Burst limit exceeded",
                identifier=identifier,
                current=burst_requests,
                limit=burst_limit
            )
            return False, {
                "requests": current_requests,
                "limit": requests_per_minute,
                "reset_time": window_start + 60,
                "burst_requests": burst_requests,
                "burst_limit": burst_limit
            }

        # Increment counters
        pipe = self.redis_client.pipeline()
        pipe.incr(current_key)
        pipe.expire(current_key, 120)  # Keep for 2 minutes
        pipe.incr(burst_key)
        pipe.expire(burst_key, 60)  # Reset burst counter every minute
        await pipe.execute()

        return True, {
            "requests": current_requests + 1,
            "limit": requests_per_minute,
            "reset_time": window_start + 60,
            "burst_requests": burst_requests + 1,
            "burst_limit": burst_limit
        }

    def _check_memory_limits(
        self,
        identifier: str,
        current_time: float,
        requests_per_minute: int,
        burst_limit: int
    ) -> tuple[bool, Dict[str, int]]:
        """Check rate limits using in-memory cache."""

        if identifier not in self.fallback_cache:
            self.fallback_cache[identifier] = {
                "requests": 0,
                "window_start": int(current_time / 60) * 60,
                "burst_requests": 0,
                "last_request": current_time
            }

        cache_entry = self.fallback_cache[identifier]
        window_start = int(current_time / 60) * 60

        # Reset counters if we're in a new minute window
        if cache_entry["window_start"] != window_start:
            cache_entry["requests"] = 0
            cache_entry["window_start"] = window_start
            cache_entry["burst_requests"] = 0

        # Reset burst counter if more than a minute has passed
        if current_time - cache_entry["last_request"] > 60:
            cache_entry["burst_requests"] = 0

        # Check limits
        if cache_entry["requests"] >= requests_per_minute:
            return False, {
                "requests": cache_entry["requests"],
                "limit": requests_per_minute,
                "reset_time": window_start + 60,
                "burst_requests": cache_entry["burst_requests"],
                "burst_limit": burst_limit
            }

        if cache_entry["burst_requests"] >= burst_limit:
            return False, {
                "requests": cache_entry["requests"],
                "limit": requests_per_minute,
                "reset_time": window_start + 60,
                "burst_requests": cache_entry["burst_requests"],
                "burst_limit": burst_limit
            }

        # Increment counters
        cache_entry["requests"] += 1
        cache_entry["burst_requests"] += 1
        cache_entry["last_request"] = current_time

        return True, {
            "requests": cache_entry["requests"],
            "limit": requests_per_minute,
            "reset_time": window_start + 60,
            "burst_requests": cache_entry["burst_requests"],
            "burst_limit": burst_limit
        }


class RateLimitMiddleware:
    """Rate limiting middleware for FastAPI."""

    def __init__(self, app, rate_limiter: EnhancedRateLimiter):
        self.app = app
        self.rate_limiter = rate_limiter

    async def __call__(self, scope, receive, send):
        if scope["type"] != "http":
            await self.app(scope, receive, send)
            return

        request = Request(scope, receive)

        # Skip rate limiting for health checks and metrics
        if request.url.path in ["/health", "/metrics"]:
            await self.app(scope, receive, send)
            return

        # Get identifier (API key or IP address)
        identifier = self._get_identifier(request)

        # Check rate limits
        allowed, info = await self.rate_limiter.is_allowed(identifier)

        if not allowed:
            # Send rate limit exceeded response
            response = {
                "error": {
                    "code": "RATE_LIMIT_EXCEEDED",
                    "message": "Rate limit exceeded",
                    "details": info
                }
            }

            await send({
                "type": "http.response.start",
                "status": 429,
                "headers": [
                    (b"content-type", b"application/json"),
                    (b"x-ratelimit-limit", str(info["limit"]).encode()),
                    (b"x-ratelimit-remaining", str(max(0, info["limit"] - info["requests"])).encode()),
                    (b"x-ratelimit-reset", str(info["reset_time"]).encode()),
                    (b"retry-after", b"60"),
                ]
            })

            import json
            body = json.dumps(response).encode()
            await send({
                "type": "http.response.body",
                "body": body
            })
            return

        # Add rate limit headers to response
        async def send_with_headers(message):
            if message["type"] == "http.response.start":
                headers = list(message.get("headers", []))
                headers.extend([
                    (b"x-ratelimit-limit", str(info["limit"]).encode()),
                    (b"x-ratelimit-remaining", str(max(0, info["limit"] - info["requests"])).encode()),
                    (b"x-ratelimit-reset", str(info["reset_time"]).encode()),
                ])
                message["headers"] = headers

            await send(message)

        await self.app(scope, receive, send_with_headers)

    def _get_identifier(self, request: Request) -> str:
        """Get rate limiting identifier from request."""
        # Try to get API key first
        auth_header = request.headers.get("authorization", "")
        if auth_header.startswith("Bearer "):
            api_key = auth_header[7:]
            # Hash the API key for privacy
            import hashlib
            return f"api_key:{hashlib.sha256(api_key.encode()).hexdigest()[:16]}"

        # Fall back to IP address
        client_ip = request.client.host if request.client else "unknown"
        forwarded_for = request.headers.get("x-forwarded-for")
        if forwarded_for:
            client_ip = forwarded_for.split(",")[0].strip()

        return f"ip:{client_ip}"


# Create global rate limiter instance
def create_rate_limiter(redis_client: Optional[redis.Redis] = None) -> EnhancedRateLimiter:
    """Create rate limiter instance."""
    return EnhancedRateLimiter(redis_client)


# Slowapi limiter for simple rate limiting
def get_api_key_or_ip(request: Request) -> str:
    """Get API key or IP address for slowapi limiter."""
    auth_header = request.headers.get("authorization", "")
    if auth_header.startswith("Bearer "):
        return auth_header[7:][:16]  # Use first 16 chars of API key
    return get_remote_address(request)


# Create slowapi limiter
limiter = Limiter(key_func=get_api_key_or_ip)