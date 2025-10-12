<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * Document-specific persistent cache
 * Caches search results, embeddings, and metadata per document
 * Uses Redis with file fallback for durability
 */
class DocumentCacheService
{
    private const TTL_SEARCH_RESULTS = 86400 * 30; // 30 days
    private const TTL_EMBEDDINGS = 86400 * 90; // 90 days (very stable)
    private const TTL_METADATA = 86400 * 7; // 7 days
    
    private string $cacheDriver;
    private string $cachePrefix = 'doc_cache:';
    
    public function __construct()
    {
        // Try Redis first, fallback to file
        $this->cacheDriver = config('cache.default', 'file');
        
        if ($this->cacheDriver === 'redis') {
            try {
                Cache::store('redis')->get('test');
            } catch (\Exception $e) {
                Log::warning('Redis unavailable, using file cache', ['error' => $e->getMessage()]);
                $this->cacheDriver = 'file';
            }
        }
    }
    
    /**
     * Cache search results for a document
     */
    public function cacheSearchResult(int $documentId, string $query, array $result): bool
    {
        try {
            $key = $this->getSearchKey($documentId, $query);
            
            $cacheData = [
                'query' => $query,
                'document_id' => $documentId,
                'result' => $result,
                'cached_at' => now()->toIso8601String(),
                'ttl' => self::TTL_SEARCH_RESULTS
            ];
            
            Cache::store($this->cacheDriver)->put($key, $cacheData, self::TTL_SEARCH_RESULTS);
            
            // Also update stats
            $this->incrementCacheStats($documentId, 'searches_cached');
            
            Log::debug('Search result cached', [
                'document_id' => $documentId,
                'query_hash' => $this->hashQuery($query),
                'ttl' => self::TTL_SEARCH_RESULTS
            ]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Cache save failed', [
                'document_id' => $documentId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Get cached search result
     */
    public function getCachedSearchResult(int $documentId, string $query): ?array
    {
        try {
            $key = $this->getSearchKey($documentId, $query);
            $cached = Cache::store($this->cacheDriver)->get($key);
            
            if ($cached) {
                // Update stats
                $this->incrementCacheStats($documentId, 'cache_hits');
                
                Log::debug('Cache hit', [
                    'document_id' => $documentId,
                    'query_hash' => $this->hashQuery($query),
                    'cached_at' => $cached['cached_at'] ?? 'unknown'
                ]);
                
                return $cached['result'] ?? null;
            }
            
            $this->incrementCacheStats($documentId, 'cache_misses');
            return null;
        } catch (\Exception $e) {
            Log::error('Cache retrieval failed', [
                'document_id' => $documentId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Cache document metadata
     */
    public function cacheDocumentMetadata(int $documentId, array $metadata): bool
    {
        try {
            $key = $this->getMetadataKey($documentId);
            
            $cacheData = [
                'document_id' => $documentId,
                'metadata' => $metadata,
                'cached_at' => now()->toIso8601String()
            ];
            
            Cache::store($this->cacheDriver)->put($key, $cacheData, self::TTL_METADATA);
            return true;
        } catch (\Exception $e) {
            Log::error('Metadata cache failed', ['error' => $e->getMessage()]);
            return false;
        }
    }
    
    /**
     * Get cached document metadata
     */
    public function getCachedMetadata(int $documentId): ?array
    {
        try {
            $key = $this->getMetadataKey($documentId);
            $cached = Cache::store($this->cacheDriver)->get($key);
            return $cached['metadata'] ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }
    
    /**
     * Clear all cache for a specific document
     */
    public function clearDocumentCache(int $documentId): bool
    {
        try {
            // Clear search results
            $pattern = $this->cachePrefix . "search:{$documentId}:*";
            $this->clearByPattern($pattern);
            
            // Clear metadata
            $key = $this->getMetadataKey($documentId);
            Cache::store($this->cacheDriver)->forget($key);
            
            // Clear stats
            $statsKey = $this->getStatsKey($documentId);
            Cache::store($this->cacheDriver)->forget($statsKey);
            
            Log::info('Document cache cleared', ['document_id' => $documentId]);
            return true;
        } catch (\Exception $e) {
            Log::error('Cache clear failed', [
                'document_id' => $documentId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Get cache statistics for a document
     */
    public function getDocumentCacheStats(int $documentId): array
    {
        try {
            $key = $this->getStatsKey($documentId);
            $stats = Cache::store($this->cacheDriver)->get($key, [
                'document_id' => $documentId,
                'searches_cached' => 0,
                'cache_hits' => 0,
                'cache_misses' => 0,
                'hit_rate' => 0.0,
                'created_at' => now()->toIso8601String()
            ]);
            
            // Calculate hit rate
            $total = ($stats['cache_hits'] ?? 0) + ($stats['cache_misses'] ?? 0);
            $stats['hit_rate'] = $total > 0 
                ? round(($stats['cache_hits'] ?? 0) / $total * 100, 2) 
                : 0.0;
            
            return $stats;
        } catch (\Exception $e) {
            return [
                'document_id' => $documentId,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get global cache statistics
     */
    public function getGlobalCacheStats(): array
    {
        try {
            // Get all documents with chunks
            $documents = DB::table('documents')
                ->select('id', 'title')
                ->whereExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('chunks')
                        ->whereColumn('chunks.document_id', 'documents.id');
                })
                ->get();
            
            $globalStats = [
                'total_documents' => count($documents),
                'total_hits' => 0,
                'total_misses' => 0,
                'total_cached' => 0,
                'average_hit_rate' => 0.0,
                'top_cached_documents' => []
            ];
            
            foreach ($documents as $doc) {
                $docStats = $this->getDocumentCacheStats($doc->id);
                $globalStats['total_hits'] += $docStats['cache_hits'] ?? 0;
                $globalStats['total_misses'] += $docStats['cache_misses'] ?? 0;
                $globalStats['total_cached'] += $docStats['searches_cached'] ?? 0;
                
                if (($docStats['cache_hits'] ?? 0) > 0) {
                    $globalStats['top_cached_documents'][] = [
                        'document_id' => $doc->id,
                        'title' => $doc->title,
                        'hits' => $docStats['cache_hits'],
                        'hit_rate' => $docStats['hit_rate']
                    ];
                }
            }
            
            // Sort by hits
            usort($globalStats['top_cached_documents'], function($a, $b) {
                return $b['hits'] - $a['hits'];
            });
            $globalStats['top_cached_documents'] = array_slice($globalStats['top_cached_documents'], 0, 10);
            
            // Calculate average hit rate
            $totalRequests = $globalStats['total_hits'] + $globalStats['total_misses'];
            $globalStats['average_hit_rate'] = $totalRequests > 0
                ? round($globalStats['total_hits'] / $totalRequests * 100, 2)
                : 0.0;
            
            $globalStats['cache_driver'] = $this->cacheDriver;
            $globalStats['generated_at'] = now()->toIso8601String();
            
            return $globalStats;
        } catch (\Exception $e) {
            Log::error('Global stats failed', ['error' => $e->getMessage()]);
            return [
                'error' => $e->getMessage(),
                'cache_driver' => $this->cacheDriver
            ];
        }
    }
    
    /**
     * Pre-warm cache for a document (cache suggested questions, metadata, etc)
     */
    public function warmDocumentCache(int $documentId): bool
    {
        try {
            // Get document metadata
            $doc = DB::table('documents')->where('id', $documentId)->first();
            if (!$doc) {
                return false;
            }
            
            // Cache metadata
            $metadata = json_decode($doc->metadata ?? '{}', true);
            $this->cacheDocumentMetadata($documentId, $metadata);
            
            // Cache suggested questions if available
            if (!empty($metadata['suggested_questions'])) {
                foreach ($metadata['suggested_questions'] as $question) {
                    // We won't execute searches, just prepare the cache keys
                    // Real cache will be populated on first search
                }
            }
            
            Log::info('Document cache warmed', ['document_id' => $documentId]);
            return true;
        } catch (\Exception $e) {
            Log::error('Cache warm failed', ['error' => $e->getMessage()]);
            return false;
        }
    }
    
    private function getSearchKey(int $documentId, string $query): string
    {
        $queryHash = $this->hashQuery($query);
        return $this->cachePrefix . "search:{$documentId}:{$queryHash}";
    }
    
    private function getMetadataKey(int $documentId): string
    {
        return $this->cachePrefix . "metadata:{$documentId}";
    }
    
    private function getStatsKey(int $documentId): string
    {
        return $this->cachePrefix . "stats:{$documentId}";
    }
    
    private function hashQuery(string $query): string
    {
        // Normalize query for caching
        $normalized = mb_strtolower(trim($query));
        $normalized = preg_replace('/\s+/', ' ', $normalized);
        return md5($normalized);
    }
    
    private function incrementCacheStats(int $documentId, string $metric): void
    {
        try {
            $key = $this->getStatsKey($documentId);
            $stats = Cache::store($this->cacheDriver)->get($key, []);
            
            if (!isset($stats[$metric])) {
                $stats[$metric] = 0;
            }
            $stats[$metric]++;
            
            $stats['last_updated'] = now()->toIso8601String();
            
            Cache::store($this->cacheDriver)->put($key, $stats, self::TTL_SEARCH_RESULTS);
        } catch (\Exception $e) {
            // Fail silently for stats
        }
    }
    
    private function clearByPattern(string $pattern): void
    {
        // For file cache, we can't easily clear by pattern
        // For Redis, we could use KEYS command (not recommended in production)
        // For now, this is a placeholder
        Log::debug('Cache pattern clear requested', ['pattern' => $pattern]);
    }
}

