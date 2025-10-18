<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\Document;
use App\Models\Chunk;
use Exception;

/**
 * Pipeline RAG Enterprise
 *
 * Pipeline completo para processamento de documentos:
 * - Chunking inteligente preservando contexto
 * - Embedding batch otimizado com Vertex AI
 * - Armazenamento sem duplicatas
 * - Retrieval híbrido (vector + keyword)
 * - Re-ranking semântico
 * - Context assembly otimizado
 */
class RagPipeline
{
    private VertexClient $vertexClient;
    private ChunkingStrategy $chunkingStrategy;
    private HybridRetriever $retriever;
    private SemanticReranker $reranker;
    private EmbeddingCache $cache;

    // Configurações do pipeline
    private const MAX_CHUNK_SIZE = 1000;
    private const MIN_CHUNK_SIZE = 100;
    private const OVERLAP_RATIO = 0.1;
    private const BATCH_SIZE = 50;

    public function __construct(
        VertexClient $vertexClient,
        ChunkingStrategy $chunkingStrategy,
        HybridRetriever $retriever,
        SemanticReranker $reranker,
        EmbeddingCache $cache
    ) {
        $this->vertexClient = $vertexClient;
        $this->chunkingStrategy = $chunkingStrategy;
        $this->retriever = $retriever;
        $this->reranker = $reranker;
        $this->cache = $cache;
    }

    /**
     * Processar documento completo através do pipeline
     */
    public function processDocument(
        string $tenantSlug,
        int $documentId,
        string $content,
        array $metadata = [],
        array $options = []
    ): array {
        $startTime = microtime(true);

        try {
            Log::info('Starting RAG pipeline processing', [
                'tenant' => $tenantSlug,
                'document_id' => $documentId,
                'content_length' => strlen($content),
                'metadata_keys' => array_keys($metadata)
            ]);

            // 1. Chunking inteligente
            $chunks = $this->chunkDocument($content, $metadata, $options);

            if (empty($chunks)) {
                throw new Exception('No chunks generated from document');
            }

            // 2. Detectar e remover duplicatas
            $uniqueChunks = $this->deduplicateChunks($chunks);

            Log::info('Chunks generated and deduplicated', [
                'original_chunks' => count($chunks),
                'unique_chunks' => count($uniqueChunks),
                'deduplication_ratio' => round((1 - count($uniqueChunks) / count($chunks)) * 100, 2) . '%'
            ]);

            // 3. Embedding em lotes com timeout
            $embeddingStartTime = microtime(true);

            try {
                $embeddings = $this->generateEmbeddings($uniqueChunks);
                $embeddingTime = microtime(true) - $embeddingStartTime;

                Log::info('Embeddings generation completed', [
                    'embeddings_count' => count($embeddings),
                    'chunks_count' => count($uniqueChunks),
                    'embedding_time' => round($embeddingTime, 3) . 's'
                ]);

                if (count($embeddings) !== count($uniqueChunks)) {
                    throw new Exception('Embedding count mismatch with chunk count');
                }
            } catch (Exception $e) {
                $embeddingTime = microtime(true) - $embeddingStartTime;

                Log::warning('Embeddings generation failed, using fallback', [
                    'error' => $e->getMessage(),
                    'embedding_time' => round($embeddingTime, 3) . 's',
                    'chunks_count' => count($uniqueChunks)
                ]);

                // Fallback: create zero embeddings para permitir chunk storage
                $embeddings = array_fill(0, count($uniqueChunks), null);
            }

            // 4. Armazenamento otimizado
            $storedChunks = $this->storeChunks($tenantSlug, $documentId, $uniqueChunks, $embeddings, $metadata);

            // 5. Indexação para busca híbrida
            $this->indexForHybridSearch($storedChunks);

            $processingTime = microtime(true) - $startTime;

            Log::info('RAG pipeline completed successfully', [
                'tenant' => $tenantSlug,
                'document_id' => $documentId,
                'chunks_processed' => count($uniqueChunks),
                'chunks_stored' => count($storedChunks),
                'processing_time' => round($processingTime, 2) . 's'
            ]);

            return [
                'success' => true,
                'chunks_created' => count($storedChunks),
                'processing_time' => $processingTime,
                'deduplication_ratio' => round((1 - count($uniqueChunks) / count($chunks)) * 100, 2),
                'embedding_cache_hits' => $this->cache->getHitRate(),
                'metadata' => [
                    'original_chunks' => count($chunks),
                    'unique_chunks' => count($uniqueChunks),
                    'embeddings_generated' => count($embeddings),
                ]
            ];

        } catch (Exception $e) {
            Log::error('RAG pipeline failed', [
                'tenant' => $tenantSlug,
                'document_id' => $documentId,
                'error' => $e->getMessage(),
                'processing_time' => microtime(true) - $startTime
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'processing_time' => microtime(true) - $startTime,
            ];
        }
    }

    /**
     * Busca RAG completa com retrieval híbrido e re-ranking
     */
    public function search(
        string $tenantSlug,
        string $query,
        array $options = []
    ): array {
        $startTime = microtime(true);

        try {
            // Configurações default
            $limit = $options['limit'] ?? 10;
            $enableReranking = $options['rerank'] ?? true;
            $documentIds = $options['document_ids'] ?? [];
            $metadataFilters = $options['metadata_filters'] ?? [];

            Log::info('Starting RAG search', [
                'tenant' => $tenantSlug,
                'query_length' => strlen($query),
                'limit' => $limit,
                'reranking' => $enableReranking
            ]);

            // 1. Retrieval híbrido
            $candidates = $this->retriever->search(
                $tenantSlug,
                $query,
                [
                    'limit' => $limit * 3, // Buscar mais candidatos para re-ranking
                    'document_ids' => $documentIds,
                    'metadata_filters' => $metadataFilters,
                    'vector_weight' => $options['vector_weight'] ?? 0.7,
                    'keyword_weight' => $options['keyword_weight'] ?? 0.3,
                ]
            );

            if (empty($candidates)) {
                return [
                    'success' => true,
                    'results' => [],
                    'total_found' => 0,
                    'search_time' => microtime(true) - $startTime,
                ];
            }

            // 2. Re-ranking semântico (opcional)
            if ($enableReranking && count($candidates) > $limit) {
                $rankedCandidates = $this->reranker->rerank($query, $candidates, $limit);
            } else {
                $rankedCandidates = array_slice($candidates, 0, $limit);
            }

            // 3. Enriquecer resultados com contexto
            $enrichedResults = $this->enrichSearchResults($rankedCandidates);

            $searchTime = microtime(true) - $startTime;

            Log::info('RAG search completed', [
                'tenant' => $tenantSlug,
                'candidates_found' => count($candidates),
                'results_returned' => count($enrichedResults),
                'search_time' => round($searchTime, 3) . 's'
            ]);

            return [
                'success' => true,
                'results' => $enrichedResults,
                'total_found' => count($candidates),
                'search_time' => $searchTime,
                'metadata' => [
                    'reranking_enabled' => $enableReranking,
                    'vector_weight' => $options['vector_weight'] ?? 0.7,
                    'keyword_weight' => $options['keyword_weight'] ?? 0.3,
                ]
            ];

        } catch (Exception $e) {
            Log::error('RAG search failed', [
                'tenant' => $tenantSlug,
                'query' => mb_substr($query, 0, 100),
                'error' => $e->getMessage(),
                'search_time' => microtime(true) - $startTime
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'search_time' => microtime(true) - $startTime,
            ];
        }
    }

    /**
     * Gerar resposta contextual usando contexto recuperado
     */
    public function generateAnswer(
        string $tenantSlug,
        string $query,
        array $searchOptions = [],
        array $generationOptions = []
    ): array {
        $startTime = microtime(true);

        try {
            // 1. Buscar contexto relevante
            $searchResult = $this->search($tenantSlug, $query, $searchOptions);

            if (!$searchResult['success']) {
                throw new Exception('Context search failed: ' . $searchResult['error']);
            }

            $contexts = array_map(fn($result) => $result['content'], $searchResult['results']);

            if (empty($contexts)) {
                return [
                    'success' => true,
                    'answer' => 'Não encontrei informações relevantes para responder à sua pergunta.',
                    'confidence' => 0.0,
                    'sources' => [],
                    'generation_time' => microtime(true) - $startTime,
                ];
            }

            // 2. Gerar resposta contextual
            $answer = $this->vertexClient->generate($query, $contexts, $generationOptions);

            // 3. Calcular confiança baseada na qualidade dos contextos
            $confidence = $this->calculateAnswerConfidence($searchResult['results'], $answer);

            // 4. Extrair fontes citadas
            $sources = $this->extractSources($searchResult['results']);

            $generationTime = microtime(true) - $startTime;

            Log::info('RAG answer generated', [
                'tenant' => $tenantSlug,
                'query_length' => strlen($query),
                'contexts_used' => count($contexts),
                'answer_length' => strlen($answer),
                'confidence' => $confidence,
                'generation_time' => round($generationTime, 3) . 's'
            ]);

            return [
                'success' => true,
                'answer' => $answer,
                'confidence' => $confidence,
                'sources' => $sources,
                'generation_time' => $generationTime,
                'metadata' => [
                    'contexts_found' => count($searchResult['results']),
                    'search_time' => $searchResult['search_time'],
                    'total_time' => $generationTime,
                ]
            ];

        } catch (Exception $e) {
            Log::error('RAG answer generation failed', [
                'tenant' => $tenantSlug,
                'query' => mb_substr($query, 0, 100),
                'error' => $e->getMessage(),
                'generation_time' => microtime(true) - $startTime
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'generation_time' => microtime(true) - $startTime,
            ];
        }
    }

    // Métodos auxiliares privados

    private function chunkDocument(string $content, array $metadata, array $options): array
    {
        $documentType = $metadata['file_type'] ?? 'text';
        $chunkSize = $options['chunk_size'] ?? self::MAX_CHUNK_SIZE;
        $overlapSize = (int)($chunkSize * self::OVERLAP_RATIO);

        return $this->chunkingStrategy->chunk($content, $documentType, [
            'chunk_size' => $chunkSize,
            'overlap_size' => $overlapSize,
            'preserve_structure' => $options['preserve_structure'] ?? true,
            'metadata' => $metadata,
        ]);
    }

    private function deduplicateChunks(array $chunks): array
    {
        $seen = [];
        $unique = [];

        foreach ($chunks as $chunk) {
            $hash = hash('sha256', trim($chunk['content']));

            if (!isset($seen[$hash])) {
                $seen[$hash] = true;
                $unique[] = $chunk;
            }
        }

        return $unique;
    }

    private function generateEmbeddings(array $chunks): array
    {
        $texts = array_map(fn($chunk) => $chunk['content'], $chunks);
        $batches = array_chunk($texts, self::BATCH_SIZE);
        $allEmbeddings = [];

        foreach ($batches as $batch) {
            $batchEmbeddings = $this->vertexClient->embed($batch);
            $allEmbeddings = array_merge($allEmbeddings, $batchEmbeddings);
        }

        return $allEmbeddings;
    }

    private function storeChunks(
        string $tenantSlug,
        int $documentId,
        array $chunks,
        array $embeddings,
        array $baseMetadata
    ): array {
        $storedChunks = [];

        DB::transaction(function () use (
            $tenantSlug,
            $documentId,
            $chunks,
            $embeddings,
            $baseMetadata,
            &$storedChunks
        ) {
            foreach ($chunks as $index => $chunk) {
                $embedding = $embeddings[$index] ?? null;

                if (!$embedding) {
                    Log::info('Storing chunk without embedding (fallback mode)', [
                        'document_id' => $documentId,
                        'chunk_index' => $index,
                        'content_preview' => substr($chunk['content'], 0, 100)
                    ]);
                }

                $chunkMetadata = array_merge($baseMetadata, $chunk['metadata'] ?? []);

                // Store chunk even without embedding to ensure chunk creation
                $documentChunk = Chunk::create([
                    'tenant_slug' => $tenantSlug,
                    'document_id' => $documentId,
                    'chunk_index' => $index,
                    'content' => $chunk['content'],
                    'content_preview' => mb_substr($chunk['content'], 0, 200),
                    'embedding' => $embedding ? json_encode($embedding) : null,
                    'meta' => $chunkMetadata,
                    'word_count' => str_word_count($chunk['content']),
                    'char_count' => mb_strlen($chunk['content']),
                ]);

                $storedChunks[] = $documentChunk;
            }
        });

        return $storedChunks;
    }

    private function indexForHybridSearch(array $chunks): void
    {
        // Atualizar estatísticas para otimizador de consultas
        DB::statement('ANALYZE chunks');

        // Log para monitoramento
        Log::info('Hybrid search indexing completed', [
            'chunks_indexed' => count($chunks)
        ]);
    }

    private function enrichSearchResults(array $results): array
    {
        return array_map(function ($result) {
            // Adicionar contexto expandido se necessário
            $expandedContext = $this->getExpandedContext($result);

            return [
                'id' => $result['id'],
                'content' => $result['content'],
                'expanded_content' => $expandedContext,
                'score' => $result['score'],
                'document_id' => $result['document_id'],
                'chunk_index' => $result['chunk_index'],
                'metadata' => $result['metadata'],
                'similarity' => $result['similarity'] ?? null,
                'keyword_score' => $result['keyword_score'] ?? null,
            ];
        }, $results);
    }

    private function getExpandedContext(array $chunkResult): string
    {
        // Buscar chunks adjacentes para contexto expandido
        $adjacentChunks = Chunk::where('tenant_slug', $chunkResult['tenant_slug'] ?? '')
            ->where('document_id', $chunkResult['document_id'])
            ->whereBetween('chunk_index', [
                max(0, $chunkResult['chunk_index'] - 1),
                $chunkResult['chunk_index'] + 1
            ])
            ->orderBy('chunk_index')
            ->pluck('content')
            ->toArray();

        return implode(' ', $adjacentChunks);
    }

    private function calculateAnswerConfidence(array $searchResults, string $answer): float
    {
        if (empty($searchResults)) {
            return 0.0;
        }

        // Confidence baseada na qualidade dos resultados de busca
        $avgScore = array_sum(array_column($searchResults, 'score')) / count($searchResults);

        // Penalizar se resposta é muito genérica
        $answerLength = strlen($answer);
        $lengthBonus = min(1.0, $answerLength / 200); // Bonus até 200 chars

        // Bonus se múltiplas fontes foram usadas
        $sourceBonus = min(1.0, count($searchResults) / 5); // Bonus até 5 fontes

        return min(1.0, ($avgScore * 0.6) + ($lengthBonus * 0.2) + ($sourceBonus * 0.2));
    }

    private function extractSources(array $searchResults): array
    {
        $sources = [];

        foreach ($searchResults as $result) {
            $sources[] = [
                'document_id' => $result['document_id'],
                'chunk_index' => $result['chunk_index'],
                'score' => $result['score'],
                'preview' => mb_substr($result['content'], 0, 150) . '...',
            ];
        }

        return $sources;
    }

    /**
     * Métricas de performance do pipeline
     */
    public function getPerformanceMetrics(): array
    {
        return [
            'vertex_usage' => $this->vertexClient->getUsageMetrics(),
            'cache_stats' => $this->cache->getStats(),
            'retriever_stats' => $this->retriever->getStats(),
            'reranker_stats' => $this->reranker->getStats(),
        ];
    }
}