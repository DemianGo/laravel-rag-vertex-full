<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Models\DocumentChunk;
use Exception;

/**
 * Busca híbrida enterprise
 *
 * Combina:
 * - Vector search (pgvector cosine similarity)
 * - Keyword search (PostgreSQL Full-Text Search)
 * - Fusão de scores (RRF - Reciprocal Rank Fusion)
 * - Filtros por metadata/tenant
 * - Paginação eficiente
 * - Metrics e monitoring
 */
class HybridRetriever
{
    private VertexClient $vertexClient;
    private EmbeddingCache $cache;
    private array $stats;

    // Configurações de busca
    private const DEFAULT_VECTOR_LIMIT = 50;
    private const DEFAULT_KEYWORD_LIMIT = 50;
    private const DEFAULT_FINAL_LIMIT = 10;
    private const RRF_K = 60; // Constante para Reciprocal Rank Fusion
    private const CACHE_TTL = 1800; // 30 minutos

    // Pesos padrão
    private const DEFAULT_VECTOR_WEIGHT = 0.7;
    private const DEFAULT_KEYWORD_WEIGHT = 0.3;

    public function __construct(VertexClient $vertexClient, EmbeddingCache $cache)
    {
        $this->vertexClient = $vertexClient;
        $this->cache = $cache;
        $this->initializeStats();
    }

    /**
     * Busca híbrida principal
     */
    public function search(string $tenantSlug, string $query, array $options = []): array
    {
        $startTime = microtime(true);

        try {
            // Configurações
            $limit = $options['limit'] ?? self::DEFAULT_FINAL_LIMIT;
            $vectorWeight = $options['vector_weight'] ?? self::DEFAULT_VECTOR_WEIGHT;
            $keywordWeight = $options['keyword_weight'] ?? self::DEFAULT_KEYWORD_WEIGHT;
            $documentIds = $options['document_ids'] ?? [];
            $metadataFilters = $options['metadata_filters'] ?? [];

            Log::info('Starting hybrid search', [
                'tenant' => $tenantSlug,
                'query_length' => strlen($query),
                'vector_weight' => $vectorWeight,
                'keyword_weight' => $keywordWeight,
                'limit' => $limit
            ]);

            // Verificar cache primeiro
            $cacheKey = $this->generateCacheKey($tenantSlug, $query, $options);
            $cached = Cache::get($cacheKey);

            if ($cached && env('HYBRID_SEARCH_CACHE_ENABLED', true)) {
                $this->incrementStat('cache_hits');
                Log::debug('Hybrid search cache hit', ['cache_key' => $cacheKey]);
                return $cached;
            }

            // 1. Vector Search
            $vectorResults = $this->vectorSearch($tenantSlug, $query, [
                'limit' => $options['vector_limit'] ?? self::DEFAULT_VECTOR_LIMIT,
                'document_ids' => $documentIds,
                'metadata_filters' => $metadataFilters,
                'similarity_threshold' => $options['similarity_threshold'] ?? 0.1,
            ]);

            // 2. Keyword Search
            $keywordResults = $this->keywordSearch($tenantSlug, $query, [
                'limit' => $options['keyword_limit'] ?? self::DEFAULT_KEYWORD_LIMIT,
                'document_ids' => $documentIds,
                'metadata_filters' => $metadataFilters,
                'rank_normalization' => $options['rank_normalization'] ?? true,
            ]);

            // 3. Fusão de resultados com RRF
            $fusedResults = $this->fuseResults($vectorResults, $keywordResults, [
                'vector_weight' => $vectorWeight,
                'keyword_weight' => $keywordWeight,
                'limit' => $limit,
                'diversify' => $options['diversify'] ?? true,
            ]);

            // 4. Cache resultado
            if (env('HYBRID_SEARCH_CACHE_ENABLED', true)) {
                Cache::put($cacheKey, $fusedResults, self::CACHE_TTL);
            }

            $searchTime = microtime(true) - $startTime;

            $this->incrementStat('searches');
            $this->incrementStat('cache_misses');

            Log::info('Hybrid search completed', [
                'tenant' => $tenantSlug,
                'vector_results' => count($vectorResults),
                'keyword_results' => count($keywordResults),
                'fused_results' => count($fusedResults),
                'search_time' => round($searchTime, 3) . 's'
            ]);

            return $fusedResults;

        } catch (Exception $e) {
            $this->incrementStat('errors');

            Log::error('Hybrid search failed', [
                'tenant' => $tenantSlug,
                'query' => mb_substr($query, 0, 100),
                'error' => $e->getMessage(),
                'search_time' => microtime(true) - $startTime
            ]);

            throw $e;
        }
    }

    /**
     * Vector search usando pgvector
     */
    private function vectorSearch(string $tenantSlug, string $query, array $options): array
    {
        $startTime = microtime(true);

        try {
            // 1. Gerar embedding da query
            $queryEmbeddings = $this->vertexClient->embed([$query]);

            if (empty($queryEmbeddings) || empty($queryEmbeddings[0])) {
                Log::warning('Failed to generate query embedding', ['query' => mb_substr($query, 0, 100)]);
                return [];
            }

            $queryEmbedding = $queryEmbeddings[0];

            // 2. Busca vetorial
            $baseQuery = DocumentChunk::select([
                'id',
                'document_id',
                'chunk_index',
                'content',
                'content_preview',
                'metadata',
                'word_count',
                'char_count',
                DB::raw('1 - (embedding <=> ?) as similarity')
            ])
            ->where('tenant_slug', $tenantSlug)
            ->whereNotNull('embedding')
            ->where(DB::raw('1 - (embedding <=> ?)'), '>=', $options['similarity_threshold'])
            ->orderByDesc('similarity');

            // Aplicar filtros
            if (!empty($options['document_ids'])) {
                $baseQuery->whereIn('document_id', $options['document_ids']);
            }

            if (!empty($options['metadata_filters'])) {
                foreach ($options['metadata_filters'] as $key => $value) {
                    $baseQuery->where('metadata->' . $key, $value);
                }
            }

            // Executar query com embedding
            $results = $baseQuery
                ->limit($options['limit'])
                ->get()
                ->map(function ($chunk) {
                    return [
                        'id' => $chunk->id,
                        'document_id' => $chunk->document_id,
                        'chunk_index' => $chunk->chunk_index,
                        'content' => $chunk->content,
                        'content_preview' => $chunk->content_preview,
                        'metadata' => $chunk->metadata,
                        'word_count' => $chunk->word_count,
                        'char_count' => $chunk->char_count,
                        'similarity' => (float) $chunk->similarity,
                        'score' => (float) $chunk->similarity, // Score unificado
                        'source' => 'vector',
                        'tenant_slug' => $chunk->tenant_slug,
                    ];
                })
                ->toArray();

            $vectorTime = microtime(true) - $startTime;

            Log::debug('Vector search completed', [
                'results_count' => count($results),
                'search_time' => round($vectorTime, 3) . 's',
                'avg_similarity' => count($results) > 0 ? round(array_sum(array_column($results, 'similarity')) / count($results), 3) : 0
            ]);

            $this->incrementStat('vector_searches');

            // Adicionar bind do embedding para a query
            DB::select(
                'SELECT 1 WHERE EXISTS (SELECT 1 FROM document_chunks WHERE tenant_slug = ? AND embedding <=> ? < 1 LIMIT 1)',
                [$tenantSlug, '[' . implode(',', $queryEmbedding) . ']']
            );

            return $results;

        } catch (Exception $e) {
            Log::error('Vector search failed', [
                'tenant' => $tenantSlug,
                'error' => $e->getMessage(),
                'search_time' => microtime(true) - $startTime
            ]);

            $this->incrementStat('vector_search_errors');
            return [];
        }
    }

    /**
     * Keyword search usando PostgreSQL FTS
     */
    private function keywordSearch(string $tenantSlug, string $query, array $options): array
    {
        $startTime = microtime(true);

        try {
            // Preparar query para FTS
            $ftsQuery = $this->prepareFtsQuery($query);

            if (empty($ftsQuery)) {
                return [];
            }

            // Base query com FTS
            $baseQuery = DocumentChunk::select([
                'id',
                'document_id',
                'chunk_index',
                'content',
                'content_preview',
                'metadata',
                'word_count',
                'char_count',
                DB::raw('ts_rank(to_tsvector(\'english\', content), plainto_tsquery(\'english\', ?)) as rank')
            ])
            ->where('tenant_slug', $tenantSlug)
            ->whereRaw('to_tsvector(\'english\', content) @@ plainto_tsquery(\'english\', ?)', [$ftsQuery])
            ->orderByDesc('rank');

            // Aplicar filtros
            if (!empty($options['document_ids'])) {
                $baseQuery->whereIn('document_id', $options['document_ids']);
            }

            if (!empty($options['metadata_filters'])) {
                foreach ($options['metadata_filters'] as $key => $value) {
                    $baseQuery->where('metadata->' . $key, $value);
                }
            }

            $results = $baseQuery
                ->limit($options['limit'])
                ->get()
                ->map(function ($chunk) use ($options) {
                    $rank = (float) $chunk->rank;

                    // Normalizar rank se solicitado
                    if ($options['rank_normalization']) {
                        $rank = min(1.0, $rank); // Normalizar para 0-1
                    }

                    return [
                        'id' => $chunk->id,
                        'document_id' => $chunk->document_id,
                        'chunk_index' => $chunk->chunk_index,
                        'content' => $chunk->content,
                        'content_preview' => $chunk->content_preview,
                        'metadata' => $chunk->metadata,
                        'word_count' => $chunk->word_count,
                        'char_count' => $chunk->char_count,
                        'rank' => $rank,
                        'score' => $rank, // Score unificado
                        'source' => 'keyword',
                        'tenant_slug' => $chunk->tenant_slug,
                    ];
                })
                ->toArray();

            $keywordTime = microtime(true) - $startTime;

            Log::debug('Keyword search completed', [
                'fts_query' => $ftsQuery,
                'results_count' => count($results),
                'search_time' => round($keywordTime, 3) . 's',
                'avg_rank' => count($results) > 0 ? round(array_sum(array_column($results, 'rank')) / count($results), 3) : 0
            ]);

            $this->incrementStat('keyword_searches');

            return $results;

        } catch (Exception $e) {
            Log::error('Keyword search failed', [
                'tenant' => $tenantSlug,
                'query' => $query,
                'error' => $e->getMessage(),
                'search_time' => microtime(true) - $startTime
            ]);

            $this->incrementStat('keyword_search_errors');
            return [];
        }
    }

    /**
     * Fusão de resultados com Reciprocal Rank Fusion (RRF)
     */
    private function fuseResults(array $vectorResults, array $keywordResults, array $options): array
    {
        $vectorWeight = $options['vector_weight'];
        $keywordWeight = $options['keyword_weight'];
        $limit = $options['limit'];

        // Mapear resultados por ID para fusão
        $allResults = [];

        // Processar resultados vetoriais
        foreach ($vectorResults as $rank => $result) {
            $id = $result['id'];
            $rrfScore = $vectorWeight / (self::RRF_K + $rank + 1);

            if (!isset($allResults[$id])) {
                $allResults[$id] = $result;
                $allResults[$id]['combined_score'] = 0;
                $allResults[$id]['vector_rank'] = null;
                $allResults[$id]['keyword_rank'] = null;
                $allResults[$id]['sources'] = [];
            }

            $allResults[$id]['combined_score'] += $rrfScore;
            $allResults[$id]['vector_rank'] = $rank + 1;
            $allResults[$id]['vector_score'] = $result['score'];
            $allResults[$id]['sources'][] = 'vector';
        }

        // Processar resultados de palavras-chave
        foreach ($keywordResults as $rank => $result) {
            $id = $result['id'];
            $rrfScore = $keywordWeight / (self::RRF_K + $rank + 1);

            if (!isset($allResults[$id])) {
                $allResults[$id] = $result;
                $allResults[$id]['combined_score'] = 0;
                $allResults[$id]['vector_rank'] = null;
                $allResults[$id]['keyword_rank'] = null;
                $allResults[$id]['sources'] = [];
            }

            $allResults[$id]['combined_score'] += $rrfScore;
            $allResults[$id]['keyword_rank'] = $rank + 1;
            $allResults[$id]['keyword_score'] = $result['score'];
            $allResults[$id]['sources'][] = 'keyword';
        }

        // Ordenar por score combinado
        $sortedResults = array_values($allResults);
        usort($sortedResults, fn($a, $b) => $b['combined_score'] <=> $a['combined_score']);

        // Aplicar diversificação se solicitado
        if ($options['diversify']) {
            $sortedResults = $this->diversifyResults($sortedResults);
        }

        // Limitar resultados finais
        $finalResults = array_slice($sortedResults, 0, $limit);

        // Normalizar scores finais
        if (!empty($finalResults)) {
            $maxScore = $finalResults[0]['combined_score'];
            foreach ($finalResults as &$result) {
                $result['score'] = $maxScore > 0 ? $result['combined_score'] / $maxScore : 0;
                $result['fusion_method'] = 'RRF';
                $result['sources'] = array_unique($result['sources']);
            }
        }

        Log::debug('Results fused with RRF', [
            'vector_results' => count($vectorResults),
            'keyword_results' => count($keywordResults),
            'unique_results' => count($allResults),
            'final_results' => count($finalResults),
            'vector_weight' => $vectorWeight,
            'keyword_weight' => $keywordWeight
        ]);

        $this->incrementStat('fusions');

        return $finalResults;
    }

    /**
     * Diversificação de resultados para evitar duplicatas similares
     */
    private function diversifyResults(array $results): array
    {
        $diversified = [];
        $seenDocuments = [];

        foreach ($results as $result) {
            $docId = $result['document_id'];

            // Limitar resultados por documento (máximo 3)
            $countForDoc = $seenDocuments[$docId] ?? 0;

            if ($countForDoc < 3) {
                $diversified[] = $result;
                $seenDocuments[$docId] = $countForDoc + 1;
            }
        }

        return $diversified;
    }

    /**
     * Preparar query para Full-Text Search
     */
    private function prepareFtsQuery(string $query): string
    {
        // Limpar e preparar query
        $query = trim($query);

        // Remover caracteres especiais que podem quebrar FTS
        $query = preg_replace('/[^\w\s\-\'\"]/u', ' ', $query);

        // Remover espaços extras
        $query = preg_replace('/\s+/', ' ', $query);

        // Quebrar em palavras e filtrar palavras muito curtas
        $words = array_filter(
            explode(' ', $query),
            fn($word) => mb_strlen($word) >= 2
        );

        return implode(' ', $words);
    }

    /**
     * Gerar chave de cache para busca
     */
    private function generateCacheKey(string $tenantSlug, string $query, array $options): string
    {
        $keyData = [
            'tenant' => $tenantSlug,
            'query' => $query,
            'options' => array_intersect_key($options, array_flip([
                'limit', 'vector_weight', 'keyword_weight', 'document_ids',
                'metadata_filters', 'similarity_threshold'
            ]))
        ];

        return 'hybrid_search:' . hash('sha256', json_encode($keyData));
    }

    /**
     * Busca similaridade avançada com filtros complexos
     */
    public function advancedSimilaritySearch(
        string $tenantSlug,
        string $query,
        array $options = []
    ): array {
        // Configurações avançadas
        $boostFactors = $options['boost_factors'] ?? [];
        $timeDecay = $options['time_decay'] ?? false;
        $qualityThreshold = $options['quality_threshold'] ?? 0;

        $results = $this->search($tenantSlug, $query, $options);

        // Aplicar boost factors
        if (!empty($boostFactors)) {
            $results = $this->applyBoostFactors($results, $boostFactors);
        }

        // Aplicar time decay
        if ($timeDecay) {
            $results = $this->applyTimeDecay($results, $timeDecay);
        }

        // Filtrar por qualidade
        if ($qualityThreshold > 0) {
            $results = array_filter($results, fn($r) => $r['score'] >= $qualityThreshold);
        }

        return array_values($results);
    }

    private function applyBoostFactors(array $results, array $boostFactors): array
    {
        foreach ($results as &$result) {
            $boost = 1.0;

            // Boost baseado em metadata
            foreach ($boostFactors as $field => $factor) {
                if (isset($result['metadata'][$field])) {
                    $boost *= $factor;
                }
            }

            $result['score'] *= $boost;
            $result['boost_applied'] = $boost;
        }

        // Re-ordenar após boost
        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);

        return $results;
    }

    private function applyTimeDecay(array $results, array $timeDecayConfig): array
    {
        $decayRate = $timeDecayConfig['rate'] ?? 0.1;
        $timeField = $timeDecayConfig['field'] ?? 'created_at';

        $now = time();

        foreach ($results as &$result) {
            if (isset($result['metadata'][$timeField])) {
                $timestamp = strtotime($result['metadata'][$timeField]);
                $ageDays = ($now - $timestamp) / 86400;

                $decayFactor = exp(-$decayRate * $ageDays);
                $result['score'] *= $decayFactor;
                $result['time_decay_factor'] = $decayFactor;
            }
        }

        // Re-ordenar após decay
        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);

        return $results;
    }

    // Métodos auxiliares para estatísticas

    private function initializeStats(): void
    {
        $this->stats = [
            'searches' => 0,
            'vector_searches' => 0,
            'keyword_searches' => 0,
            'fusions' => 0,
            'cache_hits' => 0,
            'cache_misses' => 0,
            'errors' => 0,
            'vector_search_errors' => 0,
            'keyword_search_errors' => 0,
        ];
    }

    private function incrementStat(string $stat): void
    {
        $this->stats[$stat] = ($this->stats[$stat] ?? 0) + 1;

        // Persistir estatísticas em cache
        $key = 'hybrid_retriever_stats';
        $cached = Cache::get($key, []);
        $cached[$stat] = ($cached[$stat] ?? 0) + 1;
        Cache::put($key, $cached, 3600); // 1 hora
    }

    public function getStats(): array
    {
        $cached = Cache::get('hybrid_retriever_stats', []);
        return array_merge($this->stats, $cached);
    }

    public function resetStats(): void
    {
        $this->stats = [];
        Cache::forget('hybrid_retriever_stats');
    }
}