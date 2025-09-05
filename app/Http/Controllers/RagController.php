<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\UploadedFile;
use App\Services\VertexClient;
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

    // ---------- INGEST (best-effort) ----------
    public function ingest(Request $req, VertexClient $vertex)
    {
        try {
            [$title, $text] = $this->resolveIngestPayload($req);

            if ($text === '') {
                return response()->json([
                    'ok' => false,
                    'error' => 'Nenhum texto para ingestar. Para PDF instale poppler-utils (pdftotext) ou envie DOCX/TXT, ou mande JSON {title,text}.'
                ], 422);
            }

            // 1) Chunking
            $chunks = $this->chunkText($text, 1000, 200);
            if (empty($chunks)) {
                return response()->json(['ok'=>false,'error'=>'Texto vazio após chunking.'], 422);
            }

            // 2) Tenta embeddings (best-effort)
            $embs = null;
            try {
                $embs = $vertex->embed($chunks);
                if (!is_array($embs) || count($embs) !== count($chunks)) {
                    $embs = null;
                }
            } catch (Throwable $e) {
                Log::warning("embed best-effort falhou: ".$e->getMessage());
                $embs = null;
            }

            // 3) Persiste SEMPRE (com embedding se houver)
            DB::beginTransaction();
            $docId = DB::table('documents')->insertGetId([
                'title'     => $title ?: 'Sem título',
                'source'    => 'upload',
                'metadata'  => json_encode(['len' => mb_strlen($text)]),
                'created_at'=> now(),
                'updated_at'=> now(),
            ]);

            $rows = [];
            foreach ($chunks as $i => $content) {
                $row = [
                    'document_id' => $docId,
                    'ord'         => $i,
                    'content'     => $content,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ];
                if ($embs && isset($embs[$i]) && is_array($embs[$i])) {
                    $vec = '[' . implode(',', $embs[$i]) . ']';
                    $row['embedding'] = DB::raw("CAST('{$vec}' AS vector)");
                }
                $rows[] = $row;
            }
            foreach (array_chunk($rows, 1000) as $batch) {
                DB::table('chunks')->insert($batch);
            }
            DB::commit();

            // 4) Cookie de sessão do doc
            $minutes = 60*24*30;
            return response()
                ->json(['ok'=>true,'document_id'=>$docId,'chunks'=>count($chunks)], 201)
                ->cookie('rag_last_doc_id', (string)$docId, $minutes, '/', null, false, false);

        } catch (Throwable $e) {
            Log::error("ingest error: ".$e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['ok'=>false,'error'=>$e->getMessage()], 500);
        }
    }

    // ---------- QUERY (Query Builder → binds consistentes) ----------
    public function query(Request $req, VertexClient $vertex)
    {
        $q = $this->getStringParam($req, ['q','query','question','prompt','text','message','msg','qtext','search']);
        $topK = $this->getIntParam($req, ['top_k','topk','k','top','limit','n'], 5, 1, 50);

        $docId = $this->getIntParam($req, ['document_id','doc_id','id'], 0, 0, PHP_INT_MAX) ?: null;
        $title = $this->getStringParam($req, ['title','filename','name']);
        $scope = strtolower($this->getStringParam($req, ['scope']));

        if ($q === '') return response()->json(['ok'=>false,'error'=>'Parâmetro q ausente.'], 422);

        $usedDocId = $this->resolveDocId($req, $docId, $title, $scope);

        try {
            $hasEmb = $this->docHasEmbeddings($usedDocId);

            if ($hasEmb) {
                $qEmb = $vertex->embed([$q])[0] ?? null;
                if ($qEmb) {
                    $vecLit = '['.implode(',', $qEmb).']';

                    $rows = DB::table('chunks as c')
                        ->select('c.id','c.document_id','c.ord','c.content')
                        ->selectRaw('(c.embedding <=> CAST(? AS vector)) AS distance', [$vecLit])
                        ->where('c.document_id', $usedDocId)
                        ->whereNotNull('c.embedding')
                        ->orderByRaw('c.embedding <=> CAST(? AS vector) ASC', [$vecLit])
                        ->limit($topK)
                        ->get();

                    $results = [];
                    foreach ($rows as $r) {
                        $dist = (float)$r->distance; $sim = 1.0 - $dist;
                        $results[] = [
                            'id'=>$r->id,'document_id'=>$r->document_id,'ord'=>$r->ord,
                            'content'=>$r->content,'distance'=>$dist,'similarity'=>$sim,
                        ];
                    }
                    if (!empty($results)) {
                        return response()->json([
                            'ok'=>true,'query'=>$q,'top_k'=>$topK,'used_doc'=>$usedDocId,'mode'=>'vector','results'=>$results,
                        ]);
                    }
                }
            }

            // FALLBACK textual (sem vetor)
            $rows = DB::table('chunks')
                ->select('id','document_id','ord','content')
                ->where('document_id', $usedDocId)
                ->orderByRaw("CASE WHEN lower(content) LIKE lower('%' || ? || '%') THEN 0 ELSE 1 END, ord ASC", [$q])
                ->limit($topK)
                ->get();

            $results = $rows->map(function ($r) {
                return [
                    'id'=>$r->id,'document_id'=>$r->document_id,'ord'=>$r->ord,
                    'content'=>$r->content,'distance'=>null,'similarity'=>null,
                ];
            })->all();

            return response()->json([
                'ok'=>true,'query'=>$q,'top_k'=>$topK,'used_doc'=>$usedDocId,'mode'=>'fallback_text','results'=>$results,
            ]);

        } catch (Throwable $e) {
            Log::error("query failed: ".$e->getMessage(), ['trace' => $e->getTraceAsString()]);
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

        if ($ext === 'docx') {
            if (!class_exists(ZipArchive::class)) return '';
            $zip = new ZipArchive();
            if ($zip->open($path) === true) {
                $xml = $zip->getFromName('word/document.xml');
                $zip->close();
                if ($xml !== false) {
                    $xml  = preg_replace('/<\/w:p>/', "\n", $xml);
                    $text = preg_replace('/<[^>]+>/', '', $xml);
                    return trim(html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8'));
                }
            }
            return '';
        }

        return '';
    }
}
