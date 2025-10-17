<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Cache\CacheManager;
use Carbon\Carbon;
use Throwable;

/**
 * Strategic RAG Caching Service
 *
 * Provides intelligent, multi-layered caching for RAG operations with
 * compression, invalidation strategies, and performance optimization.
 * Designed for enterprise-scale applications with high throughput requirements.
 */
class RagCache
{
    private CacheManager $cache;
    private array $config;
    private array $metrics = [];
    private string $store;

    public function __construct(CacheManager $cache)
    {
        $this->cache = $cache;
        $this->config = config('rag.cache', []);
        $this->store = $this->config['store'] ?? 'rag_redis';
    }

    /**
     * Get cached value with intelligent retrieval
     *
     * @param string $key Cache key
     * @param mixed $default Default value if not found
     * @return mixed Cached value or default
     */
    public function get(string $key, $default = null)
    {
        if (!$this->isCacheEnabled()) {
            return $default;
        }

        $startTime = microtime(true);

        try {
            $fullKey = $this->buildKey($key);
            $value = $this->cache->store($this->store)->get($fullKey);

            if ($value !== null) {
                $value = $this->decompress($value);
                $this->recordMetric('hit', $key, microtime(true) - $startTime);
                return $value;
            }

            $this->recordMetric('miss', $key, microtime(true) - $startTime);
            return $default;

        } catch (Throwable $e) {
            Log::warning('Cache retrieval failed', [
                'key' => $key,
                'error' => $e->getMessage(),
                'store' => $this->store
            ]);

            $this->recordMetric('error', $key, microtime(true) - $startTime);
            return $default;
        }
    }

    /**
     * Store value in cache with intelligent compression and TTL
     *
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int|null $ttl Time to live in seconds
     * @return bool Success status
     */
    public function put(string $key, $value, ?int $ttl = null): bool
    {
        if (!$this->isCacheEnabled()) {
            return false;
        }

        $startTime = microtime(true);

        try {
            $fullKey = $this->buildKey($key);
            $ttl = $ttl ?? $this->getDefaultTtl($key);

            // Apply compression if enabled
            $compressedValue = $this->compress($value);

            $success = $this->cache->store($this->store)->put($fullKey, $compressedValue, $ttl);

            $this->recordMetric($success ? 'put' : 'put_failed', $key, microtime(true) - $startTime);

            return $success;

        } catch (Throwable $e) {
            Log::warning('Cache storage failed', [
                'key' => $key,
                'error' => $e->getMessage(),
                'store' => $this->store,
                'value_size' => strlen(serialize($value))
            ]);

            $this->recordMetric('error', $key, microtime(true) - $startTime);
            return false;
        }
    }

    /**
     * Remember pattern with callback
     *
     * @param string $key Cache key
     * @param callable $callback Function to generate value
     * @param int|null $ttl Time to live in seconds
     * @return mixed Cached or generated value
     */
    public function remember(string $key, callable $callback, ?int $ttl = null)
    {
        if (!$this->isCacheEnabled()) {
            return $callback();
        }

        $value = $this->get($key);

        if ($value !== null) {
            return $value;
        }

        $startTime = microtime(true);
        $value = $callback();
        $generationTime = (microtime(true) - $startTime) * 1000;

        // Only cache if generation took significant time or value is substantial
        if ($generationTime > 50 || $this->shouldCache($value)) {
            $this->put($key, $value, $ttl);
            $this->recordMetric('generated', $key, $generationTime / 1000);
        }

        return $value;
    }

    /**
     * Forget (delete) cached value
     *
     * @param string $key Cache key
     * @return bool Success status
     */
    public function forget(string $key): bool
    {
        if (!$this->isCacheEnabled()) {
            return true;
        }

        try {
            $fullKey = $this->buildKey($key);
            return $this->cache->store($this->store)->forget($fullKey);
        } catch (Throwable $e) {
            Log::warning('Cache deletion failed', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Flush cache by pattern
     *
     * @param string $pattern Pattern to match (supports wildcards)
     * @return int Number of keys deleted
     */
    public function flush(string $pattern = '*'): int
    {
        if (!$this->isCacheEnabled()) {
            return 0;
        }

        try {
            $fullPattern = $this->buildKey($pattern);

            if ($this->store === 'redis' || $this->store === 'rag_redis') {
                return $this->flushRedisPattern($fullPattern);
            }

            // For non-Redis stores, flush everything (less efficient)
            $this->cache->store($this->store)->flush();
            return 1;

        } catch (Throwable $e) {
            Log::error('Cache flush failed', [
                'pattern' => $pattern,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Get cache statistics
     *
     * @return array Cache performance metrics
     */
    public function getStatistics(): array
    {
        $stats = [
            'enabled' => $this->isCacheEnabled(),
            'store' => $this->store,
            'metrics' => $this->metrics,
            'config' => [
                'compression' => $this->config['compression']['enabled'] ?? false,
                'default_ttl' => $this->getDefaultTtl('default'),
            ]
        ];

        // Add Redis-specific stats if available
        if (($this->store === 'redis' || $this->store === 'rag_redis') && class_exists('Redis')) {
            try {
                $redisStats = $this->getRedisStats();
                $stats['redis'] = $redisStats;
            } catch (Throwable $e) {
                Log::debug('Could not retrieve Redis stats', ['error' => $e->getMessage()]);
            }
        }

        return $stats;
    }

    /**
     * Get hit rate for specific cache type
     *
     * @param string $type Cache type (e.g., 'embedding', 'search', 'generation')
     * @return float Hit rate percentage
     */
    public function getHitRate(string $type): float
    {
        $hits = $this->metrics[$type]['hits'] ?? 0;
        $misses = $this->metrics[$type]['misses'] ?? 0;
        $total = $hits + $misses;

        return $total > 0 ? round(($hits / $total) * 100, 2) : 0.0;
    }

    /**
     * Get average latency for cache operations
     *
     * @param string $type Cache type
     * @return float Average latency in milliseconds
     */
    public function getAverageLatency(string $type): float
    {
        $totalTime = $this->metrics[$type]['total_time'] ?? 0;
        $operations = $this->metrics[$type]['operations'] ?? 0;

        return $operations > 0 ? round(($totalTime / $operations) * 1000, 2) : 0.0;
    }

    /**
     * Generate cache key with proper scoping
     *
     * @param string $key Base key
     * @param array $context Additional context for key generation
     * @return string Scoped cache key
     */
    public function getCacheKey(string $key, array $context = []): string
    {
        $keyParts = [
            $this->config['prefix'] ?? 'rag',
            $key
        ];

        if (!empty($context)) {
            $keyParts[] = md5(serialize($context));
        }

        return implode(':', $keyParts);
    }

    /**
     * Intelligently invalidate related caches
     *
     * @param string $type Type of invalidation (document, embedding, etc.)
     * @param mixed $identifier Identifier for the invalidated entity
     * @return int Number of invalidated keys
     */
    public function invalidateRelated(string $type, $identifier): int
    {
        $patterns = $this->getInvalidationPatterns($type, $identifier);
        $totalInvalidated = 0;

        foreach ($patterns as $pattern) {
            $invalidated = $this->flush($pattern);
            $totalInvalidated += $invalidated;

            Log::info('Cache invalidation', [
                'type' => $type,
                'identifier' => $identifier,
                'pattern' => $pattern,
                'invalidated_count' => $invalidated
            ]);
        }

        return $totalInvalidated;
    }

    /**
     * Warm cache with precomputed values
     *
     * @param array $precomputedData Array of key-value pairs to cache
     * @param int|null $ttl Time to live for all entries
     * @return array Results of cache operations
     */
    public function warmCache(array $precomputedData, ?int $ttl = null): array
    {
        $results = [];

        foreach ($precomputedData as $key => $value) {
            $results[$key] = $this->put($key, $value, $ttl);
        }

        Log::info('Cache warming completed', [
            'entries' => count($precomputedData),
            'successful' => count(array_filter($results)),
            'ttl' => $ttl
        ]);

        return $results;
    }

    /**
     * Build full cache key
     */
    private function buildKey(string $key): string
    {
        $prefix = $this->config['prefix'] ?? 'rag';
        return "{$prefix}:{$key}";
    }

    /**
     * Compress value if compression is enabled
     */
    private function compress($value): string
    {
        $serialized = serialize($value);

        if (!$this->isCompressionEnabled()) {
            return $serialized;
        }

        $algorithm = $this->config['compression']['algorithm'] ?? 'gzip';
        $level = $this->config['compression']['level'] ?? 6;

        switch ($algorithm) {
            case 'gzip':
                return gzcompress($serialized, $level);
            case 'lz4':
                if (function_exists('lz4_compress')) {
                    return lz4_compress($serialized);
                }
                // Fallback to gzip
                return gzcompress($serialized, $level);
            case 'zstd':
                if (function_exists('zstd_compress')) {
                    return zstd_compress($serialized, $level);
                }
                // Fallback to gzip
                return gzcompress($serialized, $level);
            default:
                return $serialized;
        }
    }

    /**
     * Decompress value if needed
     */
    private function decompress(string $value)
    {
        if (!$this->isCompressionEnabled()) {
            return unserialize($value);
        }

        $algorithm = $this->config['compression']['algorithm'] ?? 'gzip';

        try {
            switch ($algorithm) {
                case 'gzip':
                    $decompressed = gzuncompress($value);
                    break;
                case 'lz4':
                    if (function_exists('lz4_uncompress')) {
                        $decompressed = lz4_uncompress($value);
                    } else {
                        $decompressed = gzuncompress($value);
                    }
                    break;
                case 'zstd':
                    if (function_exists('zstd_uncompress')) {
                        $decompressed = zstd_uncompress($value);
                    } else {
                        $decompressed = gzuncompress($value);
                    }
                    break;
                default:
                    $decompressed = $value;
            }

            return unserialize($decompressed);

        } catch (Throwable $e) {
            Log::warning('Cache decompression failed, trying raw unserialize', [
                'algorithm' => $algorithm,
                'error' => $e->getMessage()
            ]);

            // Fallback to raw unserialize
            return unserialize($value);
        }
    }

    /**
     * Get default TTL based on key type
     */
    private function getDefaultTtl(string $key): int
    {
        $ttlConfig = $this->config['ttl'] ?? [];

        // Match key patterns to TTL settings
        foreach ($ttlConfig as $pattern => $ttl) {
            if (str_contains($key, $pattern)) {
                return $ttl;
            }
        }

        // Default fallback TTL
        return 3600; // 1 hour
    }

    /**
     * Check if caching is enabled
     */
    private function isCacheEnabled(): bool
    {
        return $this->config['enabled'] ?? true;
    }

    /**
     * Check if compression is enabled
     */
    private function isCompressionEnabled(): bool
    {
        return $this->config['compression']['enabled'] ?? false;
    }

    /**
     * Determine if value should be cached
     */
    private function shouldCache($value): bool
    {
        if ($value === null || $value === false) {
            return false;
        }

        $serialized = serialize($value);
        $size = strlen($serialized);

        // Don't cache very small values (overhead not worth it)
        if ($size < 100) {
            return false;
        }

        // Don't cache extremely large values
        $maxSize = $this->config['max_value_size'] ?? 1024 * 1024; // 1MB default
        if ($size > $maxSize) {
            Log::warning('Value too large for caching', [
                'size' => $size,
                'max_size' => $maxSize
            ]);
            return false;
        }

        return true;
    }

    /**
     * Record cache operation metrics
     */
    private function recordMetric(string $operation, string $key, float $time): void
    {
        $type = $this->extractTypeFromKey($key);

        if (!isset($this->metrics[$type])) {
            $this->metrics[$type] = [
                'hits' => 0,
                'misses' => 0,
                'puts' => 0,
                'errors' => 0,
                'operations' => 0,
                'total_time' => 0,
            ];
        }

        switch ($operation) {
            case 'hit':
                $this->metrics[$type]['hits']++;
                break;
            case 'miss':
                $this->metrics[$type]['misses']++;
                break;
            case 'put':
                $this->metrics[$type]['puts']++;
                break;
            case 'error':
                $this->metrics[$type]['errors']++;
                break;
        }

        $this->metrics[$type]['operations']++;
        $this->metrics[$type]['total_time'] += $time;
    }

    /**
     * Extract cache type from key
     */
    private function extractTypeFromKey(string $key): string
    {
        if (str_contains($key, 'embedding')) return 'embedding';
        if (str_contains($key, 'search') || str_contains($key, 'query')) return 'search';
        if (str_contains($key, 'generation') || str_contains($key, 'answer')) return 'generation';
        if (str_contains($key, 'rerank')) return 'rerank';
        if (str_contains($key, 'document')) return 'document';

        return 'other';
    }

    /**
     * Get invalidation patterns for different types
     */
    private function getInvalidationPatterns(string $type, $identifier): array
    {
        switch ($type) {
            case 'document':
                return [
                    "embedding:doc:{$identifier}:*",
                    "search:*:doc:{$identifier}",
                    "generation:*:doc:{$identifier}",
                ];

            case 'embedding':
                return [
                    "embedding:{$identifier}:*",
                    "search:*:emb:{$identifier}",
                ];

            case 'user':
                return [
                    "user:{$identifier}:*",
                    "search:user:{$identifier}:*",
                ];

            default:
                return ["{$type}:{$identifier}:*"];
        }
    }

    /**
     * Flush Redis keys by pattern
     */
    private function flushRedisPattern(string $pattern): int
    {
        try {
            $redis = Redis::connection($this->store === 'rag_redis' ? 'rag' : 'default');
            $keys = $redis->keys($pattern);

            if (empty($keys)) {
                return 0;
            }

            $redis->del($keys);
            return count($keys);

        } catch (Throwable $e) {
            Log::warning('Redis pattern flush failed', [
                'pattern' => $pattern,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Get Redis-specific statistics
     */
    private function getRedisStats(): array
    {
        $redis = Redis::connection($this->store === 'rag_redis' ? 'rag' : 'default');
        $info = $redis->info();

        return [
            'connected_clients' => $info['connected_clients'] ?? 0,
            'used_memory' => $info['used_memory'] ?? 0,
            'used_memory_human' => $info['used_memory_human'] ?? '0B',
            'keyspace_hits' => $info['keyspace_hits'] ?? 0,
            'keyspace_misses' => $info['keyspace_misses'] ?? 0,
            'hit_rate' => $this->calculateRedisHitRate($info),
            'uptime_in_seconds' => $info['uptime_in_seconds'] ?? 0,
        ];
    }

    /**
     * Calculate Redis hit rate
     */
    private function calculateRedisHitRate(array $info): float
    {
        $hits = $info['keyspace_hits'] ?? 0;
        $misses = $info['keyspace_misses'] ?? 0;
        $total = $hits + $misses;

        return $total > 0 ? round(($hits / $total) * 100, 2) : 0.0;
    }

    /**
     * Perform cache maintenance
     */
    public function maintenance(): array
    {
        $results = [
            'expired_cleaned' => 0,
            'memory_optimized' => false,
            'stats_updated' => false,
        ];

        try {
            // Clean expired keys (Redis handles this automatically, but we can help)
            if ($this->store === 'redis' || $this->store === 'rag_redis') {
                $results['expired_cleaned'] = $this->cleanExpiredKeys();
            }

            // Update statistics
            $results['stats_updated'] = true;

            Log::info('Cache maintenance completed', $results);

        } catch (Throwable $e) {
            Log::error('Cache maintenance failed', [
                'error' => $e->getMessage(),
                'results' => $results
            ]);
        }

        return $results;
    }

    /**
     * Clean expired keys manually
     */
    private function cleanExpiredKeys(): int
    {
        // This is mostly handled by Redis automatically
        // but we can implement custom logic if needed
        return 0;
    }

    /**
     * Get cache statistics
     */
    public function getStats(): array
    {
        try {
            return [
                'enabled' => $this->isCacheEnabled(),
                'store' => $this->store,
                'metrics' => $this->metrics,
                'hit_rate' => $this->calculateHitRate(),
                'memory_usage' => $this->getMemoryUsage(),
                'key_count' => $this->getKeyCount()
            ];
        } catch (Throwable $e) {
            Log::error('Failed to get cache stats', ['error' => $e->getMessage()]);
            return [
                'enabled' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get memory usage
     */
    private function getMemoryUsage(): array
    {
        try {
            if ($this->store === 'rag_redis') {
                $redis = Redis::connection('rag');
                $info = $redis->info('memory');
                return [
                    'used_memory' => $info['used_memory'] ?? 0,
                    'used_memory_human' => $info['used_memory_human'] ?? '0B'
                ];
            }
            return ['used_memory' => 0, 'used_memory_human' => '0B'];
        } catch (Throwable $e) {
            return ['used_memory' => 0, 'used_memory_human' => '0B'];
        }
    }

    /**
     * Get key count
     */
    private function getKeyCount(): int
    {
        try {
            if ($this->store === 'rag_redis') {
                $redis = Redis::connection('rag');
                $keys = $redis->keys('rag_cache:*');
                return is_array($keys) ? count($keys) : 0;
            }
            return 0;
        } catch (Throwable $e) {
            return 0;
        }
    }

    /**
     * Calculate hit rate from metrics
     */
    private function calculateHitRate(): float
    {
        try {
            $totalHits = 0;
            $totalMisses = 0;
            
            foreach ($this->metrics as $type => $data) {
                $totalHits += $data['hits'] ?? 0;
                $totalMisses += $data['misses'] ?? 0;
            }
            
            $totalRequests = $totalHits + $totalMisses;
            return $totalRequests > 0 ? round(($totalHits / $totalRequests) * 100, 2) : 0.0;
        } catch (Throwable $e) {
            return 0.0;
        }
    }
}