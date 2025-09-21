<?php

namespace App\Services;

class VertexClient
{
    private bool $useLocal;
    private int $dim;

    public function __construct()
    {
        // Se EMBED_PROVIDER=vertex, tenta Vertex; caso contrário, usa local
        $mode = strtolower((string) env('EMBED_PROVIDER', env('RAG_EMBED_PROVIDER', 'local')));
        $this->useLocal = ($mode !== 'vertex');
        $this->dim = (int) env('EMBED_DIM', env('RAG_EMBED_DIM', 768));
        if ($this->dim <= 0) $this->dim = 768;
    }

    /**
     * Gera embeddings para um array de textos.
     * - local: determinístico, estável por texto (hash → [-1,1]).
     * - vertex: aqui você pode ligar o cliente real depois; por enquanto cai no local.
     */
    public function embed(array $texts): array
    {
        if ($this->useLocal) {
            return $this->embedLocal($texts);
        }

        // TODO: implementar chamada real ao Vertex (quando quiser).
        // Por enquanto, mesmo em "vertex", se não estiver implementado, cai no local:
        return $this->embedLocal($texts);
    }

    /**
     * Gera um texto a partir de "user" + partes de sistema/contexto.
     * Em modo local: faz um resumo simples (recorta o contexto).
     */
    public function generate(string $user, array $parts = []): string
    {
        if ($this->useLocal) {
            $ctx = trim(implode("\n\n", $parts));
            if ($ctx === '') return '';
            $plain = preg_replace('/\s+/', ' ', $ctx);
            $snippet = mb_substr($plain ?? '', 0, 900);
            return "Resumo (local, sem LLM): " . $snippet;
        }

        // TODO: implementar chamada real ao Vertex (quando quiser).
        // Fallback seguro:
        $ctx = trim(implode("\n\n", $parts));
        $plain = preg_replace('/\s+/', ' ', $ctx);
        return "Resumo (fallback): " . mb_substr($plain ?? '', 0, 900);
    }

    // ----------------- helpers -----------------

    private function embedLocal(array $texts): array
    {
        $out = [];
        foreach ($texts as $t) {
            $vec = [];
            for ($i = 0; $i < $this->dim; $i++) {
                // sha1 determinístico → pega 8 hex → int → mapeia para [-1,1)
                $hex = substr(sha1($t . '|' . $i), 0, 8);
                $h   = hexdec($hex);
                $v   = (($h % 1000000) / 500000.0) - 1.0; // [-1,1)
                $vec[] = round($v, 6);
            }
            $out[] = $vec;
        }
        return $out;
    }
}
