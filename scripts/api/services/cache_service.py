"""
Intelligent caching service with Redis backend and in-memory fallback.
"""

import json
import hashlib
from typing import Optional, Any, Dict, List
from datetime import datetime, timedelta
import redis.asyncio as redis

from core.config import settings
from core.logging import get_logger
from models.enums import CacheStrategy

logger = get_logger("cache_service")


class CacheService:
    """Intelligent caching service with multiple backends."""

    def __init__(self, redis_client: Optional[redis.Redis] = None):
        self.redis_client = redis_client
        self._in_memory_cache: Dict[str, Dict[str, Any]] = {}
        self._cache_stats = {
            "hits": 0,
            "misses": 0,
            "sets": 0,
            "deletes": 0,
            "errors": 0
        }

    async def get(self, key: str) -> Optional[Any]:
        """Get value from cache."""
        try:
            # Try Redis first if available
            if self.redis_client:
                try:
                    value = await self.redis_client.get(key)
                    if value:
                        self._cache_stats["hits"] += 1
                        logger.debug("Cache hit (Redis)", key=key)
                        return json.loads(value)
                except Exception as e:
                    logger.warning("Redis get failed, trying in-memory", error=str(e))

            # Fall back to in-memory cache
            cache_entry = self._in_memory_cache.get(key)
            if cache_entry:
                # Check expiration
                if cache_entry["expires_at"] > datetime.utcnow():
                    self._cache_stats["hits"] += 1
                    logger.debug("Cache hit (in-memory)", key=key)
                    return cache_entry["value"]
                else:
                    # Expired, remove it
                    del self._in_memory_cache[key]

            self._cache_stats["misses"] += 1
            logger.debug("Cache miss", key=key)
            return None

        except Exception as e:
            self._cache_stats["errors"] += 1
            logger.error("Cache get error", key=key, error=str(e))
            return None

    async def set(
        self,
        key: str,
        value: Any,
        ttl: Optional[int] = None,
        strategy: CacheStrategy = CacheStrategy.SHORT_TERM
    ) -> bool:
        """Set value in cache."""
        try:
            # Determine TTL based on strategy
            if ttl is None:
                ttl = self._get_ttl_for_strategy(strategy)

            serialized_value = json.dumps(value, default=str)

            # Try Redis first if available
            if self.redis_client:
                try:
                    await self.redis_client.setex(key, ttl, serialized_value)
                    self._cache_stats["sets"] += 1
                    logger.debug("Cache set (Redis)", key=key, ttl=ttl)
                    return True
                except Exception as e:
                    logger.warning("Redis set failed, using in-memory", error=str(e))

            # Fall back to in-memory cache
            self._in_memory_cache[key] = {
                "value": value,
                "expires_at": datetime.utcnow() + timedelta(seconds=ttl),
                "created_at": datetime.utcnow()
            }

            # Clean up expired entries periodically
            if len(self._in_memory_cache) % 100 == 0:
                await self._cleanup_expired_memory_cache()

            self._cache_stats["sets"] += 1
            logger.debug("Cache set (in-memory)", key=key, ttl=ttl)
            return True

        except Exception as e:
            self._cache_stats["errors"] += 1
            logger.error("Cache set error", key=key, error=str(e))
            return False

    async def delete(self, key: str) -> bool:
        """Delete value from cache."""
        try:
            deleted = False

            # Try Redis first if available
            if self.redis_client:
                try:
                    result = await self.redis_client.delete(key)
                    deleted = result > 0
                except Exception as e:
                    logger.warning("Redis delete failed", error=str(e))

            # Also delete from in-memory cache
            if key in self._in_memory_cache:
                del self._in_memory_cache[key]
                deleted = True

            if deleted:
                self._cache_stats["deletes"] += 1
                logger.debug("Cache delete", key=key)

            return deleted

        except Exception as e:
            self._cache_stats["errors"] += 1
            logger.error("Cache delete error", key=key, error=str(e))
            return False

    async def clear_pattern(self, pattern: str = "*") -> int:
        """Clear cache entries matching pattern."""
        try:
            cleared_count = 0

            # Clear from Redis if available
            if self.redis_client:
                try:
                    keys = await self.redis_client.keys(pattern)
                    if keys:
                        cleared_count += await self.redis_client.delete(*keys)
                except Exception as e:
                    logger.warning("Redis pattern clear failed", error=str(e))

            # Clear from in-memory cache
            if pattern == "*":
                cleared_count += len(self._in_memory_cache)
                self._in_memory_cache.clear()
            else:
                # Simple pattern matching for in-memory cache
                import fnmatch
                keys_to_delete = [
                    key for key in self._in_memory_cache.keys()
                    if fnmatch.fnmatch(key, pattern)
                ]
                for key in keys_to_delete:
                    del self._in_memory_cache[key]
                cleared_count += len(keys_to_delete)

            logger.info("Cache cleared", pattern=pattern, count=cleared_count)
            return cleared_count

        except Exception as e:
            self._cache_stats["errors"] += 1
            logger.error("Cache clear error", pattern=pattern, error=str(e))
            return 0

    def generate_cache_key(self, prefix: str, *args) -> str:
        """Generate a cache key from prefix and arguments."""
        # Create a hash of the arguments for consistent key generation
        key_data = f"{prefix}:" + ":".join(str(arg) for arg in args)
        return hashlib.md5(key_data.encode()).hexdigest()

    def get_stats(self) -> Dict[str, Any]:
        """Get cache statistics."""
        total_operations = sum(self._cache_stats.values())
        hit_rate = (
            self._cache_stats["hits"] / max(self._cache_stats["hits"] + self._cache_stats["misses"], 1)
        ) * 100

        return {
            **self._cache_stats,
            "hit_rate_percentage": round(hit_rate, 2),
            "in_memory_entries": len(self._in_memory_cache),
            "redis_available": self.redis_client is not None,
            "total_operations": total_operations
        }

    async def health_check(self) -> Dict[str, Any]:
        """Perform health check on cache services."""
        health = {
            "in_memory": {"status": "healthy", "entries": len(self._in_memory_cache)},
            "redis": {"status": "unavailable", "connected": False}
        }

        if self.redis_client:
            try:
                await self.redis_client.ping()
                health["redis"] = {
                    "status": "healthy",
                    "connected": True,
                    "info": await self.redis_client.info("memory")
                }
            except Exception as e:
                health["redis"] = {
                    "status": "unhealthy",
                    "connected": False,
                    "error": str(e)
                }

        return health

    def _get_ttl_for_strategy(self, strategy: CacheStrategy) -> int:
        """Get TTL in seconds for caching strategy."""
        ttl_map = {
            CacheStrategy.NO_CACHE: 0,
            CacheStrategy.SHORT_TERM: 300,      # 5 minutes
            CacheStrategy.LONG_TERM: 3600,      # 1 hour
            CacheStrategy.PERSISTENT: 86400,    # 24 hours
        }
        return ttl_map.get(strategy, settings.cache_ttl)

    async def _cleanup_expired_memory_cache(self):
        """Clean up expired entries from in-memory cache."""
        now = datetime.utcnow()
        expired_keys = [
            key for key, entry in self._in_memory_cache.items()
            if entry["expires_at"] <= now
        ]

        for key in expired_keys:
            del self._in_memory_cache[key]

        if expired_keys:
            logger.debug("Cleaned up expired cache entries", count=len(expired_keys))