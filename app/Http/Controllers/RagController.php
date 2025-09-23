<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\UploadedFile;
use App\Services\VertexClient;
use App\Services\RagPipeline;
use App\Services\HybridRetriever;
use App\Services\VertexGenerator;
use App\Services\RagCache;
use App\Services\RagMetrics;
use App\Services\ChunkingStrategy;
use App\Services\EmbeddingCache;
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
            ->get(['id','title','source','created_at']);

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
    public function ingest(Request $req, RagPipeline $pipeline)
    {
        try {
            [$title, $text] = $this->resolveIngestPayload($req);
            $tenantSlug = $req->input('tenant_slug', 'default');

            if ($text === '') {
                return response()->json([
                    'ok' => false,
                    'error' => 'Nenhum texto para ingestar. Para PDF instale poppler-utils (pdftotext) ou envie DOCX/TXT, ou mande JSON {title,text}.'
                ], 422);
            }

            // Create document first
            $docId = DB::table('documents')->insertGetId([
                'title' => $title ?: 'Sem título',
                'source' => 'upload',
                'tenant_slug' => $tenantSlug,
                'metadata' => json_encode([
                    'original_length' => mb_strlen($text),
                    'upload_source' => 'api',
                    'processed_at' => now()->toISOString()
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Use enterprise pipeline for processing
            $result = $pipeline->processDocument(
                $tenantSlug,
                $docId,
                $text,
                [
                    'file_type' => $req->input('file_type', 'text'),
                    'source' => 'upload'
                ],
                [
                    'chunk_size' => $req->input('chunk_size', 1000),
                    'overlap_size' => $req->input('overlap_size', 200),
                    'preserve_structure' => $req->input('preserve_structure', true)
                ]
            );

            if (!$result['success']) {
                // Rollback document creation if processing failed
                DB::table('documents')->where('id', $docId)->delete();
                return response()->json([
                    'ok' => false,
                    'error' => 'Document processing failed: ' . ($result['error'] ?? 'Unknown error')
                ], 500);
            }

            // 4) Cookie de sessão do doc
            $minutes = 60*24*30;
            return response()
                ->json([
                    'ok' => true,
                    'document_id' => $docId,
                    'chunks_created' => $result['chunks_created'],
                    'processing_time' => $result['processing_time'],
                    'deduplication_ratio' => $result['deduplication_ratio'],
                    'cache_stats' => $result['embedding_cache_hits'] ?? null
                ], 201)
                ->cookie('rag_last_doc_id', (string)$docId, $minutes, '/', null, false, false);

        } catch (Throwable $e) {
            Log::error("ingest error: ".$e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['ok'=>false,'error'=>$e->getMessage()], 500);
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
    public function batchIngest(Request $req, RagPipeline $pipeline)
    {
        try {
            $documents = $req->input('documents', []);
            $tenantSlug = $req->input('tenant_slug', 'default');

            if (empty($documents)) {
                return response()->json(['ok'=>false,'error'=>'No documents provided'], 422);
            }

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

                    $result = $pipeline->processDocument(
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
            }

            return response()->json([
                'ok' => true,
                'tenant_slug' => $tenantSlug,
                'total_documents' => count($documents),
                'successful_documents' => $successCount,
                'results' => $results
            ]);

        } catch (Throwable $e) {
            Log::error("Batch ingest failed: ".$e->getMessage());
            return response()->json(['ok'=>false,'error'=>$e->getMessage()], 500);
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
            DB::table('document_chunks')->where('document_id', $docId)->delete();

            // Get original content (this would need to be stored)
            $content = $req->input('content');
            if (!$content) {
                return response()->json(['ok'=>false,'error'=>'Original content required for reprocessing'], 422);
            }

            // Reprocess with pipeline
            $result = $pipeline->processDocument(
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

    private function extractTextFromUpload(UploadedFile $file): string
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension());
        $path = $file->getRealPath();

        if ($ext === 'txt') return trim((string)file_get_contents($path));

        if ($ext === 'pdf') {
            $bin = trim((string)@shell_exec('which pdftotext 2>/dev/null'));
            if ($bin === '') return '';
            $cmd = escapeshellcmd($bin).' -enc UTF-8 -nopgbrk -q -layout '.escapeshellarg($path).' -';
            $out = @shell_exec($cmd);
            return trim((string)$out);
        }

        return '';
    }
}
