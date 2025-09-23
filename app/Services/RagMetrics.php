<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use Throwable;

/**
 * RAG Metrics and Monitoring Service
 *
 * Comprehensive metrics collection, analysis, and monitoring for RAG operations.
 * Tracks performance, usage patterns, errors, and provides actionable insights
 * for system optimization and monitoring.
 */
class RagMetrics
{
    private array $config;
    private bool $enabled;
    private string $store;
    private array $buffer = [];
    private int $bufferSize;

    public function __construct()
    {
        $this->config = config('rag.metrics', []);
        $this->enabled = $this->config['enabled'] ?? true;
        $this->store = $this->config['store'] ?? 'database';
        $this->bufferSize = $this->config['buffer_size'] ?? 100;
    }

    /**
     * Record a query operation
     *
     * @param string $query The search query
     * @param array $results Search results
     * @param float $duration Duration in milliseconds
     * @param array $metadata Additional metadata
     */
    public function recordQuery(string $query, array $results, float $duration, array $metadata = []): void
    {
        if (!$this->enabled || !$this->shouldTrack('queries')) {
            return;
        }

        $metric = [
            'type' => 'query',
            'timestamp' => now(),
            'data' => [
                'query_length' => strlen($query),
                'query_hash' => hash('xxh64', $query), // Privacy-preserving hash
                'result_count' => count($results),
                'duration_ms' => $duration,
                'has_results' => !empty($results),
                'avg_similarity' => $this->calculateAveragesimilarity($results),
                'top_similarity' => $this->getTopSimilarity($results),
                'tenant_id' => $metadata['tenant_id'] ?? 'default',
                'user_id' => $metadata['user_id'] ?? null,
                'document_ids' => array_unique(array_column($results, 'document_id')),
                'search_mode' => $metadata['search_mode'] ?? 'vector',
                'reranked' => $metadata['reranked'] ?? false,
            ],
            'performance_category' => $this->categorizePerformance($duration),
            'success' => true,
        ];

        $this->addToBuffer($metric);
    }

    /**
     * Record an embedding operation
     *
     * @param array $texts Texts that were embedded
     * @param float $duration Duration in milliseconds
     * @param bool $cached Whether result was cached
     * @param array $metadata Additional metadata
     */
    public function recordEmbedding(array $texts, float $duration, bool $cached = false, array $metadata = []): void
    {
        if (!$this->enabled || !$this->shouldTrack('embeddings')) {
            return;
        }

        $metric = [
            'type' => 'embedding',
            'timestamp' => now(),
            'data' => [
                'text_count' => count($texts),
                'total_characters' => array_sum(array_map('strlen', $texts)),
                'avg_text_length' => count($texts) > 0 ? array_sum(array_map('strlen', $texts)) / count($texts) : 0,
                'duration_ms' => $duration,
                'cached' => $cached,
                'provider' => $metadata['provider'] ?? 'vertex',
                'model' => $metadata['model'] ?? 'textembedding-gecko@003',
                'batch_size' => count($texts),
                'tenant_id' => $metadata['tenant_id'] ?? 'default',
            ],
            'performance_category' => $this->categorizePerformance($duration),
            'success' => true,
        ];

        $this->addToBuffer($metric);
    }

    /**
     * Record a generation operation
     *
     * @param string $query Original query
     * @param array $contexts Contexts used
     * @param float $duration Duration in milliseconds
     * @param bool $cached Whether result was cached
     * @param array $metadata Additional metadata
     */
    public function recordGeneration(string $query, array $contexts, float $duration, bool $cached = false, array $metadata = []): void
    {
        if (!$this->enabled || !$this->shouldTrack('generation')) {
            return;
        }

        $metric = [
            'type' => 'generation',
            'timestamp' => now(),
            'data' => [
                'query_length' => strlen($query),
                'query_hash' => hash('xxh64', $query),
                'context_count' => count($contexts),
                'context_tokens' => $this->estimateTokens($contexts),
                'duration_ms' => $duration,
                'cached' => $cached,
                'provider' => $metadata['provider'] ?? 'vertex',
                'model' => $metadata['model'] ?? 'gemini-1.5-flash',
                'strategy' => $metadata['strategy'] ?? 'contextual',
                'confidence' => $metadata['confidence'] ?? null,
                'citation_count' => $metadata['citation_count'] ?? 0,
                'response_length' => $metadata['response_length'] ?? 0,
                'tenant_id' => $metadata['tenant_id'] ?? 'default',
                'user_id' => $metadata['user_id'] ?? null,
            ],
            'performance_category' => $this->categorizePerformance($duration),
            'success' => true,
        ];

        $this->addToBuffer($metric);
    }

    /**
     * Record an error
     *
     * @param string $operation Operation type (query, embedding, generation)
     * @param Throwable $exception Exception that occurred
     * @param array $context Additional context
     */
    public function recordError(string $operation, Throwable $exception, array $context = []): void
    {
        if (!$this->enabled || !$this->shouldTrack('errors')) {
            return;
        }

        $metric = [
            'type' => 'error',
            'timestamp' => now(),
            'data' => [
                'operation' => $operation,
                'error_type' => get_class($exception),
                'error_message' => $exception->getMessage(),
                'error_code' => $exception->getCode(),
                'file' => basename($exception->getFile()),
                'line' => $exception->getLine(),
                'context' => $context,
                'tenant_id' => $context['tenant_id'] ?? 'default',
                'user_id' => $context['user_id'] ?? null,
            ],
            'success' => false,
        ];

        $this->addToBuffer($metric);

        // Also log to Laravel logs for immediate visibility
        Log::error("RAG {$operation} error", [
            'error' => $exception->getMessage(),
            'context' => $context,
        ]);
    }

    /**
     * Record custom performance metric
     *
     * @param string $name Metric name
     * @param float $value Metric value
     * @param string $unit Unit of measurement
     * @param array $tags Additional tags
     */
    public function recordPerformance(string $name, float $value, string $unit = 'ms', array $tags = []): void
    {
        if (!$this->enabled || !$this->shouldTrack('performance')) {
            return;
        }

        $metric = [
            'type' => 'performance',
            'timestamp' => now(),
            'data' => [
                'metric_name' => $name,
                'value' => $value,
                'unit' => $unit,
                'tags' => $tags,
                'tenant_id' => $tags['tenant_id'] ?? 'default',
            ],
            'success' => true,
        ];

        $this->addToBuffer($metric);
    }

    /**
     * Get system health metrics
     *
     * @return array Health status and metrics
     */
    public function getHealthMetrics(): array
    {
        $cacheKey = 'rag:metrics:health';

        return Cache::remember($cacheKey, 300, function () {
            $now = now();
            $hourAgo = $now->copy()->subHour();
            $dayAgo = $now->copy()->subDay();

            return [
                'timestamp' => $now->toISOString(),
                'status' => $this->determineSystemHealth(),
                'queries' => [
                    'last_hour' => $this->getMetricCount('query', $hourAgo, $now),
                    'last_day' => $this->getMetricCount('query', $dayAgo, $now),
                    'success_rate' => $this->getSuccessRate('query', $hourAgo, $now),
                    'avg_duration' => $this->getAverageDuration('query', $hourAgo, $now),
                    'p95_duration' => $this->getPercentileDuration('query', 95, $hourAgo, $now),
                ],
                'embeddings' => [
                    'last_hour' => $this->getMetricCount('embedding', $hourAgo, $now),
                    'last_day' => $this->getMetricCount('embedding', $dayAgo, $now),
                    'cache_hit_rate' => $this->getCacheHitRate('embedding', $hourAgo, $now),
                    'avg_batch_size' => $this->getAverageBatchSize($hourAgo, $now),
                ],
                'generation' => [
                    'last_hour' => $this->getMetricCount('generation', $hourAgo, $now),
                    'last_day' => $this->getMetricCount('generation', $dayAgo, $now),
                    'avg_confidence' => $this->getAverageConfidence($hourAgo, $now),
                    'cache_hit_rate' => $this->getCacheHitRate('generation', $hourAgo, $now),
                ],
                'errors' => [
                    'last_hour' => $this->getMetricCount('error', $hourAgo, $now),
                    'last_day' => $this->getMetricCount('error', $dayAgo, $now),
                    'error_rate' => $this->getErrorRate($hourAgo, $now),
                    'top_errors' => $this->getTopErrors($dayAgo, $now, 5),
                ],
                'performance' => [
                    'avg_query_duration' => $this->getAverageDuration('query', $hourAgo, $now),
                    'slow_queries' => $this->getSlowQueries($hourAgo, $now, 5000), // > 5 seconds
                    'throughput_qpm' => $this->getThroughput($hourAgo, $now),
                ],
            ];
        });
    }

    /**
     * Get usage analytics
     *
     * @param Carbon $from Start date
     * @param Carbon $to End date
     * @return array Usage analytics
     */
    public function getUsageAnalytics(Carbon $from, Carbon $to): array
    {
        return [
            'period' => [
                'from' => $from->toISOString(),
                'to' => $to->toISOString(),
                'days' => $from->diffInDays($to),
            ],
            'queries' => [
                'total' => $this->getMetricCount('query', $from, $to),
                'daily_average' => $this->getDailyAverage('query', $from, $to),
                'peak_hour' => $this->getPeakHour('query', $from, $to),
                'unique_users' => $this->getUniqueUsers($from, $to),
                'top_queries' => $this->getTopQueries($from, $to, 10),
            ],
            'documents' => [
                'most_queried' => $this->getMostQueriedDocuments($from, $to, 10),
                'document_coverage' => $this->getDocumentCoverage($from, $to),
            ],
            'performance_trends' => [
                'avg_duration_trend' => $this->getDurationTrend('query', $from, $to),
                'error_rate_trend' => $this->getErrorRateTrend($from, $to),
                'cache_hit_trend' => $this->getCacheHitTrend($from, $to),
            ],
            'tenants' => [
                'active_tenants' => $this->getActiveTenants($from, $to),
                'tenant_usage' => $this->getTenantUsage($from, $to),
            ],
        ];
    }

    /**
     * Export metrics for external monitoring systems
     *
     * @param string $format Export format (json, csv, prometheus)
     * @param Carbon $from Start date
     * @param Carbon $to End date
     * @return array|string Exported data
     */
    public function exportMetrics(string $format, Carbon $from, Carbon $to)
    {
        $metrics = $this->getRawMetrics($from, $to);

        switch ($format) {
            case 'json':
                return $metrics;

            case 'csv':
                return $this->exportToCsv($metrics);

            case 'prometheus':
                return $this->exportToPrometheus($metrics);

            default:
                throw new \InvalidArgumentException("Unsupported format: {$format}");
        }
    }

    /**
     * Flush buffered metrics to storage
     */
    public function flush(): void
    {
        if (empty($this->buffer)) {
            return;
        }

        try {
            switch ($this->store) {
                case 'database':
                    $this->flushToDatabase();
                    break;

                case 'cache':
                    $this->flushToCache();
                    break;

                case 'log':
                    $this->flushToLog();
                    break;
            }

            $count = count($this->buffer);
            $this->buffer = [];

            Log::debug("Flushed {$count} metrics to {$this->store}");

        } catch (Throwable $e) {
            Log::error('Failed to flush metrics', [
                'store' => $this->store,
                'buffer_size' => count($this->buffer),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get total number of generations
     */
    public function getTotalGenerations(): int
    {
        return $this->getMetricCount('generation', now()->subMonth(), now());
    }

    /**
     * Get average generation latency
     */
    public function getAverageGenerationLatency(): float
    {
        return $this->getAverageDuration('generation', now()->subHour(), now());
    }

    /**
     * Get error rate for specific operation
     */
    public function getErrorRate(string $operation): float
    {
        $hourAgo = now()->subHour();
        $now = now();

        $totalOps = $this->getMetricCount($operation, $hourAgo, $now);
        $errors = $this->getMetricCount('error', $hourAgo, $now, ['operation' => $operation]);

        return $totalOps > 0 ? round(($errors / $totalOps) * 100, 2) : 0.0;
    }

    /**
     * Get confidence distribution for generations
     */
    public function getConfidenceDistribution(): array
    {
        $cacheKey = 'rag:metrics:confidence_dist';

        return Cache::remember($cacheKey, 300, function () {
            $hourAgo = now()->subHour();

            if ($this->store === 'database') {
                return DB::table('rag_metrics')
                    ->where('type', 'generation')
                    ->where('timestamp', '>=', $hourAgo)
                    ->whereNotNull('data->confidence')
                    ->selectRaw('
                        CASE
                            WHEN CAST(data->"$.confidence" AS DECIMAL) >= 0.8 THEN "high"
                            WHEN CAST(data->"$.confidence" AS DECIMAL) >= 0.6 THEN "medium"
                            WHEN CAST(data->"$.confidence" AS DECIMAL) >= 0.4 THEN "low"
                            ELSE "very_low"
                        END as confidence_level,
                        COUNT(*) as count
                    ')
                    ->groupBy('confidence_level')
                    ->pluck('count', 'confidence_level')
                    ->toArray();
            }

            return ['high' => 0, 'medium' => 0, 'low' => 0, 'very_low' => 0];
        });
    }

    /**
     * Get strategies used in generation
     */
    public function getStrategiesUsed(): array
    {
        $cacheKey = 'rag:metrics:strategies';

        return Cache::remember($cacheKey, 600, function () {
            $dayAgo = now()->subDay();

            if ($this->store === 'database') {
                return DB::table('rag_metrics')
                    ->where('type', 'generation')
                    ->where('timestamp', '>=', $dayAgo)
                    ->groupBy('data->strategy')
                    ->selectRaw('data->"$.strategy" as strategy, COUNT(*) as count')
                    ->orderByDesc('count')
                    ->pluck('count', 'strategy')
                    ->toArray();
            }

            return [];
        });
    }

    // Private helper methods

    private function shouldTrack(string $type): bool
    {
        return $this->config['track'][$type] ?? true;
    }

    private function addToBuffer(array $metric): void
    {
        $this->buffer[] = $metric;

        if (count($this->buffer) >= $this->bufferSize) {
            $this->flush();
        }
    }

    private function calculateAverageSimila

($results): float
    {
        if (empty($results)) {
            return 0.0;
        }

        $similarities = array_column($results, 'similarity');
        $validSimilarities = array_filter($similarities, fn($s) => $s !== null);

        if (empty($validSimilarities)) {
            return 0.0;
        }

        return round(array_sum($validSimilarities) / count($validSimilarities), 3);
    }

    private function getTopSimilarity(array $results): float
    {
        if (empty($results)) {
            return 0.0;
        }

        $similarities = array_column($results, 'similarity');
        $validSimilarities = array_filter($similarities, fn($s) => $s !== null);

        return !empty($validSimilarities) ? round(max($validSimilarities), 3) : 0.0;
    }

    private function categorizePerformance(float $duration): string
    {
        if ($duration < 100) return 'fast';
        if ($duration < 500) return 'medium';
        if ($duration < 2000) return 'slow';
        return 'very_slow';
    }

    private function estimateTokens(array $contexts): int
    {
        $totalChars = array_sum(array_map(fn($ctx) => strlen($ctx['content'] ?? ''), $contexts));
        return (int)($totalChars / 4); // Rough approximation
    }

    private function flushToDatabase(): void
    {
        $insertData = array_map(function ($metric) {
            return [
                'type' => $metric['type'],
                'timestamp' => $metric['timestamp'],
                'data' => json_encode($metric['data']),
                'success' => $metric['success'],
                'performance_category' => $metric['performance_category'] ?? null,
                'created_at' => now(),
            ];
        }, $this->buffer);

        DB::table('rag_metrics')->insert($insertData);
    }

    private function flushToCache(): void
    {
        foreach ($this->buffer as $metric) {
            $key = "rag:metrics:{$metric['type']}:" . $metric['timestamp']->format('Y-m-d-H-i-s') . ':' . uniqid();
            Cache::put($key, $metric, 86400); // 24 hours
        }
    }

    private function flushToLog(): void
    {
        foreach ($this->buffer as $metric) {
            Log::info('RAG Metric', $metric);
        }
    }

    private function getMetricCount(string $type, Carbon $from, Carbon $to, array $filters = []): int
    {
        if ($this->store === 'database') {
            $query = DB::table('rag_metrics')
                ->where('type', $type)
                ->whereBetween('timestamp', [$from, $to]);

            foreach ($filters as $key => $value) {
                $query->where("data->{$key}", $value);
            }

            return $query->count();
        }

        return 0;
    }

    private function getSuccessRate(string $type, Carbon $from, Carbon $to): float
    {
        $total = $this->getMetricCount($type, $from, $to);
        if ($total === 0) return 100.0;

        if ($this->store === 'database') {
            $successful = DB::table('rag_metrics')
                ->where('type', $type)
                ->whereBetween('timestamp', [$from, $to])
                ->where('success', true)
                ->count();

            return round(($successful / $total) * 100, 2);
        }

        return 100.0;
    }

    private function getAverageDuration(string $type, Carbon $from, Carbon $to): float
    {
        if ($this->store === 'database') {
            return (float) DB::table('rag_metrics')
                ->where('type', $type)
                ->whereBetween('timestamp', [$from, $to])
                ->avg('data->duration_ms') ?? 0.0;
        }

        return 0.0;
    }

    private function getPercentileDuration(string $type, int $percentile, Carbon $from, Carbon $to): float
    {
        if ($this->store === 'database') {
            $durations = DB::table('rag_metrics')
                ->where('type', $type)
                ->whereBetween('timestamp', [$from, $to])
                ->pluck('data->duration_ms')
                ->filter()
                ->sort()
                ->values();

            if ($durations->isEmpty()) return 0.0;

            $index = (int) ceil(($percentile / 100) * $durations->count()) - 1;
            return (float) $durations->get($index, 0);
        }

        return 0.0;
    }

    private function getCacheHitRate(string $type, Carbon $from, Carbon $to): float
    {
        if ($this->store === 'database') {
            $total = DB::table('rag_metrics')
                ->where('type', $type)
                ->whereBetween('timestamp', [$from, $to])
                ->count();

            if ($total === 0) return 0.0;

            $hits = DB::table('rag_metrics')
                ->where('type', $type)
                ->whereBetween('timestamp', [$from, $to])
                ->where('data->cached', true)
                ->count();

            return round(($hits / $total) * 100, 2);
        }

        return 0.0;
    }

    private function determineSystemHealth(): string
    {
        $hourAgo = now()->subHour();
        $now = now();

        $errorRate = $this->getErrorRate($hourAgo, $now);
        $avgDuration = $this->getAverageDuration('query', $hourAgo, $now);

        if ($errorRate > 10 || $avgDuration > 5000) {
            return 'critical';
        } elseif ($errorRate > 5 || $avgDuration > 2000) {
            return 'warning';
        } elseif ($errorRate > 1 || $avgDuration > 1000) {
            return 'degraded';
        } else {
            return 'healthy';
        }
    }

    private function getAverageBatchSize(Carbon $from, Carbon $to): float
    {
        if ($this->store === 'database') {
            return (float) DB::table('rag_metrics')
                ->where('type', 'embedding')
                ->whereBetween('timestamp', [$from, $to])
                ->avg('data->batch_size') ?? 0.0;
        }

        return 0.0;
    }

    private function getAverageConfidence(Carbon $from, Carbon $to): float
    {
        if ($this->store === 'database') {
            return (float) DB::table('rag_metrics')
                ->where('type', 'generation')
                ->whereBetween('timestamp', [$from, $to])
                ->whereNotNull('data->confidence')
                ->avg('data->confidence') ?? 0.0;
        }

        return 0.0;
    }

    private function getTopErrors(Carbon $from, Carbon $to, int $limit): array
    {
        if ($this->store === 'database') {
            return DB::table('rag_metrics')
                ->where('type', 'error')
                ->whereBetween('timestamp', [$from, $to])
                ->groupBy('data->error_type', 'data->error_message')
                ->selectRaw('
                    data->"$.error_type" as error_type,
                    data->"$.error_message" as error_message,
                    COUNT(*) as count
                ')
                ->orderByDesc('count')
                ->limit($limit)
                ->get()
                ->toArray();
        }

        return [];
    }

    private function getSlowQueries(Carbon $from, Carbon $to, float $thresholdMs): int
    {
        if ($this->store === 'database') {
            return DB::table('rag_metrics')
                ->where('type', 'query')
                ->whereBetween('timestamp', [$from, $to])
                ->whereRaw('CAST(data->"$.duration_ms" AS DECIMAL) > ?', [$thresholdMs])
                ->count();
        }

        return 0;
    }

    private function getThroughput(Carbon $from, Carbon $to): float
    {
        $minutes = $from->diffInMinutes($to);
        if ($minutes === 0) return 0.0;

        $queryCount = $this->getMetricCount('query', $from, $to);
        return round($queryCount / $minutes, 2);
    }

    private function getDailyAverage(string $type, Carbon $from, Carbon $to): float
    {
        $days = $from->diffInDays($to);
        if ($days === 0) $days = 1;

        $total = $this->getMetricCount($type, $from, $to);
        return round($total / $days, 2);
    }

    private function getPeakHour(string $type, Carbon $from, Carbon $to): ?int
    {
        if ($this->store === 'database') {
            $result = DB::table('rag_metrics')
                ->where('type', $type)
                ->whereBetween('timestamp', [$from, $to])
                ->selectRaw('HOUR(timestamp) as hour, COUNT(*) as count')
                ->groupBy('hour')
                ->orderByDesc('count')
                ->first();

            return $result ? (int) $result->hour : null;
        }

        return null;
    }

    private function getUniqueUsers(Carbon $from, Carbon $to): int
    {
        if ($this->store === 'database') {
            return DB::table('rag_metrics')
                ->whereIn('type', ['query', 'generation'])
                ->whereBetween('timestamp', [$from, $to])
                ->whereNotNull('data->user_id')
                ->distinct('data->user_id')
                ->count();
        }

        return 0;
    }

    private function getTopQueries(Carbon $from, Carbon $to, int $limit): array
    {
        if ($this->store === 'database') {
            return DB::table('rag_metrics')
                ->where('type', 'query')
                ->whereBetween('timestamp', [$from, $to])
                ->groupBy('data->query_hash')
                ->selectRaw('data->"$.query_hash" as query_hash, COUNT(*) as count')
                ->orderByDesc('count')
                ->limit($limit)
                ->get()
                ->toArray();
        }

        return [];
    }

    private function getMostQueriedDocuments(Carbon $from, Carbon $to, int $limit): array
    {
        if ($this->store === 'database') {
            return DB::table('rag_metrics')
                ->where('type', 'query')
                ->whereBetween('timestamp', [$from, $to])
                ->whereNotNull('data->document_ids')
                ->selectRaw('
                    JSON_UNQUOTE(JSON_EXTRACT(data, "$.document_ids[0]")) as document_id,
                    COUNT(*) as query_count
                ')
                ->groupBy('document_id')
                ->orderByDesc('query_count')
                ->limit($limit)
                ->get()
                ->toArray();
        }

        return [];
    }

    private function getDocumentCoverage(Carbon $from, Carbon $to): array
    {
        if ($this->store === 'database') {
            $queriedDocs = DB::table('rag_metrics')
                ->where('type', 'query')
                ->whereBetween('timestamp', [$from, $to])
                ->distinct('data->document_ids')
                ->count();

            $totalDocs = DB::table('documents')->count();

            return [
                'queried_documents' => $queriedDocs,
                'total_documents' => $totalDocs,
                'coverage_percentage' => $totalDocs > 0 ? round(($queriedDocs / $totalDocs) * 100, 2) : 0,
            ];
        }

        return ['queried_documents' => 0, 'total_documents' => 0, 'coverage_percentage' => 0];
    }

    private function getDurationTrend(string $type, Carbon $from, Carbon $to): array
    {
        if ($this->store === 'database') {
            return DB::table('rag_metrics')
                ->where('type', $type)
                ->whereBetween('timestamp', [$from, $to])
                ->selectRaw('DATE(timestamp) as date, AVG(CAST(data->"$.duration_ms" AS DECIMAL)) as avg_duration')
                ->groupBy('date')
                ->orderBy('date')
                ->get()
                ->toArray();
        }

        return [];
    }

    private function getErrorRateTrend(Carbon $from, Carbon $to): array
    {
        if ($this->store === 'database') {
            return DB::table('rag_metrics')
                ->whereBetween('timestamp', [$from, $to])
                ->selectRaw('
                    DATE(timestamp) as date,
                    COUNT(*) as total_ops,
                    SUM(CASE WHEN type = "error" THEN 1 ELSE 0 END) as errors,
                    ROUND((SUM(CASE WHEN type = "error" THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) as error_rate
                ')
                ->groupBy('date')
                ->orderBy('date')
                ->get()
                ->toArray();
        }

        return [];
    }

    private function getCacheHitTrend(Carbon $from, Carbon $to): array
    {
        if ($this->store === 'database') {
            return DB::table('rag_metrics')
                ->whereIn('type', ['embedding', 'generation'])
                ->whereBetween('timestamp', [$from, $to])
                ->selectRaw('
                    DATE(timestamp) as date,
                    type,
                    COUNT(*) as total_ops,
                    SUM(CASE WHEN CAST(data->"$.cached" AS UNSIGNED) = 1 THEN 1 ELSE 0 END) as cache_hits,
                    ROUND((SUM(CASE WHEN CAST(data->"$.cached" AS UNSIGNED) = 1 THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) as hit_rate
                ')
                ->groupBy('date', 'type')
                ->orderBy('date')
                ->get()
                ->toArray();
        }

        return [];
    }

    private function getActiveTenants(Carbon $from, Carbon $to): array
    {
        if ($this->store === 'database') {
            return DB::table('rag_metrics')
                ->whereBetween('timestamp', [$from, $to])
                ->groupBy('data->tenant_id')
                ->selectRaw('data->"$.tenant_id" as tenant_id, COUNT(*) as operations')
                ->orderByDesc('operations')
                ->get()
                ->toArray();
        }

        return [];
    }

    private function getTenantUsage(Carbon $from, Carbon $to): array
    {
        if ($this->store === 'database') {
            return DB::table('rag_metrics')
                ->whereIn('type', ['query', 'embedding', 'generation'])
                ->whereBetween('timestamp', [$from, $to])
                ->selectRaw('
                    data->"$.tenant_id" as tenant_id,
                    type,
                    COUNT(*) as count,
                    AVG(CAST(data->"$.duration_ms" AS DECIMAL)) as avg_duration
                ')
                ->groupBy('tenant_id', 'type')
                ->orderBy('tenant_id', 'type')
                ->get()
                ->toArray();
        }

        return [];
    }

    private function getRawMetrics(Carbon $from, Carbon $to): array
    {
        if ($this->store === 'database') {
            return DB::table('rag_metrics')
                ->whereBetween('timestamp', [$from, $to])
                ->orderBy('timestamp')
                ->get()
                ->toArray();
        }

        return [];
    }

    private function exportToCsv(array $metrics): string
    {
        if (empty($metrics)) {
            return '';
        }

        $csv = "timestamp,type,success,performance_category,data\n";

        foreach ($metrics as $metric) {
            $csv .= sprintf(
                "%s,%s,%s,%s,\"%s\"\n",
                $metric->timestamp,
                $metric->type,
                $metric->success ? 'true' : 'false',
                $metric->performance_category ?? '',
                str_replace('"', '""', $metric->data)
            );
        }

        return $csv;
    }

    private function exportToPrometheus(array $metrics): string
    {
        $prometheus = "# RAG Metrics Export\n";

        $metricGroups = [];
        foreach ($metrics as $metric) {
            $data = json_decode($metric->data, true);
            $type = $metric->type;

            if ($type === 'query') {
                $metricGroups['rag_query_duration_ms'][] = $data['duration_ms'] ?? 0;
                $metricGroups['rag_query_result_count'][] = $data['result_count'] ?? 0;
            } elseif ($type === 'embedding') {
                $metricGroups['rag_embedding_duration_ms'][] = $data['duration_ms'] ?? 0;
                $metricGroups['rag_embedding_batch_size'][] = $data['batch_size'] ?? 0;
            } elseif ($type === 'generation') {
                $metricGroups['rag_generation_duration_ms'][] = $data['duration_ms'] ?? 0;
                $metricGroups['rag_generation_confidence'][] = $data['confidence'] ?? 0;
            }
        }

        foreach ($metricGroups as $metricName => $values) {
            if (!empty($values)) {
                $prometheus .= "# TYPE {$metricName} gauge\n";
                $prometheus .= "{$metricName} " . end($values) . "\n";
            }
        }

        return $prometheus;
    }

    public function __destruct()
    {
        // Flush any remaining buffered metrics
        if (!empty($this->buffer)) {
            $this->flush();
        }
    }
}