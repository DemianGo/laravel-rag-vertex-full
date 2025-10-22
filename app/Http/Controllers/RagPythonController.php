<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\DocumentCacheService;

class RagPythonController extends Controller
{
    /**
     * Busca RAG usando Python (rag_search.py)
     * Endpoint: POST /api/rag/python-search
     */
    public function pythonSearch(Request $request)
    {
        // Aumenta timeout para queries complexas
        set_time_limit(600); // 10 minutos
        try {
            // Validação dos parâmetros
            $query = $request->input('query');
            $documentId = $request->input('document_id');
            $topK = max(1, min(20, intval($request->input('top_k', 5))));
            $threshold = max(0.05, min(1.0, floatval($request->input('threshold', 0.3))));
            $includeAnswer = filter_var($request->input('include_answer', true), FILTER_VALIDATE_BOOLEAN);
            $strictness = max(0, min(3, intval($request->input('strictness', 2))));
        $mode = $request->input('mode', 'auto');
        $format = $request->input('format', 'plain');
        $length = $request->input('length', 'auto');
        $citations = max(0, min(10, intval($request->input('citations', 0))));
        $useFullDocument = filter_var($request->input('use_full_document', false), FILTER_VALIDATE_BOOLEAN);
            $useCache = filter_var($request->input('use_cache', true), FILTER_VALIDATE_BOOLEAN);

            if (!$query) {
                return response()->json([
                    'success' => false,
                    'error' => 'Parâmetro query é obrigatório'
                ], 422);
            }
            
            // OTIMIZAÇÃO: Detecta queries de resumo em vídeos
            $queryLower = mb_strtolower($query);
            $isResumoQuery = preg_match('/(resumo|resuma|sumarize|summarize|sobre o que|do que trata)/i', $queryLower);
            
            if ($isResumoQuery && $documentId) {
                // Verifica se é um vídeo
                $document = DB::table('documents')->where('id', $documentId)->first();
                
                if ($document && in_array($document->source, ['video_url', 'video_upload'])) {
                    // É um vídeo! Usa transcrição completa diretamente
                    Log::info('Query de resumo em vídeo detectada, usando transcrição completa', [
                        'document_id' => $documentId,
                        'source' => $document->source
                    ]);
                    
                    return $this->handleVideoSummary($query, $documentId, $document);
                }
                
                // Para outros documentos, limita top_k
                $topK = min($topK, 8);
                Log::info('Query de resumo detectada, limitando top_k', ['top_k' => $topK]);
            }

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
            
            // Verificar se documento existe e pertence ao usuário (se especificado)
            if ($documentId) {
                // Se há usuário autenticado, verifica tenant
                if ($user) {
                    $document = DB::table('documents')
                        ->where('id', $documentId)
                        ->where('tenant_slug', $tenantSlug)
                        ->exists();
                    
                    if (!$document) {
                        return response()->json([
                            'success' => false,
                            'error' => "Documento ID {$documentId} não encontrado ou não pertence ao usuário"
                        ], 404);
                    }
                } else {
                    // Se não há usuário autenticado, apenas verifica se documento existe
                    $document = DB::table('documents')
                        ->where('id', $documentId)
                        ->exists();
                    
                    if (!$document) {
                        return response()->json([
                            'success' => false,
                            'error' => "Documento ID {$documentId} não encontrado"
                        ], 404);
                    }
                }
            }
            
            // Try to get from cache first
            if ($useCache && $documentId) {
                $cacheService = new DocumentCacheService();
                $cached = $cacheService->getCachedSearchResult($documentId, $query);
                
                if ($cached) {
                    Log::info('Search result from cache', [
                        'document_id' => $documentId,
                        'query' => substr($query, 0, 50)
                    ]);
                    
                    $cached['metadata']['cache_hit'] = true;
                    return response()->json($cached);
                }
            }

            // Verificar se há chunks com embeddings
            $chunksWithEmbeddings = DB::table('chunks')->whereNotNull('embedding')->count();
            if ($chunksWithEmbeddings === 0) {
                return response()->json([
                    'success' => false,
                    'error' => 'Nenhum chunk com embeddings encontrado. Execute a ingestão com embeddings primeiro.'
                ], 422);
            }

            Log::info('Iniciando busca Python RAG', [
                'query' => $query,
                'document_id' => $documentId,
                'top_k' => $topK,
                'threshold' => $threshold,
                'include_answer' => $includeAnswer,
                'smart_mode' => $useSmartMode ?? true
            ]);

            // Construir comando Python
            // Usa Smart Router se parâmetro use_smart_mode=true, senão usa rag_search.py direto
            $useSmartMode = filter_var($request->input('use_smart_mode', true), FILTER_VALIDATE_BOOLEAN);
            $scriptPath = $useSmartMode 
                ? base_path('scripts/rag_search/smart_router.py')
                : base_path('scripts/rag_search/rag_search.py');
            
            $cmd = [
                'python3',
                $scriptPath,
                '--query', escapeshellarg($query),
                '--top-k', $topK,
                '--threshold', $threshold
            ];

            if ($documentId) {
                $cmd[] = '--document-id';
                $cmd[] = $documentId;
            }

            if (!$includeAnswer) {
                $cmd[] = '--no-llm';
            }

            // Adicionar strictness se diferente do padrão
            if ($strictness != 2) {
                $cmd[] = '--strictness';
                $cmd[] = $strictness;
            }

            // Adicionar novos parâmetros se diferentes dos padrões
            if ($mode !== 'auto') {
                $cmd[] = '--mode';
                $cmd[] = $mode;
            }
            
            if ($format !== 'plain') {
                $cmd[] = '--format';
                $cmd[] = $format;
            }
            
            if ($length !== 'auto') {
                $cmd[] = '--length';
                $cmd[] = $length;
            }
            
            if ($citations > 0) {
                $cmd[] = '--citations';
                $cmd[] = $citations;
            }
            
            if ($useFullDocument) {
                $cmd[] = '--use-full-document';
            }

            // Adicionar configuração do banco via JSON
            $dbConfig = [
                'host' => env('DB_HOST', 'localhost'),
                'database' => env('DB_DATABASE', 'laravel'),
                'user' => env('DB_USERNAME', 'postgres'),
                'password' => env('DB_PASSWORD', ''),
                'port' => env('DB_PORT', '5432')
            ];

            $cmd[] = '--db-config';
            $cmd[] = escapeshellarg(json_encode($dbConfig));

            // Executar comando Python
            $startTime = microtime(true);
            $command = implode(' ', $cmd);
            
            Log::debug('Executando comando Python', ['command' => $command]);
            
            $output = shell_exec($command . ' 2>/dev/null');
            $executionTime = microtime(true) - $startTime;

            if (!$output) {
                return response()->json([
                    'success' => false,
                    'error' => 'Falha ao executar script Python (sem output)',
                    'debug' => [
                        'command' => $command,
                        'execution_time' => round($executionTime, 3)
                    ]
                ], 500);
            }

            // Tentar decodificar JSON de resposta
            $result = json_decode(trim($output), true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Erro ao decodificar JSON do Python', [
                    'output' => $output,
                    'json_error' => json_last_error_msg()
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'Resposta inválida do script Python',
                    'debug' => [
                        'raw_output' => $output,
                        'json_error' => json_last_error_msg(),
                        'execution_time' => round($executionTime, 3)
                    ]
                ], 500);
            }

            // Adicionar metadados de execução
            $result['metadata']['python_execution_time'] = round($executionTime, 3);
            $result['metadata']['total_chunks_with_embeddings'] = $chunksWithEmbeddings;
            $result['metadata']['cache_hit'] = false;
            // Preservar o search_method que vem do Python (não sobrescrever)

            Log::info('Busca Python RAG concluída', [
                'success' => $result['success'] ?? false,
                'chunks_found' => count($result['chunks'] ?? []),
                'execution_time' => round($executionTime, 3)
            ]);

            // Cache result for future use (only if successful and document_id specified)
            $isSuccessful = ($result['success'] ?? false) || ($result['ok'] ?? false);
            if ($useCache && $documentId && $isSuccessful) {
                $cacheService = new DocumentCacheService();
                $cacheService->cacheSearchResult($documentId, $query, $result);
            }

            return response()->json($result);

        } catch (\Throwable $e) {
            Log::error('Erro na busca Python RAG', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Erro interno: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Health check do sistema Python RAG
     * Endpoint: GET /api/rag/python-health
     */
    public function pythonHealth()
    {
        try {
            // Verificar se script existe
            $scriptPath = base_path('scripts/rag_search/rag_search.py');
            if (!file_exists($scriptPath)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Script rag_search.py não encontrado',
                    'script_path' => $scriptPath
                ], 404);
            }

            // Verificar Python
            $pythonVersion = shell_exec('python3 --version 2>/dev/null');
            if (!$pythonVersion) {
                return response()->json([
                    'success' => false,
                    'error' => 'Python3 não encontrado no sistema'
                ], 500);
            }

            // Verificar dependências Python (teste rápido)
            $testCmd = 'python3 -c "import sys; sys.path.insert(0, \'' . dirname($scriptPath) . '\'); import config, embeddings_service, vector_search, llm_service, database; print(\'OK\')" 2>/dev/null';
            $depsTest = shell_exec($testCmd);

            // Verificar dados no banco
            $chunksWithEmbeddings = DB::table('chunks')->whereNotNull('embedding')->count();
            $totalChunks = DB::table('chunks')->count();
            $totalDocuments = DB::table('documents')->count();

            return response()->json([
                'success' => true,
                'python_version' => trim($pythonVersion),
                'script_exists' => true,
                'dependencies_test' => trim($depsTest) === 'OK',
                'database_stats' => [
                    'total_documents' => $totalDocuments,
                    'total_chunks' => $totalChunks,
                    'chunks_with_embeddings' => $chunksWithEmbeddings,
                    'embedding_coverage' => $totalChunks > 0 ? round(($chunksWithEmbeddings / $totalChunks) * 100, 2) : 0
                ],
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro no health check: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Comparação entre busca PHP vs Python
     * Endpoint: POST /api/rag/compare-search
     */
    public function compareSearch(Request $request)
    {
        try {
            $query = $request->input('query');
            $documentId = $request->input('document_id');
            $topK = max(1, min(10, intval($request->input('top_k', 5))));

            if (!$query) {
                return response()->json([
                    'success' => false,
                    'error' => 'Parâmetro query é obrigatório'
                ], 422);
            }

            // Busca PHP (RagAnswerController)
            $phpStart = microtime(true);
            $phpController = new \App\Http\Controllers\RagAnswerController();
            $phpRequest = new Request([
                'query' => $query,
                'document_id' => $documentId,
                'top_k' => $topK
            ]);
            $phpResponse = $phpController->answer($phpRequest);
            $phpTime = microtime(true) - $phpStart;

            // Busca Python
            $pythonStart = microtime(true);
            $pythonRequest = new Request([
                'query' => $query,
                'document_id' => $documentId,
                'top_k' => $topK,
                'include_answer' => false // Apenas chunks para comparação
            ]);
            $pythonResponse = $this->pythonSearch($pythonRequest);
            $pythonTime = microtime(true) - $pythonStart;

            return response()->json([
                'success' => true,
                'query' => $query,
                'comparison' => [
                    'php' => [
                        'success' => $phpResponse->getData()->ok ?? false,
                        'chunks_found' => $phpResponse->getData()->used_chunks ?? 0,
                        'execution_time' => round($phpTime, 3),
                        'method' => 'text_search_fts'
                    ],
                    'python' => [
                        'success' => $pythonResponse->getData()->success ?? false,
                        'chunks_found' => count($pythonResponse->getData()->chunks ?? []),
                        'execution_time' => round($pythonTime, 3),
                        'method' => 'vector_search'
                    ]
                ],
                'winner' => [
                    'speed' => $phpTime < $pythonTime ? 'php' : 'python',
                    'chunks' => count($pythonResponse->getData()->chunks ?? []) > ($phpResponse->getData()->used_chunks ?? 0) ? 'python' : 'php'
                ]
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Erro na comparação: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Processa resumo de vídeo usando transcrição completa
     * OTIMIZAÇÃO: Envia transcrição direto para LLM sem busca vetorial
     */
    private function handleVideoSummary($query, $documentId, $document)
    {
        $startTime = microtime(true);
        
        try {
            // Busca todos os chunks do vídeo (transcrição completa)
            $chunks = DB::table('chunks')
                ->where('document_id', $documentId)
                ->orderBy('chunk_index')
                ->get(['content', 'chunk_index']);
            
            if ($chunks->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Transcrição não encontrada'
                ], 404);
            }
            
            // Monta transcrição completa
            $transcricao = $chunks->map(fn($c) => $c->content)->join("\n\n");
            
            Log::info('Transcrição completa montada', [
                'document_id' => $documentId,
                'chunks_count' => $chunks->count(),
                'text_length' => strlen($transcricao)
            ]);
            
            // Chama LLM diretamente
            $llmService = app(\App\Services\LlmService::class);
            
            if (!$llmService->enabled()) {
                return response()->json([
                    'success' => false,
                    'error' => 'LLM não está habilitado. Verifique GOOGLE_GENAI_API_KEY'
                ], 500);
            }
            
            // OTIMIZAÇÃO: Se transcrição muito grande (>20k chars), usa apenas top chunks
            if (strlen($transcricao) > 20000) {
                Log::info('Transcrição muito grande, usando apenas primeiros chunks', [
                    'original_length' => strlen($transcricao),
                    'chunks_total' => $chunks->count()
                ]);
                
                // Usa apenas os primeiros 15 chunks (~15k chars)
                $transcricao = $chunks->take(15)->map(fn($c) => $c->content)->join("\n\n");
                
                Log::info('Transcrição reduzida', [
                    'new_length' => strlen($transcricao),
                    'chunks_used' => 15
                ]);
            }
            
            Log::info('Chamando LLM com transcrição', [
                'query_length' => strlen($query),
                'context_length' => strlen($transcricao)
            ]);
            
            $answer = $llmService->answerFromContext($query, $transcricao);
            
            if (!$answer) {
                Log::error('LLM retornou resposta vazia ou null', [
                    'document_id' => $documentId,
                    'transcricao_length' => strlen($transcricao)
                ]);
                
                return response()->json([
                    'success' => false,
                    'error' => 'LLM não conseguiu gerar resposta. Transcrição pode ser muito grande.'
                ], 500);
            }
            
            $executionTime = microtime(true) - $startTime;
            
            Log::info('Resumo de vídeo gerado com sucesso', [
                'document_id' => $documentId,
                'execution_time' => $executionTime
            ]);
            
            return response()->json([
                'success' => true,
                'ok' => true,
                'query' => $query,
                'answer' => $answer,
                'document_id' => $documentId,
                'chunks_used' => $chunks->count(),
                'method' => 'video_transcription_full',
                'execution_time' => round($executionTime, 2),
                'metadata' => [
                    'strategy' => 'VIDEO_SUMMARY',
                    'reason' => 'Query de resumo em vídeo - usa transcrição completa',
                    'optimization' => 'Sem busca vetorial, direto para LLM'
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erro ao processar resumo de vídeo', [
                'document_id' => $documentId,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Erro ao processar resumo: ' . $e->getMessage()
            ], 500);
        }
    }
}
