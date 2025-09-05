<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\VertexClient;
use Throwable;

class RagAnswerController extends Controller
{
    public function answer(Request $req, VertexClient $vertex)
    {
        $q = $this->getStringParam($req, ['q','query','question','prompt','text','message','msg','qtext','search']);
        $topK = $this->getIntParam($req, ['top_k','topk','k','top','limit','n'], 5, 1, 50);

        $docId = $this->getIntParam($req, ['document_id','doc_id','id'], 0, 0, PHP_INT_MAX) ?: null;
        $title = $this->getStringParam($req, ['title','filename','name']);
        $scope = strtolower($this->getStringParam($req, ['scope']));

        if ($q === '') return response()->json(['ok'=>false,'error'=>'Parâmetro q ausente.'], 422);

        $usedDocId = $this->resolveDocId($req, $docId, $title, $scope);

        try {
            $contexts = [];
            $distances = [];
            $hasEmb = $this->docHasEmbeddings($usedDocId);

            if ($hasEmb) {
                $qEmb = $vertex->embed([$q])[0] ?? null;
                if ($qEmb) {
                    $vecLit = '['.implode(',', $qEmb).']';

                    $rows = DB::table('chunks as c')
                        ->select('c.content')
                        ->selectRaw('(c.embedding <=> CAST(? AS vector)) AS distance', [$vecLit])
                        ->where('c.document_id', $usedDocId)
                        ->whereNotNull('c.embedding')
                        ->orderByRaw('c.embedding <=> CAST(? AS vector) ASC', [$vecLit])
                        ->limit($topK)
                        ->get();

                    foreach ($rows as $r) { $contexts[] = $r->content; $distances[] = (float)$r->distance; }
                }
            }

            // FALLBACK textual se necessário
            if (empty($contexts)) {
                $rows = DB::table('chunks')
                    ->select('content')
                    ->where('document_id', $usedDocId)
                    ->orderByRaw("CASE WHEN lower(content) LIKE lower('%' || ? || '%') THEN 0 ELSE 1 END, ord ASC", [$q])
                    ->limit($topK)
                    ->get();
                foreach ($rows as $r) { $contexts[] = $r->content; }
            }

            $system = "Responda ESTRITAMENTE com base no contexto. Se faltar informação suficiente, diga claramente que não há dados suficientes.";
            $contextText = "Contexto (trechos relevantes):\n\n";
            foreach ($contexts as $i => $c) $contextText .= "Trecho ".($i+1).":\n".$c."\n\n";
            $user = "Pergunta:\n".$q."\n\nResponda em português, de forma objetiva.";

            $answer = '';
            try { $answer = trim($vertex->generate($user, [$system, $contextText])); }
            catch (Throwable $e) { Log::warning("generate best-effort falhou: ".$e->getMessage()); }
            if ($answer === '') {
                $answer = $this->simpleFallbackAnswer($contexts, $q);
            }

            return response()->json([
                'ok'=>true,'query'=>$q,'top_k'=>$topK,'used_doc'=>$usedDocId,
                'used_chunks'=>count($contexts),'answer'=>$answer,
                'debug'=>['distances'=>$distances, 'mode'=>$hasEmb ? 'vector_or_fallback' : 'fallback_text'],
            ]);
        } catch (Throwable $e) {
            Log::error("rag.answer failed: ".$e->getMessage());
            return response()->json(['ok'=>false,'error'=>$e->getMessage()], 500);
        }
    }

    // ---------- helpers ----------
    private function simpleFallbackAnswer(array $contexts, string $q): string
    {
        if (empty($contexts)) return "Não encontrei informações suficientes no documento para responder.";
        $joined = trim(implode("\n\n", array_slice($contexts, 0, 6)));
        $slice  = mb_substr($joined, 0, 1200);
        return "Resumo (fallback, sem LLM):\n\n" . $slice;
    }

    private function resolveDocId(Request $req, ?int $docId, ?string $title, ?string $scope): ?int
    {
        if ($docId && DB::table('documents')->where('id',$docId)->exists()) return $docId;
        if ($title !== '') { $row = DB::table('documents')->where('title',$title)->orderByDesc('created_at')->first(); if ($row) return (int)$row->id; }
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
}
