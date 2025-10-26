<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
use App\Services\DocumentPageValidator;
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
        // Get tenant_slug from authenticated user - try multiple guards
        $user = null;
        $tenantSlug = 'default';
        
        // Try web guard first (for session-based auth)
        if (Auth::guard('web')->check()) {
            $user = Auth::guard('web')->user();
            $tenantSlug = "user_{$user->id}";
        }
        // Try sanctum guard (for API tokens)
        elseif (Auth::guard('sanctum')->check()) {
            $user = Auth::guard('sanctum')->user();
            $tenantSlug = "user_{$user->id}";
        }
        // Fallback to default auth
        elseif (auth()->check()) {
            $user = auth()->user();
            $tenantSlug = "user_{$user->id}";
        }
        
        $docs = DB::table('documents')
            ->where('tenant_slug', $tenantSlug)
            ->orderByDesc('created_at')
            ->get(['id','title','source','created_at','metadata']);

        $counts = DB::table('chunks')
            ->select('document_id', DB::raw('COUNT(*) as n'))
            ->groupBy('document_id')
            ->pluck('n','document_id');

        foreach ($docs as $d) {
            $d->chunks = (int)($counts[$d->id] ?? 0);
        }

        return response()->json(['ok' => true, 'docs' => $docs, 'tenant' => $tenantSlug]);
    }
    
    public function getDocument($id)
    {
        $doc = DB::table('documents')
            ->where('id', $id)
            ->first(['id','title','source','created_at','metadata','uri']);
        
        if (!$doc) {
            return response()->json(['error' => 'Document not found'], 404);
        }
        
        return response()->json($doc);
    }
    
    public function getDocumentChunks($id)
    {
        $chunks = DB::table('chunks')
            ->where('document_id', $id)
            ->orderBy('ord')
            ->get(['id','content','ord','meta']);
        
        return response()->json($chunks);
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
        // Proxy para FastAPI (sistema real de ingestão)
        try {
            $fastApiUrl = 'http://localhost:8002/api/rag/ingest';
            
            // Preparar dados para o FastAPI
            $formData = [];
            
            // Adicionar arquivo se presente
            if ($req->hasFile('file')) {
                $file = $req->file('file');
                $formData['file'] = new \CURLFile($file->getRealPath(), $file->getMimeType(), $file->getClientOriginalName());
            }
            
            // Adicionar outros campos
            if ($req->input('title')) {
                $formData['title'] = $req->input('title');
            }
            if ($req->input('text')) {
                $formData['text'] = $req->input('text');
            }
            if ($req->input('url')) {
                $formData['url'] = $req->input('url');
            }
            if ($req->input('tenant_slug')) {
                $formData['tenant_slug'] = $req->input('tenant_slug');
            }
            
            // Fazer requisição para o FastAPI
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $fastApiUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $formData);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 300); // 5 minutos
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Accept: application/json'
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($error) {
                throw new \Exception("Erro na comunicação com FastAPI: " . $error);
            }
            
            // Retornar resposta do FastAPI
            $responseData = json_decode($response, true);
            return response()->json($responseData, $httpCode);
            
        } catch (\Exception $e) {
            Log::error('Erro no proxy para FastAPI', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'ok' => false,
                'error' => 'Erro interno do servidor: ' . $e->getMessage()
            ], 500);
        }
    }

    public function ingest_old(Request $req)
    {
        // Optimize PHP settings for large file processing (up to 5000 pages)
        ini_set('max_execution_time', 0); // Sem limite de tempo para arquivos de até 5000 páginas
        ini_set('memory_limit', '4G'); // 4GB para arquivos de até 5000 páginas
        set_time_limit(300);

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

            // Get tenant_slug from authenticated user (multi-user support)
            $user = auth()->user();
            $tenantSlug = $user ? "user_{$user->id}" : $req->input('tenant_slug', 'default');
            
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

        // Get tenant_slug from authenticated user
        $user = auth('sanctum')->user();
        $tenantSlug = $user ? "user_{$user->id}" : $req->input('tenant_slug', 'default');
        
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
        
        // Get tenant_slug from authenticated user
        $user = auth('sanctum')->user();
        $tenantSlug = $user ? "user_{$user->id}" : $req->input('tenant_slug', 'default');

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
            
            // Get tenant_slug from authenticated user
            $user = auth('sanctum')->user();
            $tenantSlug = $user ? "user_{$user->id}" : $req->input('tenant_slug', 'default');

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
            
            // Get tenant_slug from authenticated user
            $user = auth('sanctum')->user();
            $tenantSlug = $user ? "user_{$user->id}" : $req->input('tenant_slug', 'default');
            
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
            // Get tenant_slug from authenticated user
            $user = auth('sanctum')->user();
            $tenantSlug = $user ? "user_{$user->id}" : $req->input('tenant_slug', 'default');
            
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
            
            // Get tenant_slug from authenticated user
            $user = auth('sanctum')->user();
            $tenantSlug = $user ? "user_{$user->id}" : $req->input('tenant_slug', 'default');

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
        // Optimized for large texts (up to 5000 pages)
        $text = preg_replace("/\r\n|\r/","\n",$text);
        $text = preg_replace("/\n{3,}/","\n\n",$text);
        $text = trim($text);
        $len = mb_strlen($text);
        
        // For very large texts (> 5MB), use byte-based chunking (much faster)
        if ($len > 5000000) {
            return $this->fastChunkText($text, $window, $overlap);
        }
        
        $chunks = [];
        $i = 0;
        
        while ($i < $len) {
            $end = min($len, $i + $window);
            $chunk = mb_substr($text, $i, $end - $i);
            
            $trimmed = trim($chunk);
            if ($trimmed !== '') {
                $chunks[] = $trimmed;
            }
            
            if ($end >= $len) break;
            $i = max(0, $end - $overlap);
            
            // Reset timer every 1000 chunks to prevent timeout on gigantic files
            if (count($chunks) % 1000 == 0) {
                set_time_limit(300);
            }
        }
        
        return array_values($chunks);
    }
    
    private function fastChunkText(string $text, int $window = 1000, int $overlap = 200): array
    {
        // Fast byte-based chunking for gigantic texts (5000+ pages)
        // Uses strlen/substr instead of mb_strlen/mb_substr for 10x speed
        $len = strlen($text);
        $chunks = [];
        $i = 0;
        
        while ($i < $len) {
            $end = min($len, $i + $window);
            $chunk = substr($text, $i, $end - $i);
            
            $trimmed = trim($chunk);
            if ($trimmed !== '') {
                $chunks[] = $trimmed;
            }
            
            if ($end >= $len) break;
            $i = max(0, $end - $overlap);
            
            // Reset timer every 1000 chunks
            if (count($chunks) % 1000 == 0) {
                set_time_limit(300);
            }
        }
        
        return array_values($chunks);
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

        // Basic validation - 500MB limit for large documents (up to 5000 pages)
        if ($fileSize > 500 * 1024 * 1024) { // 500MB limit
            return [
                'success' => false,
                'error' => 'Arquivo muito grande. Limite: 500MB (~5.000 páginas)'
            ];
        }

        // Validate page count (generic for all formats)
        $validator = new DocumentPageValidator();
        $pageValidation = $validator->validatePageLimit($file->getPathname(), $ext);
        
        if (!$pageValidation['valid']) {
            Log::warning("Document exceeds page limit", [
                'file' => $originalName,
                'estimated_pages' => $pageValidation['estimated_pages'],
                'max_pages' => $pageValidation['max_pages']
            ]);
            
            return [
                'success' => false,
                'error' => $pageValidation['message'],
                'estimated_pages' => $pageValidation['estimated_pages']
            ];
        }

        Log::info("Document page validation passed", [
            'file' => $originalName,
            'estimated_pages' => $pageValidation['estimated_pages']
        ]);

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

                case 'pptx':
                case 'ppt':
                    return $this->extractFromPowerPoint($path);

                case 'xml':
                    return $this->extractFromXml($path);

                case 'rtf':
                    return $this->extractFromRtf($path);

                case 'png':
                case 'jpg':
                case 'jpeg':
                case 'gif':
                case 'bmp':
                case 'tiff':
                case 'tif':
                case 'webp':
                    return $this->extractFromImage($path);

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

        // If no text extracted, might be scanned - try OCR before failing
        if (!$bestContent || strlen(trim($bestContent)) < 50) {
            Log::info("PDF has little/no text, checking if it's scanned");
            
            // Try OCR as last resort
            try {
                $ocrScriptPath = base_path('scripts/document_extraction/pdf_ocr_processor.py');
                if (file_exists($ocrScriptPath)) {
                    $adaptiveTimeout = $this->calculateOCRTimeout(filesize($path));
                    $ocrCmd = "timeout {$adaptiveTimeout}s python3 " . escapeshellarg($ocrScriptPath) . " " . escapeshellarg($path) . " por+eng 2>/dev/null";
                    $ocrOutput = shell_exec($ocrCmd);
                    
                    if ($ocrOutput && strlen(trim($ocrOutput)) > 50) {
                        Log::info("OCR extraction successful for scanned PDF");
                        
                        return [
                            'success' => true,
                            'content' => trim($ocrOutput),
                            'method' => 'pdf_ocr_scanned',
                            'quality_score' => 0.8,
                            'metadata' => [
                                'extraction_method' => 'ocr_scanned',
                                'is_scanned' => true
                            ]
                        ];
                    }
                }
            } catch (\Exception $e) {
                Log::debug("OCR fallback failed", ['error' => $e->getMessage()]);
            }
            
            return [
                'success' => false,
                'error' => 'Nenhum método de extração PDF funcionou. Instale poppler-utils ou python-pymupdf.'
            ];
        }

        // NEW: Try to extract tables (if any) - NON-BLOCKING
        $tablesContent = '';
        $tablesFound = 0;
        try {
            $tablesScriptPath = base_path('scripts/document_extraction/pdf_tables_extractor.py');
            if (file_exists($tablesScriptPath)) {
                $adaptiveTimeout = $this->calculateTableExtractionTimeout(filesize($path));
                $tablesCmd = "timeout {$adaptiveTimeout}s python3 " . escapeshellarg($tablesScriptPath) . " " . escapeshellarg($path) . " 2>/dev/null";
                $tablesOutput = shell_exec($tablesCmd);
                
                if ($tablesOutput) {
                    $tablesResult = json_decode($tablesOutput, true);
                    if ($tablesResult && $tablesResult['success'] && $tablesResult['tables_found'] > 0) {
                        $tablesFound = $tablesResult['tables_found'];
                        
                        // Append tables text to content
                        $tablesTextParts = [];
                        foreach ($tablesResult['tables'] as $table) {
                            $tablesTextParts[] = $table['text'];
                        }
                        $tablesContent = "\n\n" . implode("\n\n", $tablesTextParts);
                        
                        Log::info("PDF tables extracted", [
                            'path' => $path,
                            'tables_found' => $tablesFound
                        ]);
                    }
                }
            }
        } catch (\Exception $e) {
            // Silent fail - tables extraction is optional
            Log::debug("Table extraction failed (non-critical)", ['error' => $e->getMessage()]);
        }

        // NEW: Try OCR on images (if PDF has images) - NON-BLOCKING
        $ocrContent = '';
        $imagesProcessed = 0;
        $hasImages = false;
        $isScanned = false;
        
        try {
            $ocrScriptPath = base_path('scripts/document_extraction/pdf_ocr_processor.py');
            if (file_exists($ocrScriptPath)) {
                // First check if PDF has images (quick check)
                $checkCmd = "python3 " . base_path('scripts/document_extraction/pdf_image_extractor.py') . " " . escapeshellarg($path) . " --check-only 2>/dev/null";
                $checkOutput = shell_exec($checkCmd);
                
                if ($checkOutput) {
                    $checkResult = json_decode($checkOutput, true);
                    if ($checkResult && $checkResult['success']) {
                        $hasImages = $checkResult['has_images'] ?? false;
                        $isScanned = $checkResult['is_scanned'] ?? false;
                        
                        // If has images OR is scanned OR has very little text, try OCR
                        $shouldTryOcr = $hasImages || $isScanned || strlen(trim($bestContent)) < 100;
                        
                        if ($shouldTryOcr) {
                            Log::info("PDF has images, attempting OCR", [
                                'path' => $path,
                                'has_images' => $hasImages,
                                'is_scanned' => $isScanned,
                                'text_length' => strlen($bestContent)
                            ]);
                            
                            // Run OCR processor (timeout adaptativo para arquivos grandes)
                            $adaptiveTimeout = $this->calculateOCRTimeout(filesize($path));
                            $ocrCmd = "timeout {$adaptiveTimeout}s python3 " . escapeshellarg($ocrScriptPath) . " " . escapeshellarg($path) . " por+eng 2>/dev/null";
                            $ocrOutput = shell_exec($ocrCmd);
                            
                            if ($ocrOutput && strlen(trim($ocrOutput)) > 50) {
                                // OCR processor returns text directly
                                $ocrContent = trim($ocrOutput);
                                
                                // If it's a scanned PDF with little direct text, replace content
                                if ($isScanned || strlen(trim($bestContent)) < 100) {
                                    $bestContent = $ocrContent;
                                    $usedMethod .= '_ocr_scanned';
                                } else {
                                    // Otherwise append OCR text
                                    $ocrContent = "\n\n=== TEXTO DE IMAGENS (OCR) ===\n\n" . $ocrContent;
                                }
                                
                                $imagesProcessed = $checkResult['image_count'] ?? 0;
                                
                                Log::info("PDF OCR completed", [
                                    'path' => $path,
                                    'images_processed' => $imagesProcessed,
                                    'ocr_text_length' => strlen($ocrContent)
                                ]);
                            }
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            // Silent fail - OCR is optional
            Log::debug("OCR extraction failed (non-critical)", ['error' => $e->getMessage()]);
        }

        // Combine all content
        $finalContent = $bestContent . $tablesContent;
        if ($ocrContent && !$isScanned) {
            $finalContent .= $ocrContent;
        }

        // Determine final method name
        $methodSuffix = '';
        if ($tablesFound > 0) $methodSuffix .= '_tables';
        if ($imagesProcessed > 0) $methodSuffix .= '_ocr';

        return [
            'success' => true,
            'content' => trim($finalContent),
            'method' => "pdf_$usedMethod" . $methodSuffix,
            'quality_score' => $bestScore + ($tablesFound > 0 ? 0.05 : 0) + ($imagesProcessed > 0 ? 0.1 : 0),
            'metadata' => [
                'extraction_method' => $usedMethod,
                'tables_found' => $tablesFound,
                'has_tables' => $tablesFound > 0,
                'has_images' => $hasImages,
                'images_processed' => $imagesProcessed,
                'is_scanned' => $isScanned
            ]
        ];
    }

    private function extractFromWord(string $path): array
    {
        Log::debug("Extracting DOCX", ['path' => $path, 'exists' => file_exists($path), 'size' => filesize($path)]);
        
        // Try Python docx extraction
        $content = $this->runPythonExtractor('docx_extractor.py', $path);

        Log::debug("DOCX extraction result", ['content_length' => strlen($content ?? ''), 'has_content' => !empty($content)]);

        if ($content && strlen(trim($content)) > 5) {
            // NEW: Try to extract tables too (optional, non-blocking)
            $tablesContent = '';
            $tablesFound = 0;
            try {
                $tablesScript = base_path('scripts/document_extraction/docx_tables_extractor.py');
                if (file_exists($tablesScript)) {
                    $adaptiveTimeout = $this->calculateTableExtractionTimeout(filesize($path));
                    $tablesCmd = "timeout {$adaptiveTimeout}s python3 " . escapeshellarg($tablesScript) . " " . escapeshellarg($path) . " 2>/dev/null";
                    $tablesOutput = shell_exec($tablesCmd);
                    
                    if ($tablesOutput) {
                        $tablesResult = json_decode($tablesOutput, true);
                        if ($tablesResult && $tablesResult['success'] && $tablesResult['tables_found'] > 0) {
                            $tablesFound = $tablesResult['tables_found'];
                            $tablesTextParts = [];
                            foreach ($tablesResult['tables'] as $table) {
                                $tablesTextParts[] = $table['text'];
                            }
                            $tablesContent = "\n\n" . implode("\n\n", $tablesTextParts);
                        }
                    }
                }
            } catch (\Exception $e) {
                // Silent fail
            }
            
            return [
                'success' => true,
                'content' => trim($content . $tablesContent),
                'method' => 'python_docx' . ($tablesFound > 0 ? '_with_tables' : ''),
                'quality_score' => 0.9 + ($tablesFound > 0 ? 0.05 : 0),
                'metadata' => [
                    'extractor' => 'python_docx',
                    'tables_found' => $tablesFound,
                    'has_tables' => $tablesFound > 0
                ]
            ];
        }

        // Fallback to universal extractor
        $content = $this->runPythonExtractor('universal_extractor.py', $path);
        
        if ($content && strlen(trim($content)) > 5) {
            return [
                'success' => true,
                'content' => trim($content),
                'method' => 'python_universal',
                'quality_score' => 0.8,
                'metadata' => ['extractor' => 'universal_python']
            ];
        }

        return [
            'success' => false,
            'error' => 'Falha na extração DOCX. Verifique se o arquivo está corrompido.'
        ];
    }

    private function extractFromExcel(string $path): array
    {
        // NEW: Try structured extractor first (returns text + JSON)
        $structuredService = new \App\Services\ExcelStructuredService();
        $structuredResult = $structuredService->extractStructured($path);

        if ($structuredResult && $structuredResult['success'] && !empty($structuredResult['text'])) {
            Log::info("Excel structured extraction successful", [
                'path' => $path,
                'sheets' => $structuredResult['structured_data']['metadata']['total_sheets'] ?? 0,
                'rows' => $structuredResult['structured_data']['metadata']['total_rows'] ?? 0
            ]);

            return [
                'success' => true,
                'content' => trim($structuredResult['text']),
                'method' => 'python_structured_openpyxl',
                'quality_score' => 0.9,
                'metadata' => [
                    'extractor' => 'python_structured',
                    'structured_data' => $structuredResult['structured_data'], // Store JSON data
                    'chunking_hints' => $structuredResult['chunking_hints'] ?? null
                ]
            ];
        }

        // Fallback 1: Simple text extractor (backward compatibility)
        $content = $this->runPythonExtractor('excel_extractor.py', $path);

        if ($content && strlen(trim($content)) > 5) {
            return [
                'success' => true,
                'content' => trim($content),
                'method' => 'python_openpyxl',
                'quality_score' => 0.8,
                'metadata' => ['extractor' => 'python_openpyxl']
            ];
        }

        // Fallback 2: Universal extractor
        $content = $this->runPythonExtractor('universal_extractor.py', $path);
        
        if ($content && strlen(trim($content)) > 5) {
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
            'error' => 'Falha na extração Excel. Verifique se o arquivo está corrompido.'
        ];
    }

    private function extractFromXml(string $path): array
    {
        // Use Python extractor for XML (better structure preservation)
        $content = $this->runPythonExtractor('universal_extractor.py', $path);

        if ($content) {
            return [
                'success' => true,
                'content' => trim($content),
                'method' => 'python_xml',
                'quality_score' => 0.9,
                'metadata' => ['extractor' => 'python_lxml']
            ];
        }

        // Fallback: simple text extraction
        $xml = file_get_contents($path);
        $text = strip_tags($xml);
        
        return [
            'success' => true,
            'content' => trim($text),
            'method' => 'php_strip_tags',
            'quality_score' => 0.6,
            'metadata' => ['extractor' => 'php_fallback']
        ];
    }

    private function extractFromPowerPoint(string $path): array
    {
        // NEW: Try enhanced extractor first (slide-by-slide + tables + notes)
        $scriptPath = base_path('scripts/document_extraction/pptx_enhanced_extractor.py');
        
        if (file_exists($scriptPath)) {
            try {
                $adaptiveTimeout = $this->calculateAdaptiveTimeout(filesize($path), 120);
                $cmd = "timeout {$adaptiveTimeout}s python3 " . escapeshellarg($scriptPath) . " " . escapeshellarg($path) . " 2>/dev/null";
                $output = shell_exec($cmd);
                
                if ($output) {
                    $result = json_decode($output, true);
                    if ($result && $result['success'] && !empty($result['text'])) {
                        Log::info("PPTX enhanced extraction successful", [
                            'path' => $path,
                            'slides' => $result['metadata']['total_slides'] ?? 0
                        ]);

                        return [
                            'success' => true,
                            'content' => trim($result['text']),
                            'method' => 'python_enhanced_pptx',
                            'quality_score' => 0.9,
                            'metadata' => array_merge(
                                ['extractor' => 'python_enhanced_pptx'],
                                $result['metadata'] ?? [],
                                ['chunking_hints' => $result['chunking_hints'] ?? null]
                            )
                        ];
                    }
                }
            } catch (\Exception $e) {
                Log::debug("PPTX enhanced extraction failed, using fallback", ['error' => $e->getMessage()]);
            }
        }

        // Fallback 1: Simple PPTX extractor
        $content = $this->runPythonExtractor('pptx_extractor.py', $path);

        if ($content && strlen(trim($content)) > 5) {
            return [
                'success' => true,
                'content' => trim($content),
                'method' => 'python_pptx',
                'quality_score' => 0.85,
                'metadata' => ['extractor' => 'python_pptx']
            ];
        }

        // Fallback 2: Universal extractor
        $content = $this->runPythonExtractor('universal_extractor.py', $path);
        
        if ($content && strlen(trim($content)) > 5) {
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
            'error' => 'Falha na extração PowerPoint. Verifique se o arquivo está corrompido.'
        ];
    }

    private function extractFromRtf(string $path): array
    {
        $content = $this->runPythonExtractor('rtf_extractor.py', $path);

        if ($content && strlen(trim($content)) > 5) {
            return [
                'success' => true,
                'content' => trim($content),
                'method' => 'python_rtf',
                'quality_score' => 0.75,
                'metadata' => ['extractor' => 'python_striprtf']
            ];
        }

        // Fallback to universal
        $content = $this->runPythonExtractor('universal_extractor.py', $path);
        
        if ($content && strlen(trim($content)) > 5) {
            return [
                'success' => true,
                'content' => trim($content),
                'method' => 'python_universal',
                'quality_score' => 0.6,
                'metadata' => ['extractor' => 'universal_python']
            ];
        }

        return [
            'success' => false,
            'error' => 'Falha na extração RTF. Verifique se o arquivo está corrompido.'
        ];
    }

    private function extractFromHtml(string $path): array
    {
        $html = file_get_contents($path);
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', $text);

        // NEW: Try to extract HTML tables (optional, non-blocking)
        $tablesContent = '';
        $tablesFound = 0;
        try {
            $tablesScript = base_path('scripts/document_extraction/html_tables_extractor.py');
            if (file_exists($tablesScript)) {
                $adaptiveTimeout = $this->calculateTableExtractionTimeout(filesize($path));
                $tablesCmd = "timeout {$adaptiveTimeout}s python3 " . escapeshellarg($tablesScript) . " " . escapeshellarg($path) . " 2>/dev/null";
                $tablesOutput = shell_exec($tablesCmd);
                
                if ($tablesOutput) {
                    $tablesResult = json_decode($tablesOutput, true);
                    if ($tablesResult && $tablesResult['success'] && $tablesResult['tables_found'] > 0) {
                        $tablesFound = $tablesResult['tables_found'];
                        $tablesTextParts = [];
                        foreach ($tablesResult['tables'] as $table) {
                            $tablesTextParts[] = $table['text'];
                        }
                        $tablesContent = "\n\n" . implode("\n\n", $tablesTextParts);
                    }
                }
            }
        } catch (\Exception $e) {
            // Silent fail
        }

        return [
            'success' => true,
            'content' => trim($text . $tablesContent),
            'method' => 'html_strip_tags' . ($tablesFound > 0 ? '_with_tables' : ''),
            'quality_score' => 0.7 + ($tablesFound > 0 ? 0.1 : 0),
            'metadata' => [
                'original_html_length' => strlen($html),
                'tables_found' => $tablesFound,
                'has_tables' => $tablesFound > 0
            ]
        ];
    }

    private function extractFromCsv(string $path): array
    {
        // NEW: Try structured extractor first (same as Excel)
        $scriptPath = base_path('scripts/document_extraction/csv_structured_extractor.py');
        
        if (file_exists($scriptPath)) {
            try {
                $cmd = "python3 " . escapeshellarg($scriptPath) . " " . escapeshellarg($path) . " 2>/dev/null";
                $output = shell_exec($cmd);
                
                if ($output) {
                    $result = json_decode($output, true);
                    if ($result && $result['success'] && !empty($result['text'])) {
                        Log::info("CSV structured extraction successful", [
                            'path' => $path,
                            'rows' => $result['structured_data']['row_count'] ?? 0
                        ]);

                        return [
                            'success' => true,
                            'content' => trim($result['text']),
                            'method' => 'python_structured_csv',
                            'quality_score' => 0.9,
                            'metadata' => [
                                'extractor' => 'python_structured',
                                'structured_data' => $result['structured_data'],
                                'chunking_hints' => $result['chunking_hints'] ?? null
                            ]
                        ];
                    }
                }
            } catch (\Exception $e) {
                Log::debug("CSV structured extraction failed, using fallback", ['error' => $e->getMessage()]);
            }
        }

        // Fallback 1: Simple CSV extractor
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

        // Fallback 2: Basic PHP CSV reading
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

    private function extractFromImage(string $path): array
    {
        // Extract text from images using OCR (Tesseract via Python)
        $timeoutSeconds = 120; // OCR can be slow on large/complex images
        $scriptPath = base_path('scripts/document_extraction/image_extractor_wrapper.py');
        
        Log::info("Extracting from image using OCR", [
            'path' => $path,
            'script' => $scriptPath
        ]);

        try {
            // Calcula timeout adaptativo baseado no tamanho da imagem (OCR)
            $adaptiveTimeout = $this->calculateOCRTimeout(filesize($path));
            $cmd = "timeout {$adaptiveTimeout}s python3 " . escapeshellarg($scriptPath) . " " . escapeshellarg($path) . " 2>/dev/null";
            $output = shell_exec($cmd);
            
            if ($output === null || trim($output) === '') {
                Log::warning("OCR extraction returned empty result", ['path' => $path]);
                return [
                    'success' => false,
                    'error' => 'OCR failed to extract text from image'
                ];
            }

            $content = trim($output);
            
            // Check if output indicates no text was detected
            if (strpos($content, '[Image processed - no text detected]') !== false) {
                return [
                    'success' => true,
                    'content' => '',
                    'method' => 'ocr_tesseract',
                    'quality_score' => 0.5,
                    'metadata' => ['ocr_status' => 'no_text_detected']
                ];
            }

            return [
                'success' => true,
                'content' => $content,
                'method' => 'ocr_tesseract',
                'quality_score' => 0.8,
                'metadata' => [
                    'ocr_engine' => 'tesseract',
                    'text_length' => strlen($content)
                ]
            ];
        } catch (Throwable $e) {
            Log::error("Image extraction failed", [
                'path' => $path,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'OCR extraction error: ' . $e->getMessage()
            ];
        }
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

    private function runPythonExtractor(string $script, string $filePath, int $timeoutSeconds = 120): ?string
    {
        $scriptPath = base_path("scripts/document_extraction/$script");

        if (!file_exists($scriptPath)) {
            Log::warning("Python extractor not found: $script");
            return null;
        }

        // Calcula timeout adaptativo baseado no tamanho do arquivo
        if (file_exists($filePath)) {
            $fileSize = filesize($filePath);
            $adaptiveTimeout = $this->calculateAdaptiveTimeout($fileSize, $timeoutSeconds);
            Log::debug("Adaptive timeout calculated", [
                'file_size_mb' => round($fileSize / (1024 * 1024), 2),
                'original_timeout' => $timeoutSeconds,
                'adaptive_timeout' => $adaptiveTimeout
            ]);
            $timeoutSeconds = $adaptiveTimeout;
        }

        // Increased timeout for large files (up to 5000 pages)
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
            // Store in tenant-specific directory without 'uploads' prefix
            $tenantSlug = auth('sanctum')->user() ? 'user_' . auth('sanctum')->user()->id : 'default';
            $storedPath = $file->storeAs($tenantSlug, $fileName, 'local');

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
            // NEW: Check if we have structured data (Excel with intelligent chunking)
            $chunks = [];
            $chunkingMethod = 'standard';
            
            if (isset($metadata['structured_data']) && isset($metadata['chunking_hints'])) {
                // Use intelligent chunking for structured data (Excel)
                $structuredService = new \App\Services\ExcelStructuredService();
                $chunks = $structuredService->createIntelligentChunks($metadata['structured_data']);
                $chunkingMethod = 'intelligent_row_based';
                
                Log::info('Using intelligent chunking for structured data', [
                    'document_id' => $docId,
                    'chunks_count' => count($chunks),
                    'method' => $chunkingMethod
                ]);
            } else {
                // Standard chunking for regular documents
            $chunkSize = $processingOptions['chunk_size'] ?? 1000;
            $overlapSize = $processingOptions['overlap_size'] ?? 200;
            $chunks = $this->chunkText($text, $chunkSize, $overlapSize);

            Log::info('Simple document processing', [
                'document_id' => $docId,
                'chunks_count' => count($chunks),
                'chunk_size' => $chunkSize,
                'content_length' => strlen($text)
            ]);
            }

            // Store chunks without embeddings for now (fast mode)
            $chunksStored = 0;
            foreach ($chunks as $index => $chunkContent) {
                if (strlen(trim($chunkContent)) > 10) { // Skip very small chunks
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
                'python3 %s --document-id %d --db-config %s > /dev/null 2>/dev/null &',
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

    /**
     * Calcula timeout adaptativo baseado no tamanho do arquivo e tipo de processamento
     * Suporta arquivos de até 5000 páginas com timeouts otimizados
     */
    private function calculateAdaptiveTimeout(int $fileSizeBytes, int $defaultTimeout): int
    {
        $fileSizeMB = $fileSizeBytes / (1024 * 1024);
        
        // Timeout baseado no tamanho do arquivo
        if ($fileSizeMB < 1) {
            return 15; // 15s para arquivos pequenos (< 1MB)
        } elseif ($fileSizeMB < 5) {
            return 30; // 30s para arquivos pequenos-médios (1-5MB)
        } elseif ($fileSizeMB < 10) {
            return 60; // 1min para arquivos médios (5-10MB)
        } elseif ($fileSizeMB < 25) {
            return 120; // 2min para arquivos médios-grandes (10-25MB)
        } elseif ($fileSizeMB < 50) {
            return 180; // 3min para arquivos grandes (25-50MB)
        } elseif ($fileSizeMB < 100) {
            return 300; // 5min para arquivos grandes (50-100MB)
        } elseif ($fileSizeMB < 200) {
            return 450; // 7.5min para arquivos muito grandes (100-200MB)
        } elseif ($fileSizeMB < 300) {
            return 600; // 10min para arquivos gigantes (200-300MB)
        } elseif ($fileSizeMB < 400) {
            return 750; // 12.5min para arquivos gigantes (300-400MB)
        } else {
            return 900; // 15min para arquivos mega (400MB+ até 5000 páginas)
        }
    }

    /**
     * Calcula timeout específico para OCR baseado no tamanho do arquivo
     * OCR é mais lento que extração normal
     */
    private function calculateOCRTimeout(int $fileSizeBytes): int
    {
        $fileSizeMB = $fileSizeBytes / (1024 * 1024);
        
        // OCR é mais lento, timeouts maiores
        if ($fileSizeMB < 1) {
            return 30; // 30s para OCR em arquivos pequenos
        } elseif ($fileSizeMB < 5) {
            return 60; // 1min para OCR em arquivos pequenos-médios
        } elseif ($fileSizeMB < 10) {
            return 120; // 2min para OCR em arquivos médios
        } elseif ($fileSizeMB < 25) {
            return 240; // 4min para OCR em arquivos médios-grandes
        } elseif ($fileSizeMB < 50) {
            return 360; // 6min para OCR em arquivos grandes
        } elseif ($fileSizeMB < 100) {
            return 600; // 10min para OCR em arquivos grandes
        } elseif ($fileSizeMB < 200) {
            return 900; // 15min para OCR em arquivos muito grandes
        } elseif ($fileSizeMB < 300) {
            return 1200; // 20min para OCR em arquivos gigantes
        } elseif ($fileSizeMB < 400) {
            return 1500; // 25min para OCR em arquivos gigantes
        } else {
            return 1800; // 30min para OCR em arquivos mega (até 5000 páginas)
        }
    }

    /**
     * Calcula timeout para extração de tabelas baseado no tamanho do arquivo
     */
    private function calculateTableExtractionTimeout(int $fileSizeBytes): int
    {
        $fileSizeMB = $fileSizeBytes / (1024 * 1024);
        
        // Extração de tabelas é moderadamente lenta
        if ($fileSizeMB < 1) {
            return 20; // 20s para tabelas em arquivos pequenos
        } elseif ($fileSizeMB < 5) {
            return 40; // 40s para tabelas em arquivos pequenos-médios
        } elseif ($fileSizeMB < 10) {
            return 80; // 1.3min para tabelas em arquivos médios
        } elseif ($fileSizeMB < 25) {
            return 150; // 2.5min para tabelas em arquivos médios-grandes
        } elseif ($fileSizeMB < 50) {
            return 240; // 4min para tabelas em arquivos grandes
        } elseif ($fileSizeMB < 100) {
            return 360; // 6min para tabelas em arquivos grandes
        } elseif ($fileSizeMB < 200) {
            return 540; // 9min para tabelas em arquivos muito grandes
        } elseif ($fileSizeMB < 300) {
            return 720; // 12min para tabelas em arquivos gigantes
        } elseif ($fileSizeMB < 400) {
            return 900; // 15min para tabelas em arquivos gigantes
        } else {
            return 1080; // 18min para tabelas em arquivos mega (até 5000 páginas)
        }
    }

    /**
     * Verifica se o arquivo é muito grande e precisa de processamento especial
     */
    private function isVeryLargeFile(int $fileSizeBytes): bool
    {
        $fileSizeMB = $fileSizeBytes / (1024 * 1024);
        return $fileSizeMB > 100; // Arquivos > 100MB precisam de processamento especial
    }

    /**
     * Processa arquivo em background para arquivos muito grandes
     */
    private function processLargeFileInBackground(string $filePath, string $tenantSlug, string $title): array
    {
        $fileSizeMB = round(filesize($filePath) / (1024 * 1024), 2);
        
        Log::info("Iniciando processamento em background para arquivo grande", [
            'file' => $filePath,
            'size_mb' => $fileSizeMB,
            'tenant' => $tenantSlug
        ]);

        // Executa processamento em background
        $scriptPath = base_path('scripts/document_extraction/main_extractor.py');
        $cmd = sprintf(
            'nohup python3 %s %s > /dev/null 2>&1 &',
            escapeshellarg($scriptPath),
            escapeshellarg($filePath)
        );

        exec($cmd);

        return [
            'success' => true,
            'message' => "Arquivo grande ({$fileSizeMB}MB) sendo processado em background",
            'processing_mode' => 'background',
            'estimated_time' => $this->estimateProcessingTime($fileSizeMB)
        ];
    }

    /**
     * Estima tempo de processamento baseado no tamanho do arquivo
     */
    private function estimateProcessingTime(float $fileSizeMB): string
    {
        if ($fileSizeMB < 50) {
            return "2-5 minutos";
        } elseif ($fileSizeMB < 100) {
            return "5-10 minutos";
        } elseif ($fileSizeMB < 200) {
            return "10-20 minutos";
        } elseif ($fileSizeMB < 300) {
            return "20-30 minutos";
        } elseif ($fileSizeMB < 400) {
            return "30-45 minutos";
        } else {
            return "45-60 minutos";
        }
    }
}
