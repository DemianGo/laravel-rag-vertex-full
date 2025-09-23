"""
Dependency injection container for enterprise services.
"""

from functools import lru_cache
from typing import Optional
import redis.asyncio as redis
from .config import settings
from .logging import get_logger

logger = get_logger("dependencies")


class DependencyContainer:
    """Container for managing application dependencies."""

    def __init__(self):
        self._redis_client: Optional[redis.Redis] = None
        self._services = {}

    async def get_redis_client(self) -> Optional[redis.Redis]:
        """Get Redis client instance."""
        if self._redis_client is None:
            try:
                self._redis_client = redis.from_url(
                    settings.redis_url,
                    password=settings.redis_password,
                    ssl=settings.redis_ssl,
                    encoding="utf-8",
                    decode_responses=True,
                    health_check_interval=30
                )
                # Test connection
                await self._redis_client.ping()
                logger.info("Redis connection established")
            except Exception as e:
                logger.warning("Redis connection failed, using in-memory cache", error=str(e))
                self._redis_client = None

        return self._redis_client

    async def close_redis_client(self):
        """Close Redis connection."""
        if self._redis_client:
            await self._redis_client.close()
            self._redis_client = None
            logger.info("Redis connection closed")

    def register_service(self, name: str, service):
        """Register a service in the container."""
        self._services[name] = service
        logger.debug("Service registered", service_name=name)

    def get_service(self, name: str):
        """Get a registered service."""
        return self._services.get(name)


# Global container instance
@lru_cache()
def get_container() -> DependencyContainer:
    """Get global dependency container instance."""
    return DependencyContainer()


# Convenience dependency functions for FastAPI
async def get_redis() -> Optional[redis.Redis]:
    """FastAPI dependency to get Redis client."""
    container = get_container()
    return await container.get_redis_client()


def get_service(service_name: str):
    """FastAPI dependency to get a service."""
    def _get_service():
        container = get_container()
        service = container.get_service(service_name)
        if service is None:
            logger.error("Service not found", service_name=service_name)
            raise RuntimeError(f"Service {service_name} not registered")
        return service
    return _get_service