<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;
use App\Services\VertexClient;
use App\Services\RagCache;
use Throwable;

/**
 * Advanced Semantic Reranker Service
 *
 * Provides sophisticated reranking capabilities for RAG search results using
 * multiple algorithms including cross-encoder models, semantic similarity,
 * and hybrid scoring techniques for enterprise-grade accuracy.
 */
class SemanticReranker
{
    private VertexClient $vertexClient;
    private RagCache $cache;
    private array $config;

    public function __construct(VertexClient $vertexClient, RagCache $cache)
    {
        $this->vertexClient = $vertexClient;
        $this->cache = $cache;
        $this->config = config('rag.search', []);
    }

    /**
     * Rerank search results using advanced semantic scoring
     *
     * @param string $query The original search query
     * @param array $results Initial search results to rerank
     * @param array $options Reranking configuration options
     * @return array Reranked results with updated scores
     */
    public function rerank(string $query, array $results, array $options = []): array
    {
        if (empty($results) || !$this->isRerankingEnabled()) {
            return $results;
        }

        $startTime = microtime(true);

        try {
            // Apply reranking strategy
            $strategy = $options['strategy'] ?? $this->config['reranking_strategy'] ?? 'semantic';
            $topK = $options['top_k'] ?? $this->config['reranking_top_k'] ?? 50;

            // Limit results for performance
            $candidates = array_slice($results, 0, $topK);

            $rerankedResults = match ($strategy) {
                'semantic' => $this->semanticRerank($query, $candidates, $options),
                'cross_encoder' => $this->crossEncoderRerank($query, $candidates, $options),
                'hybrid' => $this->hybridRerank($query, $candidates, $options),
                'mmr' => $this->maximalMarginalRelevanceRerank($query, $candidates, $options),
                default => $this->semanticRerank($query, $candidates, $options)
            };

            // Log performance metrics
            $duration = (microtime(true) - $startTime) * 1000;
            $this->logRerankingMetrics($query, count($results), count($rerankedResults), $strategy, $duration);

            return $rerankedResults;

        } catch (Throwable $e) {
            Log::error('Semantic reranking failed', [
                'error' => $e->getMessage(),
                'query' => substr($query, 0, 200),
                'result_count' => count($results),
                'trace' => $e->getTraceAsString()
            ]);

            // Return original results on failure
            return $results;
        }
    }

    /**
     * Semantic similarity-based reranking
     */
    private function semanticRerank(string $query, array $results, array $options = []): array
    {
        $cacheKey = $this->cache->getCacheKey('rerank_semantic', [
            'query' => md5($query),
            'results' => md5(serialize(array_column($results, 'id'))),
            'options' => md5(serialize($options))
        ]);

        return $this->cache->remember($cacheKey, function () use ($query, $results, $options) {
            // Get query embedding
            $queryEmbedding = $this->vertexClient->embed([$query])[0] ?? null;
            if (!$queryEmbedding) {
                return $results;
            }

            // Calculate semantic similarities and rerank
            $scoredResults = [];
            foreach ($results as $result) {
                if (isset($result['embedding']) && is_array($result['embedding'])) {
                    $similarity = $this->calculateCosineSimilarity($queryEmbedding, $result['embedding']);
                } else {
                    // Fallback: re-embed the content
                    $contentEmbedding = $this->vertexClient->embed([$result['content']])[0] ?? null;
                    $similarity = $contentEmbedding ?
                        $this->calculateCosineSimilarity($queryEmbedding, $contentEmbedding) :
                        ($result['similarity'] ?? 0.0);
                }

                $result['rerank_score'] = $similarity;
                $result['original_rank'] = $result['rank'] ?? count($scoredResults);
                $scoredResults[] = $result;
            }

            // Sort by rerank score
            usort($scoredResults, fn($a, $b) => $b['rerank_score'] <=> $a['rerank_score']);

            // Update ranks
            foreach ($scoredResults as $index => &$result) {
                $result['rank'] = $index + 1;
                $result['rank_change'] = $result['original_rank'] - $result['rank'];
            }

            return $scoredResults;
        }, 3600); // Cache for 1 hour
    }

    /**
     * Cross-encoder based reranking (simulated with embeddings)
     */
    private function crossEncoderRerank(string $query, array $results, array $options = []): array
    {
        $batchSize = $options['batch_size'] ?? 10;
        $batches = array_chunk($results, $batchSize);
        $rerankedResults = [];

        foreach ($batches as $batch) {
            $pairs = [];
            foreach ($batch as $result) {
                // Create query-document pairs for cross-encoder scoring
                $pairs[] = $query . ' [SEP] ' . substr($result['content'], 0, 512);
            }

            try {
                // Use embeddings as proxy for cross-encoder scores
                $embeddings = $this->vertexClient->embed($pairs);

                foreach ($batch as $index => $result) {
                    if (isset($embeddings[$index])) {
                        // Calculate a pseudo cross-encoder score
                        $crossScore = $this->calculateCrossEncoderScore($embeddings[$index]);
                        $result['rerank_score'] = $crossScore;
                        $result['original_rank'] = count($rerankedResults) + $index;
                    } else {
                        $result['rerank_score'] = $result['similarity'] ?? 0.0;
                        $result['original_rank'] = count($rerankedResults) + $index;
                    }
                    $rerankedResults[] = $result;
                }
            } catch (Throwable $e) {
                Log::warning('Cross-encoder batch failed, using fallback', [
                    'error' => $e->getMessage(),
                    'batch_size' => count($batch)
                ]);

                // Fallback to original similarity scores
                foreach ($batch as $index => $result) {
                    $result['rerank_score'] = $result['similarity'] ?? 0.0;
                    $result['original_rank'] = count($rerankedResults) + $index;
                    $rerankedResults[] = $result;
                }
            }
        }

        // Sort by rerank score
        usort($rerankedResults, fn($a, $b) => $b['rerank_score'] <=> $a['rerank_score']);

        // Update ranks and rank changes
        foreach ($rerankedResults as $index => &$result) {
            $result['rank'] = $index + 1;
            $result['rank_change'] = $result['original_rank'] - $result['rank'];
        }

        return $rerankedResults;
    }

    /**
     * Hybrid reranking combining multiple signals
     */
    private function hybridRerank(string $query, array $results, array $options = []): array
    {
        $weights = [
            'semantic' => $options['semantic_weight'] ?? 0.5,
            'lexical' => $options['lexical_weight'] ?? 0.3,
            'recency' => $options['recency_weight'] ?? 0.1,
            'diversity' => $options['diversity_weight'] ?? 0.1,
        ];

        // Get semantic scores
        $semanticResults = $this->semanticRerank($query, $results, $options);
        $semanticScores = array_column($semanticResults, 'rerank_score', 'id');

        $hybridResults = [];
        foreach ($results as $result) {
            $hybridScore = 0;
            $resultId = $result['id'];

            // Semantic similarity score
            $semanticScore = $semanticScores[$resultId] ?? ($result['similarity'] ?? 0.0);
            $hybridScore += $weights['semantic'] * $semanticScore;

            // Lexical matching score
            $lexicalScore = $this->calculateLexicalScore($query, $result['content']);
            $hybridScore += $weights['lexical'] * $lexicalScore;

            // Recency score (if available)
            if (isset($result['created_at'])) {
                $recencyScore = $this->calculateRecencyScore($result['created_at']);
                $hybridScore += $weights['recency'] * $recencyScore;
            }

            $result['rerank_score'] = $hybridScore;
            $result['scores'] = [
                'semantic' => $semanticScore,
                'lexical' => $lexicalScore,
                'recency' => $recencyScore ?? 0,
                'hybrid' => $hybridScore
            ];
            $result['original_rank'] = count($hybridResults);
            $hybridResults[] = $result;
        }

        // Apply diversity penalty to avoid redundant results
        if ($weights['diversity'] > 0) {
            $hybridResults = $this->applyDiversityPenalty($hybridResults, $weights['diversity']);
        }

        // Sort by hybrid score
        usort($hybridResults, fn($a, $b) => $b['rerank_score'] <=> $a['rerank_score']);

        // Update ranks
        foreach ($hybridResults as $index => &$result) {
            $result['rank'] = $index + 1;
            $result['rank_change'] = $result['original_rank'] - $result['rank'];
        }

        return $hybridResults;
    }

    /**
     * Maximal Marginal Relevance (MMR) reranking for diversity
     */
    private function maximalMarginalRelevanceRerank(string $query, array $results, array $options = []): array
    {
        $lambda = $options['lambda'] ?? 0.7; // Balance between relevance and diversity
        $queryEmbedding = $this->vertexClient->embed([$query])[0] ?? null;

        if (!$queryEmbedding || count($results) <= 1) {
            return $results;
        }

        $selected = [];
        $remaining = $results;

        // Select first result (highest similarity)
        usort($remaining, fn($a, $b) => ($b['similarity'] ?? 0) <=> ($a['similarity'] ?? 0));
        $selected[] = array_shift($remaining);

        // Select remaining results using MMR
        while (!empty($remaining) && count($selected) < count($results)) {
            $bestScore = -1;
            $bestIndex = -1;

            foreach ($remaining as $index => $candidate) {
                // Relevance to query
                $relevance = $candidate['similarity'] ?? 0;

                // Maximum similarity to already selected documents
                $maxSimilarity = 0;
                foreach ($selected as $selectedDoc) {
                    if (isset($candidate['embedding']) && isset($selectedDoc['embedding'])) {
                        $similarity = $this->calculateCosineSimilarity(
                            $candidate['embedding'],
                            $selectedDoc['embedding']
                        );
                        $maxSimilarity = max($maxSimilarity, $similarity);
                    }
                }

                // MMR score
                $mmrScore = $lambda * $relevance - (1 - $lambda) * $maxSimilarity;

                if ($mmrScore > $bestScore) {
                    $bestScore = $mmrScore;
                    $bestIndex = $index;
                }
            }

            if ($bestIndex >= 0) {
                $selected[] = $remaining[$bestIndex];
                array_splice($remaining, $bestIndex, 1);
            } else {
                break;
            }
        }

        // Update scores and ranks
        foreach ($selected as $index => &$result) {
            $result['rank'] = $index + 1;
            $result['rerank_score'] = $result['similarity'] ?? 0;
            $result['mmr_applied'] = true;
        }

        return $selected;
    }

    /**
     * Calculate cosine similarity between two vectors
     */
    private function calculateCosineSimilarity(array $a, array $b): float
    {
        if (count($a) !== count($b)) {
            return 0.0;
        }

        $dotProduct = 0;
        $normA = 0;
        $normB = 0;

        for ($i = 0; $i < count($a); $i++) {
            $dotProduct += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }

        $normA = sqrt($normA);
        $normB = sqrt($normB);

        if ($normA == 0 || $normB == 0) {
            return 0.0;
        }

        return $dotProduct / ($normA * $normB);
    }

    /**
     * Calculate pseudo cross-encoder score from embedding
     */
    private function calculateCrossEncoderScore(array $embedding): float
    {
        // Simplified cross-encoder simulation using embedding magnitude and variance
        $magnitude = sqrt(array_sum(array_map(fn($x) => $x * $x, $embedding)));
        $mean = array_sum($embedding) / count($embedding);
        $variance = array_sum(array_map(fn($x) => ($x - $mean) ** 2, $embedding)) / count($embedding);

        // Normalize to 0-1 range
        return min(1.0, max(0.0, ($magnitude * $variance) / 100));
    }

    /**
     * Calculate lexical matching score
     */
    private function calculateLexicalScore(string $query, string $content): float
    {
        $queryWords = array_unique(str_word_count(strtolower($query), 1));
        $contentWords = str_word_count(strtolower($content), 1);

        if (empty($queryWords)) {
            return 0.0;
        }

        $matches = 0;
        foreach ($queryWords as $word) {
            if (in_array($word, $contentWords)) {
                $matches++;
            }
        }

        return $matches / count($queryWords);
    }

    /**
     * Calculate recency score based on timestamp
     */
    private function calculateRecencyScore(string $timestamp): float
    {
        try {
            $created = strtotime($timestamp);
            $now = time();
            $daysDiff = ($now - $created) / (24 * 60 * 60);

            // Exponential decay: newer documents score higher
            return exp(-$daysDiff / 30); // 30-day half-life
        } catch (Throwable $e) {
            return 0.0;
        }
    }

    /**
     * Apply diversity penalty to reduce similar results
     */
    private function applyDiversityPenalty(array $results, float $diversityWeight): array
    {
        for ($i = 0; $i < count($results); $i++) {
            $penalty = 0;

            for ($j = 0; $j < $i; $j++) {
                if (isset($results[$i]['embedding']) && isset($results[$j]['embedding'])) {
                    $similarity = $this->calculateCosineSimilarity(
                        $results[$i]['embedding'],
                        $results[$j]['embedding']
                    );
                    $penalty += $similarity * $diversityWeight * (1 - $j / count($results));
                }
            }

            $results[$i]['rerank_score'] -= $penalty;
            $results[$i]['diversity_penalty'] = $penalty;
        }

        return $results;
    }

    /**
     * Check if reranking is enabled
     */
    private function isRerankingEnabled(): bool
    {
        return $this->config['enable_reranking'] ?? true;
    }

    /**
     * Log reranking performance metrics
     */
    private function logRerankingMetrics(string $query, int $originalCount, int $rerankedCount, string $strategy, float $duration): void
    {
        if (config('rag.metrics.enabled', true)) {
            Log::info('Semantic reranking completed', [
                'query_length' => strlen($query),
                'original_count' => $originalCount,
                'reranked_count' => $rerankedCount,
                'strategy' => $strategy,
                'duration_ms' => round($duration, 2),
                'performance_category' => $duration < 100 ? 'fast' : ($duration < 500 ? 'medium' : 'slow')
            ]);
        }
    }

    /**
     * Get reranking statistics
     */
    public function getStatistics(): array
    {
        $cacheKey = 'rag:reranker:stats';

        return $this->cache->remember($cacheKey, function () {
            return [
                'enabled' => $this->isRerankingEnabled(),
                'strategies' => ['semantic', 'cross_encoder', 'hybrid', 'mmr'],
                'default_strategy' => $this->config['reranking_strategy'] ?? 'semantic',
                'cache_hit_rate' => $this->cache->getHitRate('rerank'),
                'average_latency' => $this->cache->getAverageLatency('rerank'),
            ];
        }, 300); // Cache for 5 minutes
    }
}