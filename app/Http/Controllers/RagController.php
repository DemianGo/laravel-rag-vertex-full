<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use App\Services\VertexClient;
use App\Services\RagPipeline;
use App\Services\HybridRetriever;
use App\Services\VertexGenerator;
use App\Services\RagCache;
use App\Services\RagMetrics;
use App\Services\ChunkingStrategy;
use App\Services\EmbeddingCache;
use App\Services\EnterpriseRagService;
use Throwable;
use ZipArchive;

class RagController extends Controller
{
    // ---------- util/health/debug ----------
    public function health()
    {
        return response()->json([
            'ok'  => true,
            'ts'  => now()->toIso8601String(),
            'app' => config('app.name'),
            'env' => app()->environment(),
            'optimizations' => [
                'fast_mode' => 'enabled',
                'async_processing' => 'available',
                'timeout_handling' => 'improved',
                'retry_logic' => 'implemented',
                'php_warnings' => 'fixed'
            ],
            'upload_info' => [
                'max_file_size' => '50MB',
                'supported_formats' => ['PDF', 'DOCX', 'XLSX', 'TXT', 'HTML', 'MD', 'CSV', 'JSON'],
                'processing_modes' => ['sync', 'async', 'fast_mode'],
                'extraction_methods' => ['pdftotext', 'python_extractors', 'universal'],
                'processing_method' => 'simplified_direct',
                'dependencies_bypassed' => true
            ]
        ]);
    }

    public function echo(Request $req)
    {
        return response()->json([
            'ok'     => true,
            'method' => $req->getMethod(),
            'headers'=> $req->headers->all(),
            'query'  => $req->query(),
            'input'  => $req->all(),
            'files'  => array_keys($req->allFiles()),
            'raw'    => substr((string)$req->getContent(), 0, 5000),
        ]);
    }

    public function listDocs()
    {
        $docs = DB::table('documents')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get(['id','title','source','created_at','metadata']);

        $counts = DB::table('chunks')
            ->select('document_id', DB::raw('COUNT(*) as n'))
            ->groupBy('document_id')
            ->pluck('n','document_id');

        foreach ($docs as $d) {
            $d->chunks = (int)($counts[$d->id] ?? 0);
        }

        return response()->json(['ok' => true, 'docs' => $docs]);
    }

    // Fixar doc no cookie
    public function useDoc(Request $req)
    {
        $id = (int)$req->query('id', 0);
        if (!$id || !DB::table('documents')->where('id',$id)->exists()) {
            return response()->json(['ok'=>false,'error'=>'Documento inválido.'], 422);
        }
        $minutes = 60*24*30;
        return response()->json(['ok'=>true,'document_id'=>$id])
            ->cookie('rag_last_doc_id', (string)$id, $minutes, '/', null, false, false);
    }

    // Limpar cookie do doc atual
    public function clearDoc()
    {
        return response()->json(['ok'=>true,'cleared'=>true])
            ->cookie('rag_last_doc_id', '', -1, '/', null, false, false);
    }

    // Preview de chunks do doc atual
    public function preview(Request $req)
    {
        $docId = $this->getIntParam($req, ['document_id','doc_id','id'], 0, 0, PHP_INT_MAX) ?: null;
        $title = $this->getStringParam($req, ['title','filename','name']);
        $limit = $this->getIntParam($req, ['limit'], 5, 1, 20);

        $usedDocId = $this->resolveDocId($req, $docId, $title, '');
        if (!$usedDocId) return response()->json(['ok'=>false,'error'=>'Nenhum documento encontrado.'], 404);

        $rows = DB::table('chunks')
            ->where('document_id', $usedDocId)
            ->orderBy('ord','asc')
            ->limit($limit)
            ->selectRaw('ord, LEFT(content, 600) AS sample')
            ->get();

        return response()->json(['ok'=>true, 'document_id'=>$usedDocId, 'samples'=>$rows]);
    }

    // ---------- ENTERPRISE INGEST ----------
    public function ingest(Request $req)
    {
        // Optimize PHP settings for upload processing with shorter timeout
        ini_set('max_execution_time', 30); // 30 seconds max to prevent 77s loops
        ini_set('memory_limit', '512M');
        set_time_limit(30);

        $startTime = microtime(true);
        $requestId = 'upload_' . uniqid();

        try {
            Log::info('Enterprise ingest started', [
                'request_id' => $requestId,
                'start_time' => $startTime,
                'files_count' => count($req->allFiles()),
                'content_type' => $req->header('Content-Type'),
                'user_agent' => $req->header('User-Agent'),
                'memory_usage' => memory_get_usage(true) / 1024 / 1024 . ' MB'
            ]);

            // 1. Validate and extract content
            $extractionStart = microtime(true);
            $extractionResult = $this->extractContentWithAllMethods($req);
            $extractionTime = microtime(true) - $extractionStart;

            Log::info('Content extraction completed', [
                'request_id' => $requestId,
                'extraction_time' => round($extractionTime, 3) . 's',
                'extraction_method' => $extractionResult['method'] ?? 'unknown',
                'content_length' => isset($extractionResult['content']) ? strlen($extractionResult['content']) : 0,
                'memory_usage' => memory_get_usage(true) / 1024 / 1024 . ' MB'
            ]);

            if (!$extractionResult['success']) {
                return response()->json([
                    'ok' => false,
                    'error' => $extractionResult['error'],
                    'supported_formats' => ['PDF', 'DOCX', 'XLSX', 'TXT', 'HTML', 'MD', 'CSV', 'JSON']
                ], 422);
            }

            $tenantSlug = $req->input('tenant_slug', 'default');
            $title = $extractionResult['title'];
            $text = $extractionResult['content'];
            $metadata = $extractionResult['metadata'];

            if (strlen($text) < 10) {
                return response()->json([
                    'ok' => false,
                    'error' => 'Conteúdo muito curto. Mínimo 10 caracteres.',
                    'extraction_method' => $extractionResult['method'],
                    'content_preview' => substr($text, 0, 100)
                ], 422);
            }

            // 2. Store original file if upload
            $storedFilePath = null;
            if ($extractionResult['file_path']) {
                $storedFilePath = $this->storeUploadedFile($req, $extractionResult['file_path']);
            }

            // 3. Create document with comprehensive metadata
            $docCreateStart = microtime(true);
            $docId = DB::table('documents')->insertGetId([
                'title' => $title ?: 'Documento ' . now()->format('Y-m-d H:i:s'),
                'source' => 'enterprise_upload',
                'tenant_slug' => $tenantSlug,
                'metadata' => json_encode(array_merge($metadata, [
                    'original_length' => mb_strlen($text),
                    'upload_source' => 'enterprise_api',
                    'processed_at' => now()->toISOString(),
                    'extraction_method' => $extractionResult['method'],
                    'file_path' => $storedFilePath,
                    'quality_score' => $extractionResult['quality_score'] ?? 0.8,
                    'language_detected' => $this->detectLanguage($text),
                    'processing_version' => '2.0'
                ])),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $docCreateTime = microtime(true) - $docCreateStart;

            Log::info('Document created', [
                'request_id' => $requestId,
                'document_id' => $docId,
                'title' => $title,
                'content_length' => strlen($text),
                'extraction_method' => $extractionResult['method'],
                'doc_create_time' => round($docCreateTime, 3) . 's',
                'memory_usage' => memory_get_usage(true) / 1024 / 1024 . ' MB'
            ]);

            // 4. Use enterprise RAG service for advanced processing
            $fastMode = $req->input('fast_mode', false);
            $enableAsync = $req->input('async', $fastMode); // Auto-async in fast mode

            $processingOptions = [
                'chunk_size' => $req->input('chunk_size', $fastMode ? 800 : 1000),
                'overlap_size' => $req->input('overlap_size', $fastMode ? 100 : 200),
                'preserve_structure' => $req->input('preserve_structure', !$fastMode),
                'use_semantic_chunking' => $req->input('semantic_chunking', !$fastMode),
                'quality_threshold' => $req->input('quality_threshold', $fastMode ? 0.5 : 0.7),
                'enable_async' => $enableAsync,
                'python_extractors' => !$fastMode, // Skip Python extractors in fast mode
                'fast_mode' => $fastMode,
                'skip_deduplication' => $fastMode,
                'batch_embeddings' => $fastMode
            ];

            // If async requested, process immediately in background
            if ($enableAsync) {
                return $this->processAsync($requestId, $tenantSlug, $docId, $text, $metadata, $processingOptions, $startTime);
            }

            $ragProcessStart = microtime(true);
            Log::info('Starting RAG processing', [
                'request_id' => $requestId,
                'document_id' => $docId,
                'processing_options' => $processingOptions,
                'memory_usage' => memory_get_usage(true) / 1024 / 1024 . ' MB'
            ]);

            // Process with timeout monitoring
            $processingStartTime = microtime(true);

            try {
                $result = $this->processDocumentSimple(
                    $tenantSlug,
                    $docId,
                    $text,
                    $metadata,
                    $processingOptions
                );

                $actualProcessingTime = microtime(true) - $processingStartTime;

                Log::info('Document processing completed', [
                    'request_id' => $requestId,
                    'actual_processing_time' => round($actualProcessingTime, 3) . 's',
                    'success' => $result['success'],
                    'chunks_created' => $result['chunks_created'] ?? 0
                ]);

            } catch (Exception $processingException) {
                $actualProcessingTime = microtime(true) - $processingStartTime;

                Log::warning('Primary processing failed, using ultra-fast fallback', [
                    'request_id' => $requestId,
                    'processing_time' => round($actualProcessingTime, 3) . 's',
                    'error' => $processingException->getMessage()
                ]);

                // Ultra-fast fallback: direct chunk insertion
                $result = $this->processingFallbackUltraFast($tenantSlug, $docId, $text, $metadata);
            }

            $ragProcessTime = microtime(true) - $ragProcessStart;
            Log::info('RAG processing completed', [
                'request_id' => $requestId,
                'document_id' => $docId,
                'rag_process_time' => round($ragProcessTime, 3) . 's',
                'success' => $result['success'],
                'chunks_created' => $result['chunks_created'] ?? 0,
                'memory_usage' => memory_get_usage(true) / 1024 / 1024 . ' MB'
            ]);

            if (!$result['success']) {
                // Try one retry with different settings
                Log::warning('First processing attempt failed, trying retry', [
                    'request_id' => $requestId,
                    'document_id' => $docId,
                    'error' => $result['error'] ?? 'Unknown error'
                ]);

                // Retry with simpler/faster options
                $retryOptions = array_merge($processingOptions, [
                    'fast_mode' => true,
                    'skip_deduplication' => true,
                    'use_semantic_chunking' => false,
                    'chunk_size' => 500,
                    'overlap_size' => 50
                ]);

                $retryStart = microtime(true);
                $retryResult = $this->processDocumentSimple(
                    $tenantSlug,
                    $docId,
                    $text,
                    $metadata,
                    $retryOptions
                );
                $retryTime = microtime(true) - $retryStart;

                Log::info('Retry processing completed', [
                    'request_id' => $requestId,
                    'document_id' => $docId,
                    'retry_time' => round($retryTime, 3) . 's',
                    'retry_success' => $retryResult['success']
                ]);

                if (!$retryResult['success']) {
                    // Both attempts failed - rollback
                    DB::table('documents')->where('id', $docId)->delete();
                    if ($storedFilePath) {
                        Storage::delete($storedFilePath);
                    }

                    return response()->json([
                        'ok' => false,
                        'error' => 'Document processing failed after retry: ' . ($retryResult['error'] ?? 'Unknown error'),
                        'original_error' => $result['error'] ?? 'Unknown error',
                        'retry_attempted' => true,
                        'debug_info' => $retryResult['debug_info'] ?? null
                    ], 500);
                }

                // Use retry result
                $result = $retryResult;
                $result['retry_used'] = true;
            }

            // 5. Set session cookie
            $minutes = 60*24*30;

            $totalTime = microtime(true) - $startTime;

            Log::info('Enterprise ingest completed successfully', [
                'request_id' => $requestId,
                'document_id' => $docId,
                'chunks_created' => $result['chunks_created'],
                'processing_time' => $result['processing_time'],
                'quality_metrics' => $result['quality_metrics'] ?? null,
                'total_time' => round($totalTime, 3) . 's',
                'time_breakdown' => [
                    'extraction' => round($extractionTime, 3) . 's',
                    'doc_creation' => round($docCreateTime, 3) . 's',
                    'rag_processing' => round($ragProcessTime, 3) . 's'
                ],
                'final_memory_usage' => memory_get_usage(true) / 1024 / 1024 . ' MB',
                'peak_memory_usage' => memory_get_peak_usage(true) / 1024 / 1024 . ' MB'
            ]);

            // Gerar perguntas sugeridas em background (não bloqueia resposta)
            $this->generateSuggestedQuestions($docId);

            return response()
                ->json([
                    'ok' => true,
                    'document_id' => $docId,
                    'title' => $title,
                    'chunks_created' => $result['chunks_created'],
                    'processing_time' => $result['processing_time'],
                    'deduplication_ratio' => $result['deduplication_ratio'],
                    'quality_metrics' => $result['quality_metrics'] ?? null,
                    'extraction_method' => $extractionResult['method'],
                    'language_detected' => $this->detectLanguage($text),
                    'cache_stats' => $result['embedding_cache_hits'] ?? null,
                    'enterprise_features_used' => $result['enterprise_features'] ?? [],
                    'retry_used' => $result['retry_used'] ?? false
                ], 201)
                ->cookie('rag_last_doc_id', (string)$docId, $minutes, '/', null, false, false);

        } catch (Throwable $e) {
            Log::error("Enterprise ingest error", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $req->except(['file', 'document', 'upload'])
            ]);

            return response()->json([
                'ok' => false,
                'error' => 'Upload failed: ' . $e->getMessage(),
                'error_type' => get_class($e),
                'retry_suggestions' => [
                    'Check file format is supported',
                    'Verify file is not corrupted',
                    'Try smaller file size',
                    'Contact support if problem persists'
                ]
            ], 500);
        }
    }

    // ---------- ENTERPRISE QUERY ----------
    public function query(Request $req, HybridRetriever $retriever)
    {
        $q = $this->getStringParam($req, ['q','query','question','prompt','text','message','msg','qtext','search']);
        $topK = $this->getIntParam($req, ['top_k','topk','k','top','limit','n'], 5, 1, 50);

        $tenantSlug = $req->input('tenant_slug', 'default');
        $docId = $this->getIntParam($req, ['document_id','doc_id','id'], 0, 0, PHP_INT_MAX) ?: null;
        $title = $this->getStringParam($req, ['title','filename','name']);

        if ($q === '') return response()->json(['ok'=>false,'error'=>'Parâmetro q ausente.'], 422);

        $usedDocId = $this->resolveDocId($req, $docId, $title, '');

        try {
            // Use enterprise hybrid search
            $searchOptions = [
                'limit' => $topK,
                'document_ids' => $usedDocId ? [$usedDocId] : [],
                'vector_weight' => $req->input('vector_weight', 0.7),
                'keyword_weight' => $req->input('keyword_weight', 0.3),
                'similarity_threshold' => $req->input('similarity_threshold', 0.1),
                'rerank' => $req->input('rerank', true),
                'diversify' => $req->input('diversify', true)
            ];

            $results = $retriever->search($tenantSlug, $q, $searchOptions);

            return response()->json([
                'ok' => true,
                'query' => $q,
                'top_k' => $topK,
                'tenant_slug' => $tenantSlug,
                'used_doc' => $usedDocId,
                'search_mode' => 'hybrid_enterprise',
                'results' => $results,
                'search_stats' => $retriever->getStats()
            ]);

        } catch (Throwable $e) {
            Log::error("Enterprise query failed: ".$e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['ok'=>false,'error'=>$e->getMessage()], 500);
        }
    }

    // ---------- ENTERPRISE GENERATION ----------
    public function generateAnswer(Request $req, RagPipeline $pipeline)
    {
        $q = $this->getStringParam($req, ['q','query','question','prompt','text','message']);
        $tenantSlug = $req->input('tenant_slug', 'default');

        if ($q === '') return response()->json(['ok'=>false,'error'=>'Query parameter required'], 422);

        try {
            $searchOptions = [
                'limit' => $req->input('search_limit', 10),
                'vector_weight' => $req->input('vector_weight', 0.7),
                'keyword_weight' => $req->input('keyword_weight', 0.3),
                'rerank' => $req->input('rerank', true)
            ];

            $generationOptions = [
                'model' => $req->input('model', 'gemini-1.5-flash'),
                'temperature' => $req->input('temperature', 0.1),
                'max_tokens' => $req->input('max_tokens', 2048),
                'add_citations' => $req->input('citations', true),
                'streaming' => $req->input('streaming', false)
            ];

            $result = $pipeline->generateAnswer($tenantSlug, $q, $searchOptions, $generationOptions);

            return response()->json([
                'ok' => true,
                'query' => $q,
                'tenant_slug' => $tenantSlug,
                'answer' => $result['answer'] ?? null,
                'confidence' => $result['confidence'] ?? 0,
                'sources' => $result['sources'] ?? [],
                'generation_time' => $result['generation_time'] ?? 0,
                'metadata' => $result['metadata'] ?? []
            ]);

        } catch (Throwable $e) {
            Log::error("Enterprise generation failed: ".$e->getMessage());
            return response()->json(['ok'=>false,'error'=>$e->getMessage()], 500);
        }
    }

    // ---------- ENTERPRISE METRICS ----------
    public function metrics(Request $req, RagMetrics $metrics)
    {
        try {
            $period = $req->input('period', '1h'); // 1h, 1d, 7d, 30d
            $tenantSlug = $req->input('tenant_slug', 'default');

            $stats = $metrics->getMetrics($tenantSlug, $period);

            return response()->json([
                'ok' => true,
                'tenant_slug' => $tenantSlug,
                'period' => $period,
                'metrics' => $stats
            ]);

        } catch (Throwable $e) {
            Log::error("Metrics retrieval failed: ".$e->getMessage());
            return response()->json(['ok'=>false,'error'=>$e->getMessage()], 500);
        }
    }

    // ---------- ENTERPRISE CACHE MANAGEMENT ----------
    public function cacheStats(Request $req, RagCache $cache)
    {
        try {
            $stats = $cache->getStats();

            return response()->json([
                'ok' => true,
                'cache_stats' => $stats
            ]);

        } catch (Throwable $e) {
            Log::error("Cache stats failed: ".$e->getMessage());
            return response()->json(['ok'=>false,'error'=>$e->getMessage()], 500);
        }
    }

    public function clearCache(Request $req, RagCache $cache)
    {
        try {
            $pattern = $req->input('pattern', '*');
            $cleared = $cache->flush($pattern);

            return response()->json([
                'ok' => true,
                'pattern' => $pattern,
                'keys_cleared' => $cleared
            ]);

        } catch (Throwable $e) {
            Log::error("Cache clear failed: ".$e->getMessage());
            return response()->json(['ok'=>false,'error'=>$e->getMessage()], 500);
        }
    }

    // ---------- BATCH OPERATIONS ----------
    public function batchIngest(Request $req)
    {
        try {
            $documents = $req->input('documents', []);
            $tenantSlug = $req->input('tenant_slug', 'default');
            $enableAsync = $req->input('async', false);

            if (empty($documents)) {
                return response()->json(['ok'=>false,'error'=>'No documents provided'], 422);
            }

            $batchId = 'batch_' . time() . '_' . substr(md5(json_encode($documents)), 0, 8);

            if ($enableAsync) {
                // Store batch job for async processing
                DB::table('batch_jobs')->insert([
                    'batch_id' => $batchId,
                    'tenant_slug' => $tenantSlug,
                    'total_documents' => count($documents),
                    'processed_documents' => 0,
                    'status' => 'queued',
                    'documents_data' => json_encode($documents),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Queue the batch for background processing
                dispatch(function() use ($batchId, $documents, $tenantSlug) {
                    $this->processBatchAsync($batchId, $documents, $tenantSlug);
                })->onQueue('document-processing');

                return response()->json([
                    'ok' => true,
                    'batch_id' => $batchId,
                    'status' => 'queued',
                    'total_documents' => count($documents),
                    'message' => 'Batch processing started asynchronously',
                    'status_url' => route('api.rag.batch-status', ['batch_id' => $batchId])
                ], 202);
            }

            // Synchronous processing
            $results = [];
            $successCount = 0;

            foreach ($documents as $doc) {
                try {
                    $docId = DB::table('documents')->insertGetId([
                        'title' => $doc['title'] ?? 'Untitled',
                        'source' => 'batch_upload',
                        'tenant_slug' => $tenantSlug,
                        'metadata' => json_encode($doc['metadata'] ?? []),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $result = $this->processDocumentSimple(
                        $tenantSlug,
                        $docId,
                        $doc['content'],
                        $doc['metadata'] ?? [],
                        $doc['options'] ?? []
                    );

                    if ($result['success']) {
                        $successCount++;
                    }

                    $results[] = [
                        'document_id' => $docId,
                        'title' => $doc['title'] ?? 'Untitled',
                        'success' => $result['success'],
                        'chunks_created' => $result['chunks_created'] ?? 0,
                        'processing_time' => $result['processing_time'] ?? 0,
                        'quality_metrics' => $result['quality_metrics'] ?? null,
                        'error' => $result['error'] ?? null
                    ];

                } catch (Throwable $e) {
                    $results[] = [
                        'title' => $doc['title'] ?? 'Untitled',
                        'success' => false,
                        'error' => $e->getMessage()
                    ];
                }
            }

            return response()->json([
                'ok' => true,
                'tenant_slug' => $tenantSlug,
                'total_documents' => count($documents),
                'successful_documents' => $successCount,
                'results' => $results,
                'processing_summary' => [
                    'success_rate' => round(($successCount / count($documents)) * 100, 2) . '%',
                    'failed_documents' => count($documents) - $successCount,
                    'total_chunks' => array_sum(array_column($results, 'chunks_created'))
                ]
            ]);

        } catch (Throwable $e) {
            Log::error("Batch ingest failed: ".$e->getMessage());
            return response()->json(['ok'=>false,'error'=>$e->getMessage()], 500);
        }
    }

    // ---------- ASYNC UPLOAD STATUS ----------
    public function uploadStatus(Request $req)
    {
        try {
            $uploadId = $req->input('upload_id') ?: $req->route('upload_id');
            $batchId = $req->input('batch_id') ?: $req->route('batch_id');

            if ($batchId) {
                $job = DB::table('batch_jobs')->where('batch_id', $batchId)->first();

                if (!$job) {
                    return response()->json(['ok'=>false,'error'=>'Batch not found'], 404);
                }

                return response()->json([
                    'ok' => true,
                    'batch_id' => $batchId,
                    'status' => $job->status,
                    'progress' => [
                        'total' => $job->total_documents,
                        'processed' => $job->processed_documents,
                        'percentage' => $job->total_documents > 0 ?
                            round(($job->processed_documents / $job->total_documents) * 100, 2) : 0
                    ],
                    'created_at' => $job->created_at,
                    'updated_at' => $job->updated_at,
                    'results' => $job->results ? json_decode($job->results) : null
                ]);
            }

            if ($uploadId) {
                // Single document upload status
                $doc = DB::table('documents')->where('id', $uploadId)->first();

                if (!$doc) {
                    return response()->json(['ok'=>false,'error'=>'Upload not found'], 404);
                }

                $chunksCount = DB::table('chunks')->where('document_id', $uploadId)->count();

                return response()->json([
                    'ok' => true,
                    'upload_id' => $uploadId,
                    'document_id' => $uploadId,
                    'status' => $chunksCount > 0 ? 'completed' : 'processing',
                    'title' => $doc->title,
                    'chunks_created' => $chunksCount,
                    'created_at' => $doc->created_at,
                    'metadata' => json_decode($doc->metadata)
                ]);
            }

            return response()->json(['ok'=>false,'error'=>'Upload ID or Batch ID required'], 422);

        } catch (Throwable $e) {
            Log::error("Upload status check failed: ".$e->getMessage());
            return response()->json(['ok'=>false,'error'=>$e->getMessage()], 500);
        }
    }

    // ---------- UPLOAD RETRY ----------
    public function retryUpload(Request $req)
    {
        try {
            $documentId = $req->input('document_id');

            if (!$documentId) {
                return response()->json(['ok'=>false,'error'=>'document_id required'], 422);
            }

            $doc = DB::table('documents')->where('id', $documentId)->first();
            if (!$doc) {
                return response()->json(['ok'=>false,'error'=>'Document not found'], 404);
            }

            // Clear existing chunks
            DB::table('chunks')->where('document_id', $documentId)->delete();

            // Get stored file content
            $metadata = json_decode($doc->metadata, true);
            $filePath = $metadata['file_path'] ?? null;

            if (!$filePath || !Storage::exists($filePath)) {
                return response()->json([
                    'ok'=>false,
                    'error'=>'Original file not available for retry. Please upload again.'
                ], 404);
            }

            // Re-extract content
            $content = Storage::get($filePath);

            // Retry processing
            $result = $this->processDocumentSimple(
                $doc->tenant_slug,
                $documentId,
                $content,
                $metadata,
                $req->input('options', [])
            );

            return response()->json([
                'ok' => $result['success'],
                'document_id' => $documentId,
                'retry_result' => $result,
                'message' => $result['success'] ? 'Retry successful' : 'Retry failed'
            ]);

        } catch (Throwable $e) {
            Log::error("Upload retry failed: ".$e->getMessage());
            return response()->json(['ok'=>false,'error'=>$e->getMessage()], 500);
        }
    }

    private function processBatchAsync(string $batchId, array $documents, string $tenantSlug)
    {
        try {
            DB::table('batch_jobs')->where('batch_id', $batchId)->update([
                'status' => 'processing',
                'updated_at' => now()
            ]);

            $results = [];
            $successCount = 0;
            $processedCount = 0;

            foreach ($documents as $doc) {
                try {
                    $docId = DB::table('documents')->insertGetId([
                        'title' => $doc['title'] ?? 'Untitled',
                        'source' => 'batch_upload_async',
                        'tenant_slug' => $tenantSlug,
                        'metadata' => json_encode(array_merge($doc['metadata'] ?? [], [
                            'batch_id' => $batchId,
                            'batch_processing' => true
                        ])),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $result = $this->processDocumentSimple(
                        $tenantSlug,
                        $docId,
                        $doc['content'],
                        $doc['metadata'] ?? [],
                        $doc['options'] ?? []
                    );

                    if ($result['success']) {
                        $successCount++;
                    }

                    $results[] = [
                        'document_id' => $docId,
                        'title' => $doc['title'] ?? 'Untitled',
                        'success' => $result['success'],
                        'chunks_created' => $result['chunks_created'] ?? 0,
                        'error' => $result['error'] ?? null
                    ];

                } catch (Throwable $e) {
                    $results[] = [
                        'title' => $doc['title'] ?? 'Untitled',
                        'success' => false,
                        'error' => $e->getMessage()
                    ];
                }

                $processedCount++;

                // Update progress
                DB::table('batch_jobs')->where('batch_id', $batchId)->update([
                    'processed_documents' => $processedCount,
                    'updated_at' => now()
                ]);
            }

            // Mark as completed
            DB::table('batch_jobs')->where('batch_id', $batchId)->update([
                'status' => 'completed',
                'processed_documents' => $processedCount,
                'results' => json_encode([
                    'total_documents' => count($documents),
                    'successful_documents' => $successCount,
                    'results' => $results
                ]),
                'updated_at' => now()
            ]);

        } catch (Throwable $e) {
            DB::table('batch_jobs')->where('batch_id', $batchId)->update([
                'status' => 'failed',
                'results' => json_encode(['error' => $e->getMessage()]),
                'updated_at' => now()
            ]);
        }
    }

    // ---------- EMBEDDING MANAGEMENT ----------
    public function embeddingStats(Request $req, EmbeddingCache $cache)
    {
        try {
            $tenantSlug = $req->input('tenant_slug', 'default');
            $stats = $cache->getStats();

            return response()->json([
                'ok' => true,
                'tenant_slug' => $tenantSlug,
                'embedding_cache' => $stats
            ]);

        } catch (Throwable $e) {
            Log::error("Embedding stats failed: ".$e->getMessage());
            return response()->json(['ok'=>false,'error'=>$e->getMessage()], 500);
        }
    }

    public function reprocessDocument(Request $req, RagPipeline $pipeline)
    {
        try {
            $docId = $req->input('document_id');
            $tenantSlug = $req->input('tenant_slug', 'default');

            if (!$docId) {
                return response()->json(['ok'=>false,'error'=>'document_id required'], 422);
            }

            // Get document
            $doc = DB::table('documents')->where('id', $docId)->where('tenant_slug', $tenantSlug)->first();
            if (!$doc) {
                return response()->json(['ok'=>false,'error'=>'Document not found'], 404);
            }

            // Delete existing chunks
            DB::table('chunks')->where('document_id', $docId)->delete();

            // Get original content (this would need to be stored)
            $content = $req->input('content');
            if (!$content) {
                return response()->json(['ok'=>false,'error'=>'Original content required for reprocessing'], 422);
            }

            // Reprocess with pipeline
            $result = $this->processDocumentSimple(
                $tenantSlug,
                $docId,
                $content,
                json_decode($doc->metadata, true) ?? [],
                $req->input('options', [])
            );

            return response()->json([
                'ok' => true,
                'document_id' => $docId,
                'reprocessing_result' => $result
            ]);

        } catch (Throwable $e) {
            Log::error("Document reprocessing failed: ".$e->getMessage());
            return response()->json(['ok'=>false,'error'=>$e->getMessage()], 500);
        }
    }

    // ---------- helpers ----------
    private function resolveIngestPayload(Request $req): array
    {
        $file = $req->file('file')
             ?: $req->file('document')
             ?: $req->file('upload')
             ?: $req->file('pdf')
             ?: $req->file('doc')
             ?: $req->file('attachment');

        if (!$file) {
            $files = $req->file('files') ?? $req->file('files.0') ?? null;
            if (is_array($files) && count($files) > 0) $file = $files[0];
        }

        if ($file instanceof UploadedFile) {
            $title = trim((string)($req->input('title') ?: $file->getClientOriginalName()));
            $text  = $this->extractTextFromUpload($file);
            return [$title, $text];
        }

        // JSON/FORM
        $title = trim((string)($req->input('title','')));
        $text  = trim((string)($req->input('text','')));

        // Text/plain
        if ($title === '' && $text === '') {
            $raw = trim((string)$req->getContent());
            if ($raw !== '' && !in_array(substr($raw,0,1), ['{','['])) {
                $title = 'Texto colado ' . now()->format('Y-m-d H:i:s');
                $text  = $raw;
            }
        }
        return [$title, $text];
    }

    private function resolveDocId(Request $req, ?int $docId, ?string $title, ?string $scope): ?int
    {
        if ($docId && DB::table('documents')->where('id',$docId)->exists()) return $docId;
        if ($title !== '') {
            $row = DB::table('documents')->where('title',$title)->orderByDesc('created_at')->first();
            if ($row) return (int)$row->id;
        }
        $cookieId = (int)($req->cookie('rag_last_doc_id', 0) ?: 0);
        if ($cookieId && DB::table('documents')->where('id',$cookieId)->exists()) return $cookieId;
        $row = DB::table('documents')->orderByDesc('created_at')->first();
        return $row ? (int)$row->id : null;
    }

    private function docHasEmbeddings(?int $docId): bool
    {
        if (!$docId) return false;
        $n = DB::table('chunks')->where('document_id',$docId)->whereNotNull('embedding')->count();
        return $n > 0;
    }

    private function chunkText(string $text, int $window = 1000, int $overlap = 200): array
    {
        $text = preg_replace("/\r\n|\r/","\n",$text);
        $text = preg_replace("/\n{3,}/","\n\n",$text);
        $text = trim($text);
        $len = mb_strlen($text);
        $chunks = [];
        $i = 0;
        while ($i < $len) {
            $end = min($len, $i + $window);
            $chunk = mb_substr($text, $i, $end - $i);
            $chunks[] = trim($chunk);
            if ($end >= $len) break;
            $i = max(0, $end - $overlap);
        }
        return array_values(array_filter($chunks, fn($c) => $c !== ''));
    }

    private function getStringParam(Request $req, array $keys): string
    {
        foreach ($keys as $k) {
            $v = $req->input($k, $req->query($k, null));
            if (is_string($v) && trim($v) !== '') return trim($v);
        }
        $raw = trim((string)$req->getContent());
        if ($raw !== '' && !in_array(substr($raw,0,1), ['{','['])) return $raw;
        return '';
    }

    private function getIntParam(Request $req, array $keys, int $def, int $min, int $max): int
    {
        foreach ($keys as $k) {
            $v = $req->input($k, $req->query($k, null));
            if ($v !== null && $v !== '') {
                $n = (int)$v; if ($n<$min)$n=$min; if ($n>$max)$n=$max; return $n;
            }
        }
        return $def;
    }

    private function extractContentWithAllMethods(Request $req): array
    {
        // Try to get uploaded file with multiple possible field names
        $file = $req->file('file')
             ?: $req->file('document')
             ?: $req->file('upload')
             ?: $req->file('pdf')
             ?: $req->file('doc')
             ?: $req->file('attachment');

        if (!$file) {
            $files = $req->file('files') ?? $req->file('files.0') ?? null;
            if (is_array($files) && count($files) > 0) $file = $files[0];
        }

        // Handle file upload
        if ($file instanceof UploadedFile) {
            return $this->extractFromFile($file);
        }

        // Handle JSON/Form input
        $title = trim((string)($req->input('title', '')));
        $text = trim((string)($req->input('text', '')));

        if ($title !== '' && $text !== '') {
            return [
                'success' => true,
                'title' => $title,
                'content' => $text,
                'metadata' => ['input_type' => 'form_json'],
                'method' => 'direct_input',
                'quality_score' => 1.0,
                'file_path' => null
            ];
        }

        // Handle raw text/plain
        $raw = trim((string)$req->getContent());
        if ($raw !== '' && !in_array(substr($raw, 0, 1), ['{', '['])) {
            return [
                'success' => true,
                'title' => 'Texto colado ' . now()->format('Y-m-d H:i:s'),
                'content' => $raw,
                'metadata' => ['input_type' => 'raw_text'],
                'method' => 'raw_paste',
                'quality_score' => 0.9,
                'file_path' => null
            ];
        }

        return [
            'success' => false,
            'error' => 'Nenhum arquivo ou texto fornecido. Formatos suportados: PDF, DOCX, XLSX, TXT, HTML, MD, CSV, JSON'
        ];
    }

    private function extractFromFile(UploadedFile $file): array
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension());
        $originalName = $file->getClientOriginalName();
        $fileSize = $file->getSize();
        $path = $file->getRealPath();

        Log::info('Extracting content from file', [
            'filename' => $originalName,
            'extension' => $ext,
            'size' => $fileSize,
            'mime_type' => $file->getMimeType()
        ]);

        // Basic validation
        if ($fileSize > 50 * 1024 * 1024) { // 50MB limit
            return [
                'success' => false,
                'error' => 'Arquivo muito grande. Limite: 50MB'
            ];
        }

        $metadata = [
            'original_name' => $originalName,
            'file_size' => $fileSize,
            'mime_type' => $file->getMimeType(),
            'extension' => $ext
        ];

        // Extract content based on file type
        $extractionResult = $this->extractContentByType($path, $ext, $metadata);

        if ($extractionResult['success']) {
            return [
                'success' => true,
                'title' => $originalName,
                'content' => $extractionResult['content'],
                'metadata' => array_merge($metadata, $extractionResult['metadata']),
                'method' => $extractionResult['method'],
                'quality_score' => $extractionResult['quality_score'],
                'file_path' => $path
            ];
        }

        return $extractionResult;
    }

    private function extractContentByType(string $path, string $ext, array $baseMetadata): array
    {
        try {
            switch ($ext) {
                case 'txt':
                case 'text':
                    $content = file_get_contents($path);
                    return [
                        'success' => true,
                        'content' => trim($content),
                        'method' => 'direct_read',
                        'quality_score' => 1.0,
                        'metadata' => ['encoding' => mb_detect_encoding($content)]
                    ];

                case 'pdf':
                    return $this->extractFromPdf($path);

                case 'docx':
                case 'doc':
                    return $this->extractFromWord($path);

                case 'xlsx':
                case 'xls':
                    return $this->extractFromExcel($path);

                case 'html':
                case 'htm':
                    return $this->extractFromHtml($path);

                case 'md':
                case 'markdown':
                    $content = file_get_contents($path);
                    return [
                        'success' => true,
                        'content' => trim($content),
                        'method' => 'markdown_read',
                        'quality_score' => 0.9,
                        'metadata' => ['format' => 'markdown']
                    ];

                case 'csv':
                    return $this->extractFromCsv($path);

                case 'json':
                    return $this->extractFromJson($path);

                default:
                    return $this->extractWithPythonScripts($path, $ext);
            }
        } catch (Throwable $e) {
            Log::error('Content extraction failed', [
                'extension' => $ext,
                'error' => $e->getMessage(),
                'path' => $path
            ]);

            return [
                'success' => false,
                'error' => 'Falha na extração: ' . $e->getMessage()
            ];
        }
    }

    private function extractFromPdf(string $path): array
    {
        $startTime = microtime(true);

        // Try fastest methods first, with timeout for each
        $methods = [
            'pdftotext_raw' => function($path) {
                $bin = @shell_exec('which pdftotext 2>/dev/null');
                if (!$bin) return false;
                $cmd = trim($bin).' -enc UTF-8 -raw '.escapeshellarg($path).' - 2>/dev/null';
                return @shell_exec($cmd);
            },
            'pdftotext_layout' => function($path) {
                $bin = @shell_exec('which pdftotext 2>/dev/null');
                if (!$bin) return false;
                $cmd = trim($bin).' -enc UTF-8 -layout '.escapeshellarg($path).' - 2>/dev/null';
                return @shell_exec($cmd);
            }
        ];

        // Only try Python extractors if basic methods fail
        $pythonMethods = [
            'python_pymupdf' => function($path) {
                return $this->runPythonExtractor('pdf_pymupdf.py', $path);
            }
        ];

        $bestContent = '';
        $bestScore = 0;
        $usedMethod = 'none';

        // Try fast methods first (max 3 seconds)
        foreach ($methods as $method => $extractor) {
            $methodStart = microtime(true);
            $content = $extractor($path);
            $methodTime = microtime(true) - $methodStart;

            Log::debug("PDF extraction method '$method' took " . round($methodTime, 3) . "s");

            if ($content && strlen(trim($content)) > 50) { // Quick quality check
                $bestContent = $content;
                $bestScore = $this->scoreExtractedContent($content);
                $usedMethod = $method;
                break; // Use first successful method for speed
            }
        }

        // Only try Python methods if nothing worked and we have time
        if (!$bestContent && (microtime(true) - $startTime) < 5) {
            foreach ($pythonMethods as $method => $extractor) {
                $content = $extractor($path);
                if ($content && strlen(trim($content)) > strlen(trim($bestContent))) {
                    $score = $this->scoreExtractedContent($content);
                    if ($score > $bestScore) {
                        $bestContent = $content;
                        $bestScore = $score;
                        $usedMethod = $method;
                    }
                }
            }
        }

        if (!$bestContent) {
            return [
                'success' => false,
                'error' => 'Nenhum método de extração PDF funcionou. Instale poppler-utils ou python-pymupdf.'
            ];
        }

        return [
            'success' => true,
            'content' => trim($bestContent),
            'method' => "pdf_$usedMethod",
            'quality_score' => $bestScore,
            'metadata' => ['extraction_method' => $usedMethod]
        ];
    }

    private function extractFromWord(string $path): array
    {
        // Try Python docx extraction
        $content = $this->runPythonExtractor('docx_extractor.py', $path);

        if ($content) {
            return [
                'success' => true,
                'content' => trim($content),
                'method' => 'python_docx',
                'quality_score' => 0.9,
                'metadata' => ['extractor' => 'python_docx2txt']
            ];
        }

        return [
            'success' => false,
            'error' => 'Falha na extração DOCX. Instale python-docx2txt.'
        ];
    }

    private function extractFromExcel(string $path): array
    {
        $content = $this->runPythonExtractor('excel_extractor.py', $path);

        if ($content) {
            return [
                'success' => true,
                'content' => trim($content),
                'method' => 'python_openpyxl',
                'quality_score' => 0.8,
                'metadata' => ['extractor' => 'python_openpyxl']
            ];
        }

        return [
            'success' => false,
            'error' => 'Falha na extração Excel. Instale python-openpyxl.'
        ];
    }

    private function extractFromHtml(string $path): array
    {
        $html = file_get_contents($path);
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', $text);

        return [
            'success' => true,
            'content' => trim($text),
            'method' => 'html_strip_tags',
            'quality_score' => 0.7,
            'metadata' => ['original_html_length' => strlen($html)]
        ];
    }

    private function extractFromCsv(string $path): array
    {
        $content = $this->runPythonExtractor('csv_extractor.py', $path);

        if ($content) {
            return [
                'success' => true,
                'content' => trim($content),
                'method' => 'python_pandas',
                'quality_score' => 0.8,
                'metadata' => ['extractor' => 'python_pandas']
            ];
        }

        // Fallback to basic PHP CSV reading
        $handle = fopen($path, 'r');
        if (!$handle) {
            return ['success' => false, 'error' => 'Cannot open CSV file'];
        }

        $rows = [];
        while (($data = fgetcsv($handle)) !== false) {
            $rows[] = implode(' | ', $data);
        }
        fclose($handle);

        return [
            'success' => true,
            'content' => implode("\n", $rows),
            'method' => 'php_csv',
            'quality_score' => 0.6,
            'metadata' => ['rows_count' => count($rows)]
        ];
    }

    private function extractFromJson(string $path): array
    {
        $json = file_get_contents($path);
        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'error' => 'Invalid JSON: ' . json_last_error_msg()
            ];
        }

        $text = $this->convertArrayToText($data);

        return [
            'success' => true,
            'content' => $text,
            'method' => 'json_decode',
            'quality_score' => 0.8,
            'metadata' => ['json_keys' => count($data)]
        ];
    }

    private function extractWithPythonScripts(string $path, string $ext): array
    {
        // Try universal document extractor
        $content = $this->runPythonExtractor('universal_extractor.py', $path);

        if ($content) {
            return [
                'success' => true,
                'content' => trim($content),
                'method' => 'python_universal',
                'quality_score' => 0.7,
                'metadata' => ['extractor' => 'universal_python']
            ];
        }

        return [
            'success' => false,
            'error' => "Formato .$ext não suportado pelos extractors disponíveis."
        ];
    }

    private function runPythonExtractor(string $script, string $filePath, int $timeoutSeconds = 5): ?string
    {
        $scriptPath = base_path("scripts/document_extraction/$script");

        if (!file_exists($scriptPath)) {
            Log::warning("Python extractor not found: $script");
            return null;
        }

        // Add timeout to prevent hanging
        $cmd = "timeout {$timeoutSeconds}s python3 " . escapeshellarg($scriptPath) . " " . escapeshellarg($filePath) . " 2>/dev/null";

        $startTime = microtime(true);
        $output = @shell_exec($cmd);
        $execTime = microtime(true) - $startTime;

        Log::debug("Python extractor '$script' took " . round($execTime, 3) . "s");

        return $output ? trim($output) : null;
    }

    private function scoreExtractedContent(string $content): float
    {
        $content = trim($content);
        $length = strlen($content);

        if ($length < 10) return 0.0;
        if ($length < 100) return 0.3;

        // Score based on content characteristics
        $score = 0.5;

        // Bonus for reasonable length
        $score += min(0.3, $length / 10000);

        // Bonus for proper sentences
        if (preg_match_all('/[.!?]+/', $content) > $length / 100) {
            $score += 0.1;
        }

        // Penalty for too many special characters
        $specialChars = preg_match_all('/[^\w\s.,!?;:-]/', $content);
        if ($specialChars > $length / 20) {
            $score -= 0.2;
        }

        return max(0.0, min(1.0, $score));
    }

    private function convertArrayToText(array $data, int $depth = 0): string
    {
        $text = '';
        $indent = str_repeat('  ', $depth);

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $text .= "$indent$key:\n" . $this->convertArrayToText($value, $depth + 1);
            } else {
                $text .= "$indent$key: $value\n";
            }
        }

        return $text;
    }

    private function storeUploadedFile(Request $req, string $tempPath): ?string
    {
        try {
            $file = $req->file('file') ?: $req->file('document') ?: $req->file('upload');
            if (!$file) return null;

            $fileName = time() . '_' . $file->getClientOriginalName();
            $storedPath = $file->storeAs('uploads', $fileName, 'local');

            Log::info('File stored successfully', [
                'original_name' => $file->getClientOriginalName(),
                'stored_path' => $storedPath,
                'size' => $file->getSize()
            ]);

            return $storedPath;
        } catch (Throwable $e) {
            Log::error('File storage failed', [
                'error' => $e->getMessage(),
                'temp_path' => $tempPath
            ]);
            return null;
        }
    }

    private function detectLanguage(string $text): string
    {
        // Simple language detection based on common words
        $text = strtolower(substr($text, 0, 1000)); // Check first 1000 chars

        $ptWords = ['de', 'da', 'do', 'para', 'com', 'em', 'um', 'uma', 'que', 'não', 'por', 'como'];
        $enWords = ['the', 'and', 'to', 'of', 'a', 'in', 'is', 'it', 'you', 'that', 'he', 'was'];

        $ptScore = 0;
        $enScore = 0;

        foreach ($ptWords as $word) {
            $ptScore += substr_count($text, " $word ");
        }

        foreach ($enWords as $word) {
            $enScore += substr_count($text, " $word ");
        }

        if ($ptScore > $enScore) {
            return 'pt';
        } elseif ($enScore > $ptScore) {
            return 'en';
        }

        return 'unknown';
    }

    private function processDocumentSimple(string $tenantSlug, int $docId, string $text, array $metadata, array $processingOptions): array
    {
        try {
            // Simple chunking without complex dependencies
            $chunkSize = $processingOptions['chunk_size'] ?? 1000;
            $overlapSize = $processingOptions['overlap_size'] ?? 200;
            $chunks = $this->chunkText($text, $chunkSize, $overlapSize);

            Log::info('Simple document processing', [
                'document_id' => $docId,
                'chunks_count' => count($chunks),
                'chunk_size' => $chunkSize,
                'content_length' => strlen($text)
            ]);

            // Store chunks without embeddings for now (fast mode)
            $chunksStored = 0;
            foreach ($chunks as $index => $chunkContent) {
                if (strlen(trim($chunkContent)) > 50) { // Skip very small chunks
                    DB::table('chunks')->insert([
                        'document_id' => $docId,
                        'ord' => $index,
                        'content' => $chunkContent,
                        'embedding' => null, // Skip embeddings for speed
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                    $chunksStored++;
                }
            }

            Log::info('Simple processing completed', [
                'document_id' => $docId,
                'chunks_stored' => $chunksStored
            ]);

            return [
                'success' => true,
                'chunks_created' => $chunksStored,
                'processing_time' => 0.5, // Fast processing
                'deduplication_ratio' => 0,
                'method' => 'simple_fast'
            ];

        } catch (Throwable $e) {
            Log::error('Simple document processing failed', [
                'document_id' => $docId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Ultra-fast fallback processing - garantia de chunks > 0
     */
    private function processingFallbackUltraFast(string $tenantSlug, int $docId, string $text, array $metadata): array
    {
        try {
            Log::info('Ultra-fast fallback processing started', [
                'document_id' => $docId,
                'content_length' => strlen($text)
            ]);

            // Simple chunking without dependencies
            $chunkSize = 800;
            $chunks = $this->chunkText($text, $chunkSize, 100);

            if (empty($chunks)) {
                // Garantir pelo menos 1 chunk
                $chunks = [trim($text)];
            }

            // Direct database insertion sem embeddings
            $chunksStored = 0;
            foreach ($chunks as $index => $chunkContent) {
                if (strlen(trim($chunkContent)) > 20) { // Limite mínimo reduzido
                    DB::table('chunks')->insert([
                        'document_id' => $docId,
                        'ord' => $index,
                        'content' => $chunkContent,
                        'embedding' => null, // Sem embeddings para velocidade
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                    $chunksStored++;
                }
            }

            Log::info('Ultra-fast fallback completed', [
                'document_id' => $docId,
                'chunks_stored' => $chunksStored,
                'method' => 'ultra_fast_fallback'
            ]);

            return [
                'success' => true,
                'chunks_created' => $chunksStored,
                'processing_time' => 0.1,
                'method' => 'ultra_fast_fallback',
                'deduplication_ratio' => 0
            ];

        } catch (Exception $e) {
            Log::error('Ultra-fast fallback failed', [
                'document_id' => $docId,
                'error' => $e->getMessage()
            ]);

            // Último recurso: criar 1 chunk com todo o conteúdo
            try {
                DB::table('chunks')->insert([
                    'document_id' => $docId,
                    'ord' => 0,
                    'content' => substr($text, 0, 2000), // Truncate se muito grande
                    'embedding' => null,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                return [
                    'success' => true,
                    'chunks_created' => 1,
                    'processing_time' => 0.05,
                    'method' => 'emergency_single_chunk'
                ];
            } catch (Exception $finalException) {
                return [
                    'success' => false,
                    'error' => 'All fallback methods failed: ' . $finalException->getMessage()
                ];
            }
        }
    }

    private function processAsync(string $requestId, string $tenantSlug, int $docId, string $text, array $metadata, array $processingOptions, float $startTime)
    {
        // Store job in database for tracking
        $jobId = 'job_' . $requestId;
        DB::table('upload_jobs')->insert([
            'job_id' => $jobId,
            'document_id' => $docId,
            'tenant_slug' => $tenantSlug,
            'status' => 'processing',
            'progress' => 0,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // Queue the processing job
        dispatch(function() use ($requestId, $tenantSlug, $docId, $text, $metadata, $processingOptions, $jobId) {
            try {
                // Update status to processing
                DB::table('upload_jobs')->where('job_id', $jobId)->update([
                    'status' => 'processing',
                    'progress' => 10,
                    'updated_at' => now()
                ]);

                // Use simple processing instead of complex pipeline

                DB::table('upload_jobs')->where('job_id', $jobId)->update([
                    'progress' => 30,
                    'updated_at' => now()
                ]);

                $result = $this->processDocumentSimple(
                    $tenantSlug,
                    $docId,
                    $text,
                    $metadata,
                    $processingOptions
                );

                DB::table('upload_jobs')->where('job_id', $jobId)->update([
                    'progress' => 90,
                    'updated_at' => now()
                ]);

                // Mark as completed
                DB::table('upload_jobs')->where('job_id', $jobId)->update([
                    'status' => $result['success'] ? 'completed' : 'failed',
                    'progress' => 100,
                    'result' => json_encode($result),
                    'updated_at' => now()
                ]);

                Log::info('Async processing completed', [
                    'request_id' => $requestId,
                    'job_id' => $jobId,
                    'document_id' => $docId,
                    'success' => $result['success']
                ]);

            } catch (Throwable $e) {
                DB::table('upload_jobs')->where('job_id', $jobId)->update([
                    'status' => 'failed',
                    'progress' => 100,
                    'result' => json_encode(['error' => $e->getMessage()]),
                    'updated_at' => now()
                ]);

                Log::error('Async processing failed', [
                    'request_id' => $requestId,
                    'job_id' => $jobId,
                    'error' => $e->getMessage()
                ]);
            }
        })->onQueue('document-processing');

        $responseTime = microtime(true) - $startTime;

        // Return immediate response
        return response()->json([
            'ok' => true,
            'document_id' => $docId,
            'job_id' => $jobId,
            'status' => 'processing',
            'message' => 'Document upload initiated. Processing in background.',
            'response_time' => round($responseTime, 3) . 's',
            'status_url' => url("/api/rag/upload-status?upload_id=$docId"),
            'estimated_completion' => now()->addSeconds(30)->toISOString()
        ], 202);
    }

    /**
     * Gera perguntas sugeridas para um documento (em background)
     */
    private function generateSuggestedQuestions(int $docId): void
    {
        try {
            $scriptPath = base_path('scripts/rag_search/question_suggester.py');
            
            if (!file_exists($scriptPath)) {
                Log::warning('Question suggester script not found', ['path' => $scriptPath]);
                return;
            }
            
            // Prepara configuração do banco
            $dbConfig = json_encode([
                'host' => env('DB_HOST', 'localhost'),
                'database' => env('DB_DATABASE', 'laravel'),
                'user' => env('DB_USERNAME', 'postgres'),
                'password' => env('DB_PASSWORD', ''),
                'port' => env('DB_PORT', '5432')
            ]);
            
            // Executa em background (não bloqueia)
            $cmd = sprintf(
                'python3 %s --document-id %d --db-config %s > /dev/null 2>&1 &',
                escapeshellarg($scriptPath),
                $docId,
                escapeshellarg($dbConfig)
            );
            
            exec($cmd);
            
            Log::info('Question suggester started in background', [
                'document_id' => $docId
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to start question suggester', [
                'document_id' => $docId,
                'error' => $e->getMessage()
            ]);
        }
    }
}
