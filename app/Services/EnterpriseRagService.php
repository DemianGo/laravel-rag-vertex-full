<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class EnterpriseRagService
{
    private $baseUrl = 'http://127.0.0.1:8000/api';
    private $timeout = 30;

    public function performAdvancedQuery(array $params): array
    {
        $user = $params['user'];
        $query = $params['query'];
        $document = $params['document'];
        $plan = $user->plan;

        // Log which document is being used for debugging
        Log::info('Enterprise RAG Document Selected', [
            'document_id' => $document->id,
            'document_title' => $document->title,
            'created_at' => $document->created_at,
            'query' => $query,
            'plan' => $plan
        ]);

        // PHASE 1: Query Enhancement & Context Building
        $enhancedQueries = $this->buildEnhancedQueries($query);
        // Use the document's actual tenant_slug instead of user email for bypass documents
        $tenantSlug = $document->tenant_slug ?? $user->email;
        $searchParams = $this->buildSearchParams($query, $document->id, $tenantSlug, $plan);

        // PHASE 2: Multi-endpoint Search Strategy
        $results = $this->executeSearchStrategy($enhancedQueries, $searchParams, $plan);

        // PHASE 3: Result Processing & Ranking
        if (!empty($results)) {
            return $this->processResults($results, $query, $document, $plan);
        }

        // PHASE 4: Intelligent Fallback
        return $this->handleNoResults($query, $document, $searchParams);
    }

    private function buildEnhancedQueries(string $query): array
    {
        $queries = [$query];
        $lowerQuery = strtolower($query);

        // Context-aware query expansion
        $expansions = [
            'motivo' => ['razões', 'benefícios', 'vantagens', 'lista motivos'],
            'contrato' => ['cláusula', 'obrigações', 'termos', 'acordo'],
            'declara' => ['responsabilidade', 'compromisso', 'afirma'],
            'suporte' => ['atendimento', 'help', 'assistência', 'apoio'],
            'preço' => ['valor', 'custo', 'investimento', 'pagamento'],
            'prazo' => ['tempo', 'período', 'cronograma', 'duração'],
            'produto' => ['solução', 'serviço', 'oferece', 'disponível']
        ];

        foreach ($expansions as $keyword => $synonyms) {
            if (str_contains($lowerQuery, $keyword)) {
                foreach ($synonyms as $synonym) {
                    $queries[] = str_replace($keyword, $synonym, $lowerQuery);
                    $queries[] = $query . ' ' . $synonym;
                }
                break; // Use apenas a primeira expansão encontrada
            }
        }

        // Remove duplicatas e limita queries
        return array_unique(array_slice($queries, 0, 5));
    }

    private function buildSearchParams(string $query, int $docId, string $tenant, string $plan): array
    {
        $baseParams = [
            'tenant_slug' => $tenant,
            'document_id' => $docId,
        ];

        // Plan-based configurations
        switch ($plan) {
            case 'enterprise':
                return array_merge($baseParams, [
                    'top_k' => 15,
                    'similarity_threshold' => 0.03,
                    'enable_reranking' => true,
                    'max_tokens' => 4096,
                    'temperature' => 0.1,
                    'citations' => true
                ]);
            case 'pro':
                return array_merge($baseParams, [
                    'top_k' => 10,
                    'similarity_threshold' => 0.05,
                    'enable_reranking' => true,
                    'max_tokens' => 2048,
                    'citations' => true
                ]);
            default: // free
                return array_merge($baseParams, [
                    'top_k' => 5,
                    'similarity_threshold' => 0.08,
                    'max_tokens' => 1024
                ]);
        }
    }

    private function executeSearchStrategy(array $queries, array $baseParams, string $plan): array
    {
        $allResults = [];
        $documentId = $baseParams['document_id'] ?? null;

        // PHASE 1: Fast check for bypass chunks (without embeddings)
        $hasBypassChunks = $documentId ? $this->detectBypassChunks($documentId) : false;

        Log::info('Enterprise RAG Strategy - Fast Mode', [
            'document_id' => $documentId,
            'has_bypass_chunks' => $hasBypassChunks,
            'query_count' => count($queries)
        ]);

        // PHASE 2: Fast execution - use original query first
        $primaryQuery = $queries[0] ?? '';

        if ($hasBypassChunks) {
            // For bypass documents, use textual search directly with original query
            Log::info('Using fast textual search for bypass document');
            $results = $this->tryTextualSearch($primaryQuery, $baseParams);

            if (!empty($results)) {
                $allResults = $results;
            } else {
                // Only try second query if first completely fails
                if (count($queries) > 1) {
                    $results = $this->tryTextualSearch($queries[1], $baseParams);
                    $allResults = $results;
                }
            }
        } else {
            // For normal documents, use hybrid search with original query
            Log::info('Using fast hybrid search for normal document');
            $results = $this->tryBasicQuery($primaryQuery, $baseParams);

            if (!empty($results)) {
                $allResults = $results;
            } else {
                // Only try second query if first completely fails
                if (count($queries) > 1) {
                    $results = $this->tryBasicQuery($queries[1], $baseParams);
                    $allResults = $results;
                }
            }
        }

        Log::info('Fast search completed', [
            'results_found' => count($allResults),
            'execution_method' => $hasBypassChunks ? 'textual' : 'hybrid'
        ]);

        return $allResults;
    }

    private function tryGenerateAnswer(string $query, array $params): array
    {
        try {
            $response = Http::timeout($this->timeout)->post("{$this->baseUrl}/rag/generate-answer", array_merge($params, [
                'q' => $query,
                'model' => 'gemini-1.5-flash',
                'temperature' => $params['temperature'] ?? 0.1,
                'max_tokens' => $params['max_tokens'] ?? 2048,
                'citations' => $params['citations'] ?? false
            ]));

            if ($response->successful()) {
                $data = $response->json();
                if (!empty($data['answer'])) {
                    return [[
                        'type' => 'generated',
                        'content' => $data['answer'],
                        'sources' => $data['sources'] ?? [],
                        'confidence' => $data['confidence'] ?? 0,
                        'method' => 'generate-answer'
                    ]];
                }
            }
        } catch (\Exception $e) {
            Log::warning('Generate answer failed', ['error' => $e->getMessage()]);
        }

        return [];
    }

    private function tryHybridSearch(string $query, array $params): array
    {
        try {
            // First get results from basic query
            $searchResponse = Http::timeout(15)->get("{$this->baseUrl}/rag/query", array_merge($params, [
                'q' => $query,
                'top_k' => $params['top_k'] ?? 10
            ]));

            if ($searchResponse->successful()) {
                $searchData = $searchResponse->json();
                if (!empty($searchData['results']) && isset($params['enable_reranking'])) {
                    // Try reranking if available (Enterprise feature simulation)
                    return $this->rerankResults($searchData['results'], $query);
                }
                return $this->formatBasicResults($searchData['results'] ?? []);
            }
        } catch (\Exception $e) {
            Log::warning('Hybrid search failed', ['error' => $e->getMessage()]);
        }

        return [];
    }

    private function tryBasicQuery(string $query, array $params): array
    {
        try {
            $finalParams = array_merge($params, [
                'q' => $query,
                'top_k' => $params['top_k'] ?? 5
            ]);

            $url = "{$this->baseUrl}/rag/query";

            // DETAILED DEBUG LOGGING
            Log::info('API Call Debug - Basic Query', [
                'url' => $url,
                'params' => $finalParams,
                'query' => $query,
                'document_id' => $params['document_id'] ?? 'missing',
                'tenant_slug' => $params['tenant_slug'] ?? 'missing'
            ]);

            $response = Http::timeout(15)->get($url, $finalParams);

            if ($response->successful()) {
                $data = $response->json();

                Log::info('API Response Debug', [
                    'status' => $response->status(),
                    'successful' => $response->successful(),
                    'body_preview' => substr($response->body(), 0, 500),
                    'results_count' => isset($data['results']) ? count($data['results']) : 'no results key',
                    'actual_results' => count($data['results'] ?? [])
                ]);

                return $this->formatBasicResults($data['results'] ?? []);
            } else {
                Log::warning('API Response Failed', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Basic query failed with exception', [
                'error' => $e->getMessage(),
                'query' => $query,
                'params' => $params
            ]);
        }

        return [];
    }

    private function rerankResults(array $results, string $query): array
    {
        // Simulate semantic reranking by score and query relevance
        usort($results, function($a, $b) use ($query) {
            $scoreA = $this->calculateRelevanceScore($a, $query);
            $scoreB = $this->calculateRelevanceScore($b, $query);
            return $scoreB <=> $scoreA;
        });

        return $this->formatBasicResults($results);
    }

    private function calculateRelevanceScore(array $result, string $query): float
    {
        $content = strtolower($result['content'] ?? '');
        $queryWords = explode(' ', strtolower($query));
        $score = $result['score'] ?? 0;

        // Boost score based on query word matches
        foreach ($queryWords as $word) {
            if (strlen($word) > 2 && str_contains($content, $word)) {
                $score += 0.1;
            }
        }

        return $score;
    }

    private function formatBasicResults(array $results): array
    {
        return array_map(function($result) {
            return [
                'type' => 'search',
                'content' => $result['content'] ?? '',
                'score' => $result['score'] ?? 0,
                'id' => $result['id'] ?? null,
                'method' => 'search'
            ];
        }, $results);
    }

    private function processResults(array $results, string $query, $document, string $plan): array
    {
        // Sort by score/confidence
        usort($results, function($a, $b) {
            $scoreA = $a['confidence'] ?? $a['score'] ?? 0;
            $scoreB = $b['confidence'] ?? $b['score'] ?? 0;
            return $scoreB <=> $scoreA;
        });

        // Format response based on result type
        if ($results[0]['type'] === 'generated') {
            return [
                'success' => true,
                'answer' => $this->formatGeneratedAnswer($results[0], $document, $plan),
                'type' => 'generated',
                'sources' => $results[0]['sources'] ?? [],
                'confidence' => $results[0]['confidence'] ?? 0,
                'method' => $results[0]['method']
            ];
        } else {
            return [
                'success' => true,
                'answer' => $this->formatSearchResults($results, $document, $query, $plan),
                'type' => 'search',
                'sources' => array_slice($results, 0, 3),
                'results_count' => count($results),
                'method' => $results[0]['method'] ?? 'search'
            ];
        }
    }

    private function formatGeneratedAnswer(array $result, $document, string $plan): string
    {
        $answer = "**Resposta Baseada em '{$document->title}'**:\n\n";
        $answer .= $result['content'];

        if ($plan !== 'free' && !empty($result['sources'])) {
            $answer .= "\n\n**Fontes:**\n";
            foreach (array_slice($result['sources'], 0, 3) as $i => $source) {
                $answer .= ($i + 1) . ". " . (substr($source, 0, 100) . "...") . "\n";
            }
        }

        return $answer;
    }

    private function formatSearchResults(array $results, $document, string $query, string $plan): string
    {
        $answer = "**Encontrei as seguintes informações no documento '{$document->title}'**:\n\n";

        $topResults = array_slice($results, 0, $plan === 'enterprise' ? 5 : ($plan === 'pro' ? 3 : 2));

        foreach ($topResults as $i => $result) {
            $answer .= "**" . ($i + 1) . ".** " . trim($result['content']) . "\n\n";

            if ($plan !== 'free' && isset($result['score'])) {
                $answer .= "*Relevância: " . number_format($result['score'] * 100, 1) . "%*\n\n";
            }
        }

        return trim($answer);
    }

    private function handleNoResults(string $query, $document, array $params): array
    {
        // Analyze document content for suggestions
        $chunks = DB::table('chunks')
            ->where('document_id', $document->id)
            ->limit(5)
            ->pluck('content');

        $docContent = $chunks->implode(' ');
        $wordCount = str_word_count($docContent);
        $preview = substr($docContent, 0, 200);

        // Extract meaningful keywords (longer than 4 chars)
        $words = str_word_count(strtolower($preview), 1);
        $keywords = array_filter(array_unique($words), function($word) {
            return strlen($word) > 4;
        });
        $keywordsSample = implode(', ', array_slice($keywords, 0, 8));

        return [
            'success' => false,
            'answer' => "**Não encontrei informações específicas sobre '{$query}' no documento '{$document->title}'.**\n\n" .
                       "**Detalhes do documento:**\n" .
                       "• Palavras: ~{$wordCount}\n" .
                       "• Conteúdo inicial: {$preview}...\n\n" .
                       "**Sugestões para melhores resultados:**\n" .
                       "• Tente termos específicos: {$keywordsSample}\n" .
                       "• Use perguntas diretas sobre o conteúdo\n" .
                       "• Procure por tópicos principais do documento",
            'suggestions' => [
                'Use palavras-chave específicas do documento',
                'Faça perguntas sobre seções específicas',
                'Tente sinônimos dos termos buscados',
                'Pergunte sobre conceitos gerais primeiro'
            ],
            'document_info' => [
                'title' => $document->title,
                'word_count' => $wordCount,
                'keywords_found' => $keywordsSample
            ]
        ];
    }

    public function logSearchAttempt(array $params): void
    {
        Log::info('Enterprise RAG Search', [
            'query' => $params['query'],
            'document_id' => $params['document']->id ?? null,
            'document_title' => $params['document']->title ?? null,
            'user_plan' => $params['user']->plan ?? 'unknown',
            'tenant' => $params['user']->email ?? 'unknown',
            'timestamp' => now()
        ]);
    }

    /**
     * Detect if document has chunks without embeddings (bypass chunks)
     */
    private function detectBypassChunks(int $documentId): bool
    {
        try {
            // Check if document has chunks with null embeddings
            $bypassChunks = DB::table('chunks')
                ->where('document_id', $documentId)
                ->whereNull('embedding')
                ->count();

            $totalChunks = DB::table('chunks')
                ->where('document_id', $documentId)
                ->count();

            // If more than 50% of chunks don't have embeddings, consider it a bypass document
            $bypassRatio = $totalChunks > 0 ? ($bypassChunks / $totalChunks) : 0;

            Log::info('Bypass chunks detection', [
                'document_id' => $documentId,
                'bypass_chunks' => $bypassChunks,
                'total_chunks' => $totalChunks,
                'bypass_ratio' => round($bypassRatio, 2),
                'is_bypass' => $bypassRatio > 0.5
            ]);

            return $bypassRatio > 0.5;

        } catch (\Exception $e) {
            Log::warning('Error detecting bypass chunks', [
                'document_id' => $documentId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Textual search fallback for bypass documents
     */
    private function tryTextualSearch(string $query, array $params): array
    {
        try {
            $documentId = $params['document_id'] ?? null;
            $tenantSlug = $params['tenant_slug'] ?? null;

            if (!$documentId || !$tenantSlug) {
                return [];
            }

            Log::info('Fast textual search started', [
                'query' => $query,
                'document_id' => $documentId,
                'tenant_slug' => $tenantSlug
            ]);

            // Use SimpleUploadService for robust textual search
            $simpleService = app(\App\Services\SimpleUploadService::class);
            $searchResult = $simpleService->searchBypassDocuments($query, $tenantSlug, [
                'limit' => 10,
                'document_id' => $documentId,
                'threshold' => 0.05
            ]);

            if ($searchResult['success'] && !empty($searchResult['results'])) {
                $formattedResults = array_map(function($result) {
                    return [
                        'id' => $result['id'],
                        'content' => $result['content'],
                        'score' => $result['score'],
                        'method' => 'textual_search',
                        'type' => 'search'
                    ];
                }, $searchResult['results']);

                Log::info('Fast textual search succeeded', [
                    'results_count' => count($formattedResults)
                ]);

                return $formattedResults;
            }

            return [];

        } catch (\Exception $e) {
            Log::error('Fast textual search failed', [
                'query' => $query,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Intelligent textual search with pattern recognition
     */
    private function performIntelligentTextualSearch(string $query, int $documentId, array $params): array
    {
        $results = [];
        $lowerQuery = strtolower($query);

        // PATTERN 1: Search for "30 motivos" or numbered lists
        if (preg_match('/\d+\s*motivos?|lista|razões?|pontos?/i', $query)) {
            Log::info('Detected motivos/list query pattern');

            // Search for numbered items in content and metadata
            $numberedResults = DB::table('chunks')
                ->where('document_id', $documentId)
                ->where(function($q) {
                    $q->where('content', 'ILIKE', '%motivos%')
                      ->orWhere('content', 'ILIKE', '%1.%')
                      ->orWhere('content', 'ILIKE', '%2.%')
                      ->orWhere('content', 'ILIKE', '%3.%')
                      ->orWhere('meta', 'ILIKE', '%numbered_item%')
                      ->orWhere('meta', 'ILIKE', '%motivos%');
                })
                ->orderBy('ord')
                ->get();

            foreach ($numberedResults as $result) {
                $results[] = [
                    'id' => $result->id,
                    'content' => $result->content,
                    'score' => 0.9, // High score for pattern match
                    'method' => 'textual_numbered_pattern'
                ];
            }
        }

        // PATTERN 2: Keyword-based search
        if (empty($results)) {
            $keywords = $this->extractSearchKeywords($lowerQuery);

            if (!empty($keywords)) {
                Log::info('Using keyword search', ['keywords' => $keywords]);

                $keywordQuery = DB::table('chunks')
                    ->where('document_id', $documentId);

                // Build ILIKE conditions for each keyword
                foreach ($keywords as $keyword) {
                    $keywordQuery->where(function($q) use ($keyword) {
                        $q->where('content', 'ILIKE', "%{$keyword}%")
                          ->orWhere('meta', 'ILIKE', "%{$keyword}%");
                    });
                }

                $keywordResults = $keywordQuery
                    ->orderBy('ord')
                    ->limit(10)
                    ->get();

                foreach ($keywordResults as $result) {
                    $results[] = [
                        'id' => $result->id,
                        'content' => $result->content,
                        'score' => 0.7, // Medium score for keyword match
                        'method' => 'textual_keyword_match'
                    ];
                }
            }
        }

        // PATTERN 3: Fallback simple text search
        if (empty($results)) {
            Log::info('Using fallback simple text search');

            $simpleResults = DB::table('chunks')
                ->where('document_id', $documentId)
                ->where('content', 'ILIKE', "%{$lowerQuery}%")
                ->orderBy('ord')
                ->limit(5)
                ->get();

            foreach ($simpleResults as $result) {
                $results[] = [
                    'id' => $result->id,
                    'content' => $result->content,
                    'score' => 0.5, // Lower score for simple match
                    'method' => 'textual_simple_match'
                ];
            }
        }

        Log::info('Textual search completed', [
            'results_found' => count($results),
            'query' => $query
        ]);

        return $results;
    }

    /**
     * Extract meaningful keywords from search query
     */
    private function extractSearchKeywords(string $query): array
    {
        // Remove common stop words
        $stopWords = ['de', 'da', 'do', 'das', 'dos', 'a', 'o', 'as', 'os', 'um', 'uma', 'para',
                     'com', 'sem', 'por', 'em', 'na', 'no', 'que', 'é', 'são', 'foi', 'será',
                     'ter', 'tem', 'teve', 'e', 'ou', 'mas', 'se', 'não', 'sim', 'como', 'sobre'];

        // Extract words (3+ characters)
        preg_match_all('/\b[a-záàâãéêíóôõúç]{3,}\b/ui', $query, $matches);
        $words = array_map('strtolower', $matches[0]);

        // Filter out stop words
        $keywords = array_filter($words, function($word) use ($stopWords) {
            return !in_array($word, $stopWords);
        });

        return array_values(array_unique($keywords));
    }

    /**
     * Process document with advanced enterprise features using all available services
     * Integrates with RagPipeline, UniversalDocumentExtractor, EmbeddingCache, and ChunkingStrategy
     */
    public function processDocumentAdvanced(string $tenantSlug, int $documentId, string $content, array $metadata = [], array $options = []): array
    {
        try {
            Log::info('Advanced document processing started', [
                'tenant_slug' => $tenantSlug,
                'document_id' => $documentId,
                'content_length' => strlen($content),
                'options' => $options,
                'method' => 'enterprise_advanced_direct'
            ]);

            // Initialize all services for advanced processing
            $startTime = microtime(true);

            // Step 1: Use RagPipeline for complete document processing
            $ragPipeline = app(RagPipeline::class);

            // Step 2: Enhanced options for enterprise processing
            $enhancedOptions = array_merge([
                'chunk_size' => 1200,           // Larger chunks for better context
                'overlap_size' => 300,          // More overlap for enterprise
                'preserve_structure' => true,   // Keep document structure
                'use_semantic_chunking' => true,// Advanced chunking
                'quality_threshold' => 0.8,     // Higher quality threshold
                'enable_reranking' => true,     // Enterprise reranking
                'batch_embeddings' => true,     // Optimized batch processing
                'enable_cache' => true,         // Use embedding cache
                'enterprise_features' => true   // Enable all enterprise features
            ], $options);

            // Step 3: Enhanced metadata with enterprise features
            $enhancedMetadata = array_merge($metadata, [
                'processing_method' => 'enterprise_advanced',
                'processed_at' => now()->toISOString(),
                'tenant_slug' => $tenantSlug,
                'enterprise_pipeline_version' => '2.0',
                'quality_level' => 'enterprise'
            ]);

            // Step 4: Process document using RagPipeline with all enterprise features
            $result = $ragPipeline->processDocument(
                $tenantSlug,
                $documentId,
                $content,
                $enhancedMetadata,
                $enhancedOptions
            );

            $processingTime = microtime(true) - $startTime;

            if ($result['success']) {
                // Step 5: Add enterprise-specific post-processing
                $enterpriseMetrics = $this->calculateEnterpriseMetrics($result, $content, $enhancedOptions);

                Log::info('Advanced document processing completed successfully', [
                    'document_id' => $documentId,
                    'tenant_slug' => $tenantSlug,
                    'chunks_created' => $result['chunks_created'] ?? 0,
                    'processing_time' => round($processingTime, 3),
                    'enterprise_features_used' => [
                        'semantic_chunking',
                        'advanced_embeddings',
                        'quality_filtering',
                        'deduplication',
                        'enterprise_indexing'
                    ],
                    'quality_metrics' => $enterpriseMetrics
                ]);

                return [
                    'success' => true,
                    'document_id' => $documentId,
                    'chunks_created' => $result['chunks_created'] ?? 0,
                    'processing_time' => round($processingTime, 3),
                    'quality_metrics' => $enterpriseMetrics,
                    'deduplication_ratio' => $result['deduplication_ratio'] ?? 0,
                    'embedding_cache_hits' => $result['embedding_cache_hits'] ?? 0,
                    'method' => 'enterprise_advanced_pipeline',
                    'enterprise_features' => [
                        'semantic_chunking' => true,
                        'advanced_embeddings' => true,
                        'quality_filtering' => true,
                        'enterprise_indexing' => true,
                        'batch_optimization' => true
                    ],
                    'pipeline_version' => '2.0'
                ];
            } else {
                throw new Exception($result['error'] ?? 'Pipeline processing failed');
            }

        } catch (\Exception $e) {
            Log::error('Advanced document processing failed', [
                'document_id' => $documentId,
                'tenant_slug' => $tenantSlug,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Fallback: try simple processing if advanced fails
            try {
                Log::info('Attempting fallback processing', ['document_id' => $documentId]);

                $fallbackOptions = [
                    'chunk_size' => 800,
                    'overlap_size' => 150,
                    'fast_mode' => true,
                    'skip_advanced_features' => true
                ];

                $ragPipeline = app(RagPipeline::class);
                $fallbackResult = $ragPipeline->processDocument($tenantSlug, $documentId, $content, $metadata, $fallbackOptions);

                if ($fallbackResult['success']) {
                    Log::info('Fallback processing succeeded', ['document_id' => $documentId]);
                    return array_merge($fallbackResult, [
                        'method' => 'enterprise_fallback',
                        'fallback_used' => true
                    ]);
                }
            } catch (\Exception $fallbackError) {
                Log::error('Fallback processing also failed', [
                    'document_id' => $documentId,
                    'fallback_error' => $fallbackError->getMessage()
                ]);
            }

            return [
                'success' => false,
                'error' => 'Advanced processing failed: ' . $e->getMessage(),
                'method' => 'enterprise_advanced_failed'
            ];
        }
    }

    /**
     * Calculate enterprise-specific quality metrics
     */
    private function calculateEnterpriseMetrics(array $result, string $content, array $options): array
    {
        $contentLength = strlen($content);
        $chunksCount = $result['chunks_created'] ?? 0;

        return [
            'content_quality_score' => min(1.0, ($contentLength > 1000) ? 0.9 : ($contentLength / 1000 * 0.9)),
            'chunking_efficiency' => $chunksCount > 0 ? min(1.0, $contentLength / ($chunksCount * 1000)) : 0,
            'processing_mode' => $options['fast_mode'] ?? false ? 'fast' : 'comprehensive',
            'enterprise_features_active' => count(array_filter($options, fn($v) => is_bool($v) && $v)),
            'optimal_chunk_ratio' => $chunksCount > 0 ? round($contentLength / $chunksCount, 2) : 0
        ];
    }
}